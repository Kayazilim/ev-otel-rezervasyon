<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exception\BusyException;
use App\Exception\PersistenceException;
use App\Exception\UniqueConstraintException;
use App\Model\BookableUnit;
use App\Model\CancellationPolicy;
use App\Model\Reservation;
use App\Model\ReservationStatus;
use App\Model\Role;
use App\Repository\Json\JsonSchema;
use App\Repository\MySql\MySqlDatabase;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AppTestCase;

/**
 * Aşama 3 (PDO + MySQL) veritabanı düzeyindeki güvenceler. Yalnızca MySQL ile koşar:
 *   composer test:mysql   (EVOTEL_TEST_STORAGE=mysql)
 * Uygulama davranışının tamamı (135+ test) zaten iki depoda da aynı testlerle doğrulanır.
 */
#[Group('mysql')]
final class MySqlStorageTest extends AppTestCase
{
    private BookableUnit $unit;
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->usesMySql()) {
            self::markTestSkipped('MySQL testleri için: composer test:mysql (EVOTEL_TEST_STORAGE=mysql).');
        }
        $host = $this->user(Role::IlanSahibi);
        $this->customerId = (int) $this->user()->id;
        $this->unit = $this->unit((int) $this->property((int) $host->id)->id);
    }

    public function testMigrationsCreateEveryTableOnInnoDb(): void
    {
        $rows = $this->c->mysqlDatabase()->select(
            'SELECT TABLE_NAME AS tablo, ENGINE AS motor FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        $engines = array_column($rows, 'motor', 'tablo');
        foreach (array_keys(JsonSchema::tables()) as $table) {
            self::assertArrayHasKey($table, $engines, $table . ' tablosu migration ile oluşturulmalı');
            self::assertSame('InnoDB', $engines[$table], 'Transaction ve satır kilidi için InnoDB');
        }
        self::assertCount(4, $this->c->mysqlDatabase()->select('SELECT name FROM schema_migrations'));
    }

    #[Group('FR-18')]
    #[Group('FR-33')]
    #[Group('NFR-05')]
    public function testUniqueUnitNightConstraintStopsDoubleBookingEvenWithoutAppRules(): void
    {
        // Uygulama kuralları atlanarak (depoya doğrudan) aynı gece iki kez yazılmaya çalışılır.
        $this->c->tx()->transactional(fn () => $this->c->reservations()->createWithNights($this->reservation('RZ-1', 'k1'), [], [$this->d('2026-11-10')]));

        try {
            $this->c->tx()->transactional(fn () => $this->c->reservations()->createWithNights(
                $this->reservation('RZ-2', 'k2'),
                [],
                [$this->d('2026-11-09'), $this->d('2026-11-10')],
            ));
            self::fail('UNIQUE(unit_id, night_date) ihlali beklenirdi');
        } catch (UniqueConstraintException $e) {
            self::assertStringContainsString('uq_nights_unit_night', implode(',', $e->columns));
        }
        self::assertCount(1, $this->c->reservations()->all(), 'İkinci rezervasyon satırı da geri alındı (transaction)');
        self::assertCount(1, $this->c->reservations()->allNights(), '2026-11-09 gecesi de yazılmadı');
    }

    #[Group('BR-01')]
    public function testCheckConstraintRejectsCheckOutBeforeCheckIn(): void
    {
        $reservation = $this->reservation('RZ-3', 'k3');
        $reservation->checkOut = $reservation->checkIn;

        $this->expectException(PDOException::class);
        $this->c->tx()->transactional(fn () => $this->c->reservations()->createWithNights($reservation, [], []));
    }

    #[Group('FR-11')]
    #[Group('NFR-05')]
    #[Group('NFR-13')]
    public function testLockUnitHoldsARowLockUntilTheTransactionEnds(): void
    {
        $other = $this->secondConnection();
        $id = (int) $this->unit->id;
        $neighbour = (int) $this->unit($this->unit->propertyId, 1000, 2, '102')->id;

        $this->c->tx()->transactional(function () use ($other, $id, $neighbour): void {
            $this->c->reservations()->lockUnit($id); // SELECT ... FOR UPDATE
            try {
                $other->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE NOWAIT', [$id]);
                self::fail('İkinci bağlantı kilitli birim satırını alamamalı');
            } catch (PDOException $e) {
                self::assertContains((int) ($e->errorInfo[1] ?? 0), [3572, 1205], 'Kilit meşgul (NOWAIT)');
            }
            // Kilit satır düzeyindedir: aynı ilanın başka birimine gelen rezervasyon beklemez.
            self::assertNotNull($other->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE NOWAIT', [$neighbour]));
        });

        // COMMIT sonrası kilit bırakılır
        self::assertNotNull($other->transaction(fn () => $other->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE NOWAIT', [$id])));
    }

    #[Group('FR-33')]
    #[Group('NFR-06')]
    #[Group('NFR-13')]
    public function testLockWaitTimeoutIsRetriedTwiceThenReportedAsBusy(): void
    {
        $holder = $this->secondConnection();
        $holder->pdo()->beginTransaction();
        $holder->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE', [(int) $this->unit->id]);

        $waiter = $this->secondConnection(lockWaitSeconds: 1); // NFR-13: üst sınır testte 1 sn
        $attempts = 0;
        $started = microtime(true);
        try {
            $waiter->transaction(function () use ($waiter, &$attempts): void {
                $attempts++;
                $waiter->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE', [(int) $this->unit->id]);
            });
            self::fail('Kilit alınamayınca BusyException beklenirdi');
        } catch (BusyException $e) {
            self::assertStringContainsString('yoğunluk', $e->getMessage());
        } finally {
            $holder->pdo()->rollBack();
        }
        self::assertSame(3, $attempts, 'İlk deneme + 2 yeniden deneme (SRS 4.5)');
        self::assertGreaterThanOrEqual(3.0, microtime(true) - $started);
    }

    #[Group('FR-33')]
    public function testLockUnitOutsideATransactionIsAProgrammingError(): void
    {
        $this->expectException(PersistenceException::class);
        $this->c->reservations()->lockUnit((int) $this->unit->id);
    }

    #[Group('FR-15')]
    #[Group('NFR-05')]
    public function testConditionalUpdateLetsOnlyTheFirstOfTwoConnectionsWin(): void
    {
        $saved = $this->c->tx()->transactional(fn () => $this->c->reservations()->createWithNights($this->reservation('RZ-4', 'k4'), [], [$this->d('2026-11-10')]));
        $other = $this->secondConnection();
        $sql = "UPDATE reservations SET status = 'onaylandi' WHERE id = ? AND status = 'beklemede'";

        self::assertTrue($this->c->reservations()->updateStatusIf((int) $saved->id, ReservationStatus::Beklemede, ReservationStatus::Onaylandi));
        self::assertSame(0, $other->execute($sql, [(int) $saved->id]), 'İkinci yazan 0 satır etkiler: çakışma olarak bildirilir');
    }

    private function secondConnection(int $lockWaitSeconds = 5): MySqlDatabase
    {
        $s = $this->c->settings;

        return new MySqlDatabase($s->dbHost, $s->dbPort, $s->dbName, $s->dbUser, $s->dbPassword, $lockWaitSeconds, 2);
    }

    private function reservation(string $number, string $key): Reservation
    {
        $now = $this->clock->now();

        return new Reservation(
            null,
            $number,
            $this->customerId,
            (int) $this->unit->id,
            $this->d('2026-11-10'),
            $this->d('2026-11-11'),
            1,
            ReservationStatus::Beklemede,
            $now->modify('+10 minutes'),
            100000,
            CancellationPolicy::Orta,
            $key,
            'Test',
            '0532 000 00 00',
            null,
            null,
            null,
            0,
            $now,
            $now,
        );
    }
}
