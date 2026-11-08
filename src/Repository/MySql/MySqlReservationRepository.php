<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Exception\PersistenceException;
use App\Model\PaymentStatus;
use App\Model\Reservation;
use App\Model\ReservationCharge;
use App\Model\ReservationNight;
use App\Model\ReservationRevenue;
use App\Model\ReservationStatus;
use App\Repository\ReservationRepositoryInterface;
use App\Support\DateRange;
use App\Support\Decimal;
use App\Support\Row;
use BackedEnum;
use DateTimeImmutable;

/**
 * Aşama 3 rezervasyon deposu. Çekirdek problem (SRS 4.5) burada veritabanı araçlarıyla desteklenir:
 *  - lockUnit(): SELECT ... FOR UPDATE ile birim satırı kilitlenir; aynı birime gelen eşzamanlı
 *    rezervasyon işlemleri sırayla ilerler (farklı birimler birbirini beklemez).
 *  - createWithNights(): rezervasyon + kalemler + geceler tek transaction'da yazılır; UNIQUE(unit_id, night_date)
 *    ihlali tüm işlemi geri aldırır.
 *  - updateStatusIf(): UPDATE ... WHERE id = ? AND status = ? ve etkilenen satır kontrolü.
 *  - reportRows(): JOIN'li rapor sorgusu (FR-30).
 */
final class MySqlReservationRepository extends MySqlRepository implements ReservationRepositoryInterface
{
    private const MONEY_COLUMNS = ['refund_amount', 'total_price'];

    public function findById(int $id): ?Reservation
    {
        return $this->first('SELECT * FROM reservations WHERE id = ?', [$id], Reservation::fromArray(...));
    }

    public function findByNumber(string $number): ?Reservation
    {
        return $this->first('SELECT * FROM reservations WHERE reservation_no = ?', [$number], Reservation::fromArray(...));
    }

    public function findByIdempotencyKey(string $key): ?Reservation
    {
        return $this->first('SELECT * FROM reservations WHERE idempotency_key = ?', [$key], Reservation::fromArray(...));
    }

    public function numberExists(string $number): bool
    {
        return $this->db->one('SELECT 1 AS bulundu FROM reservations WHERE reservation_no = ? LIMIT 1', [$number]) !== null;
    }

    public function findOverlapping(int $unitId, DateRange $range): array
    {
        // Yarı açık aralık kesişimi: mevcut.giriş < yeni.çıkış VE yeni.giriş < mevcut.çıkış
        return $this->many(
            "SELECT * FROM reservations
             WHERE unit_id = ? AND status IN ('beklemede', 'onaylandi') AND check_in < ? AND check_out > ?
             ORDER BY check_in, id",
            [$unitId, $range->checkOut->format('Y-m-d'), $range->checkIn->format('Y-m-d')],
            Reservation::fromArray(...)
        );
    }

    public function findByCustomer(int $customerId): array
    {
        return $this->many('SELECT * FROM reservations WHERE customer_id = ? ORDER BY id', [$customerId], Reservation::fromArray(...));
    }

    public function findByUnits(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return $this->many(
            'SELECT * FROM reservations WHERE unit_id IN (' . self::placeholders($unitIds) . ') ORDER BY id',
            $unitIds,
            Reservation::fromArray(...)
        );
    }

    public function findExpiredHolds(DateTimeImmutable $now, ?int $unitId = null): array
    {
        $sql = "SELECT * FROM reservations WHERE status = 'beklemede' AND expires_at IS NOT NULL AND expires_at <= ?";
        $params = [$now];
        if ($unitId !== null) {
            $sql .= ' AND unit_id = ?';
            $params[] = $unitId;
        }

        return $this->many($sql . ' ORDER BY id', $params, Reservation::fromArray(...));
    }

    public function findConfirmedCheckedOutBy(DateTimeImmutable $date): array
    {
        return $this->many(
            "SELECT * FROM reservations WHERE status = 'onaylandi' AND check_out <= ? ORDER BY id",
            [$date->format('Y-m-d')],
            Reservation::fromArray(...)
        );
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM reservations ORDER BY id', [], Reservation::fromArray(...));
    }

    public function createWithNights(Reservation $reservation, array $charges, array $nights): Reservation
    {
        return $this->db->transaction(function () use ($reservation, $charges, $nights): Reservation {
            $data = $reservation->toArray();
            $data['id'] = null;
            $saved = Reservation::fromArray($this->insertRow('reservations', $data));
            $id = $saved->id ?? throw new PersistenceException('Rezervasyon kimliği üretilemedi.');

            foreach ($charges as $charge) {
                $row = $charge->toArray();
                $row['id'] = null;
                $row['reservation_id'] = $id;
                $this->db->insert('reservation_charges', $row);
            }
            foreach ($nights as $night) {
                // UNIQUE(unit_id, night_date) ihlali UniqueConstraintException olur; transaction geri alınır.
                $this->db->insert('reservation_nights', (new ReservationNight($id, $saved->unitId, $night))->toArray());
            }

            return $saved;
        });
    }

    public function updateStatusIf(int $id, ReservationStatus $expected, ReservationStatus $new, array $changes = []): bool
    {
        $sets = ['status = ?'];
        $params = [$new->value];
        foreach ($changes as $column => $value) {
            if (!preg_match('/^[a-z_]+$/', $column)) {
                throw new PersistenceException('Geçersiz sütun adı: ' . $column);
            }
            $sets[] = '`' . $column . '` = ?';
            $params[] = match (true) {
                $value instanceof DateTimeImmutable => Row::fmtDateTime($value),
                $value instanceof BackedEnum => $value->value,
                in_array($column, self::MONEY_COLUMNS, true) && is_int($value) => Decimal::fromInt($value),
                default => $value,
            };
        }
        $params[] = $id;
        $params[] = $expected->value;

        // Koşullu güncelleme: durum bu arada değiştiyse etkilenen satır 0 olur ve çağıran çakışma bildirir.
        return $this->db->execute('UPDATE reservations SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ?', $params) === 1;
    }

    public function releaseNights(int $reservationId): int
    {
        return $this->db->execute('DELETE FROM reservation_nights WHERE reservation_id = ?', [$reservationId]);
    }

    public function nightsOf(int $reservationId): array
    {
        return $this->many(
            'SELECT * FROM reservation_nights WHERE reservation_id = ? ORDER BY night_date',
            [$reservationId],
            ReservationNight::fromArray(...)
        );
    }

    public function allNights(): array
    {
        return $this->many('SELECT * FROM reservation_nights ORDER BY unit_id, night_date', [], ReservationNight::fromArray(...));
    }

    public function chargesOf(int $reservationId): array
    {
        return $this->many(
            'SELECT * FROM reservation_charges WHERE reservation_id = ? ORDER BY id',
            [$reservationId],
            ReservationCharge::fromArray(...)
        );
    }

    public function lockUnit(int $unitId): void
    {
        if (!$this->db->inTransaction()) {
            throw new PersistenceException('Birim kilidi yalnızca işlem içinde alınabilir.');
        }
        // Satır kilidi: işlem bitene (COMMIT/ROLLBACK) kadar aynı birimi kilitlemek isteyen diğer işlemler bekler.
        // Bekleme innodb_lock_wait_timeout (5 sn) ile sınırlıdır; aşılırsa işlem yeniden denenir (MySqlDatabase).
        $this->db->one('SELECT id FROM bookable_units WHERE id = ? FOR UPDATE', [$unitId]);
    }

    public function reportRows(?int $ownerId, DateRange $period): array
    {
        $captured = array_map(fn (PaymentStatus $s): string => $s->value, PaymentStatus::captured());
        $in = self::placeholders($captured);
        $sql = "SELECT r.id, r.unit_id, r.status, r.check_in, r.check_out,
                       COALESCE(SUM(CASE WHEN p.status IN ($in) THEN p.amount ELSE 0 END), 0) AS paid,
                       COALESCE(SUM(CASE WHEN p.status IN ($in) THEN p.refund_amount ELSE 0 END), 0) AS refunded
                FROM reservations r
                JOIN bookable_units u ON u.id = r.unit_id
                JOIN properties pr ON pr.id = u.property_id
                LEFT JOIN payments p ON p.reservation_id = r.id
                WHERE r.check_in < ? AND r.check_out > ?";
        $params = [...$captured, ...$captured, $period->checkOut->format('Y-m-d'), $period->checkIn->format('Y-m-d')];
        if ($ownerId !== null) {
            $sql .= ' AND pr.owner_id = ?';
            $params[] = $ownerId;
        }
        $sql .= ' GROUP BY r.id, r.unit_id, r.status, r.check_in, r.check_out ORDER BY r.id';

        return $this->many($sql, $params, ReservationRevenue::fromArray(...));
    }
}
