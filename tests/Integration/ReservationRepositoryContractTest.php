<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exception\UniqueConstraintException;
use App\Model\CancellationPolicy;
use App\Model\Reservation;
use App\Model\ReservationStatus;
use App\Repository\Json\JsonDatabase;
use App\Repository\Json\JsonReservationRepository;
use App\Repository\ReservationRepositoryInterface;
use App\Support\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\AppTestCase;
use Tests\Support\InMemory\InMemoryReservationRepository;

/**
 * Rezervasyon deposu sözleşme testi: aynı testler hem gerçek JSON deposunda (geçici klasörde) hem bellekteki
 * sahte depoda çalışır.
 *  1) Çekirdek problemin veri katmanındaki güvencelerini doğrudan sınar: yarı açık aralık sorgusu, yalnızca
 *     engelleyen durumlar, koşullu güncelleme ve UNIQUE(birim, gece) son savunması.
 *  2) Sahte deponun gerçek depo gibi davrandığını kanıtlar; sahte depoyla yazılan birim testlerine
 *     (BookingConflictServiceTest) bu sayede güvenilebilir.
 */
final class ReservationRepositoryContractTest extends TestCase
{
    private ?string $dir = null;
    private int $sequence = 0;

    protected function tearDown(): void
    {
        if ($this->dir !== null) {
            AppTestCase::removeDirectory($this->dir);
        }
    }

    /** @return array<string, array{string}> */
    public static function depolar(): array
    {
        return ['JSON deposu' => ['json'], 'bellekteki sahte depo' => ['bellek']];
    }

    private function repository(string $tur): ReservationRepositoryInterface
    {
        if ($tur === 'bellek') {
            return new InMemoryReservationRepository();
        }
        $this->dir = sys_get_temp_dir() . '/evotel-sozlesme-' . bin2hex(random_bytes(5));

        return new JsonReservationRepository(new JsonDatabase($this->dir));
    }

    private function add(ReservationRepositoryInterface $repository, ReservationStatus $status, string $in, string $out, int $unitId = 1): Reservation
    {
        $range = DateRange::fromStrings($in, $out);
        $now = new DateTimeImmutable('2026-11-01 10:00:00');
        $this->sequence++;
        $reservation = new Reservation(
            null,
            'RZ-SOZ-' . $this->sequence,
            20,
            $unitId,
            $range->checkIn,
            $range->checkOut,
            1,
            $status,
            null,
            200000,
            CancellationPolicy::Orta,
            'anahtar-' . $this->sequence,
            'Test Misafir',
            '05550000000',
            null,
            null,
            null,
            0,
            $now,
            $now,
        );
        $blocking = in_array($status, [ReservationStatus::Beklemede, ReservationStatus::Onaylandi], true);

        return $repository->createWithNights($reservation, [], $blocking ? $range->nightDates() : []);
    }

    /** @return list<int> */
    private function idsOverlapping(ReservationRepositoryInterface $repository, string $in, string $out, int $unitId = 1): array
    {
        return array_map(fn (Reservation $r): int => (int) $r->id, $repository->findOverlapping($unitId, DateRange::fromStrings($in, $out)));
    }

    #[DataProvider('depolar')]
    #[Group('BR-11')]
    #[Group('BR-13')]
    #[Group('CS-03')]
    #[Group('AC-14')]
    public function testOverlapQueryUsesHalfOpenInterval(string $tur): void
    {
        $repository = $this->repository($tur);
        $stay = $this->add($repository, ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');

        self::assertSame([], $this->idsOverlapping($repository, '2026-11-12', '2026-11-14'), 'Çıkış günü (12) yeni girişe açık');
        self::assertSame([], $this->idsOverlapping($repository, '2026-11-08', '2026-11-10'), 'Giriş günü (10) önceki misafirin çıkışı olabilir');
        self::assertSame([(int) $stay->id], $this->idsOverlapping($repository, '2026-11-11', '2026-11-13'), 'Bir gece kesişirse bulunur');
        self::assertSame([(int) $stay->id], $this->idsOverlapping($repository, '2026-11-09', '2026-11-13'), 'Kapsayan aralık bulunur');
        self::assertSame([], $this->idsOverlapping($repository, '2026-11-10', '2026-11-12', 2), 'Başka birim etkilenmez (BR-04)');
    }

    #[DataProvider('depolar')]
    #[Group('BR-12')]
    #[Group('AC-16')]
    public function testOnlyPendingAndConfirmedReservationsAreReturned(string $tur): void
    {
        $repository = $this->repository($tur);
        foreach ([ReservationStatus::IptalEdildi, ReservationStatus::Reddedildi, ReservationStatus::SuresiDoldu, ReservationStatus::Tamamlandi] as $status) {
            $this->add($repository, $status, '2026-11-10', '2026-11-12');
        }
        self::assertSame([], $this->idsOverlapping($repository, '2026-11-10', '2026-11-12'));

        $hold = $this->add($repository, ReservationStatus::Beklemede, '2026-11-10', '2026-11-12');
        self::assertSame([(int) $hold->id], $this->idsOverlapping($repository, '2026-11-10', '2026-11-12'));
    }

    /** Eşzamanlılık güvencesi: UPDATE ... WHERE id = ? AND status = ?; durum az önce değiştiyse hiçbir şey yazılmaz. */
    #[DataProvider('depolar')]
    #[Group('CS-07')]
    #[Group('CS-10')]
    public function testConditionalUpdateOnlyChangesTheExpectedStatus(string $tur): void
    {
        $repository = $this->repository($tur);
        $reservation = $this->add($repository, ReservationStatus::Beklemede, '2026-11-10', '2026-11-12');
        $id = (int) $reservation->id;

        self::assertTrue($repository->updateStatusIf($id, ReservationStatus::Beklemede, ReservationStatus::IptalEdildi));
        // Geç kalan ikinci işlem hâlâ "Beklemede" sanıyor: onay yazılmaz, kayıt iptal olarak kalır.
        self::assertFalse($repository->updateStatusIf($id, ReservationStatus::Beklemede, ReservationStatus::Onaylandi));
        self::assertSame(ReservationStatus::IptalEdildi, $repository->findById($id)?->status);
    }

    /** Çift rezervasyonun son savunması: aynı birimin aynı gecesi ikinci kez yazılamaz; yarım kayıt kalmaz. */
    #[DataProvider('depolar')]
    #[Group('FR-18')]
    #[Group('CS-12')]
    public function testSameNightCannotBeStoredTwice(string $tur): void
    {
        $repository = $this->repository($tur);
        $this->add($repository, ReservationStatus::Onaylandi, '2026-11-10', '2026-11-12');

        try {
            $this->add($repository, ReservationStatus::Onaylandi, '2026-11-11', '2026-11-13');
            self::fail('Aynı gece ikinci kez yazılmamalıydı');
        } catch (UniqueConstraintException $e) {
            self::assertSame('reservation_nights', $e->table);
        }
        self::assertCount(1, $repository->all(), 'İkinci rezervasyon yazılmadı (işlem geri alındı)');
        self::assertCount(2, $repository->allNights(), 'Yalnızca ilk rezervasyonun iki gecesi var');
        self::assertSame([], $this->idsOverlapping($repository, '2026-11-12', '2026-11-13'));
    }
}
