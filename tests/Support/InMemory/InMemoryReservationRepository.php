<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Exception\UniqueConstraintException;
use App\Model\Reservation;
use App\Model\ReservationCharge;
use App\Model\ReservationNight;
use App\Model\ReservationStatus;
use App\Repository\ReservationRepositoryInterface;
use App\Support\DateRange;
use App\Support\Decimal;
use App\Support\Row;
use DateTimeImmutable;
use LogicException;

/**
 * Bellekte çalışan sahte rezervasyon deposu (yalnızca testler için). Servisler depo arayüzünü bildiği için
 * çekirdek kurallar dosyaya hiç dokunmadan sınanabilir. Davranış JsonReservationRepository ile aynıdır:
 * yarı açık aralık kesişimi, UNIQUE(birim, gece) ve koşullu güncelleme (etkilenen kayıt sayısı).
 */
final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    private const ACTIVE = ['beklemede', 'onaylandi'];

    private MemoryTable $reservations;
    private MemoryTable $nights;
    private MemoryTable $charges;

    public function __construct()
    {
        $this->reservations = new MemoryTable();
        $this->nights = new MemoryTable(false);
        $this->charges = new MemoryTable();
    }

    public function findById(int $id): ?Reservation
    {
        $row = $this->reservations->first(fn (array $r): bool => $r['id'] === $id);

        return $row === null ? null : Reservation::fromArray($row);
    }

    public function findByNumber(string $number): ?Reservation
    {
        $row = $this->reservations->first(fn (array $r): bool => $r['reservation_no'] === $number);

        return $row === null ? null : Reservation::fromArray($row);
    }

    public function findByIdempotencyKey(string $key): ?Reservation
    {
        $row = $this->reservations->first(fn (array $r): bool => $r['idempotency_key'] === $key);

        return $row === null ? null : Reservation::fromArray($row);
    }

    public function numberExists(string $number): bool
    {
        return $this->findByNumber($number) !== null;
    }

    public function findOverlapping(int $unitId, DateRange $range): array
    {
        $in = $range->checkIn->format('Y-m-d');
        $out = $range->checkOut->format('Y-m-d');

        return $this->map($this->reservations->select(
            fn (array $r): bool => $r['unit_id'] === $unitId
                && in_array($r['status'], self::ACTIVE, true)
                && $r['check_in'] < $out
                && $in < $r['check_out']
        ));
    }

    public function findByCustomer(int $customerId): array
    {
        return $this->map($this->reservations->select(fn (array $r): bool => $r['customer_id'] === $customerId));
    }

    public function findByUnits(array $unitIds): array
    {
        $set = array_flip($unitIds);

        return $this->map($this->reservations->select(fn (array $r): bool => isset($set[$r['unit_id']])));
    }

    public function findExpiredHolds(DateTimeImmutable $now, ?int $unitId = null): array
    {
        $limit = $now->format('Y-m-d H:i:s');

        return $this->map($this->reservations->select(
            fn (array $r): bool => $r['status'] === 'beklemede'
                && $r['expires_at'] !== null
                && $r['expires_at'] <= $limit
                && ($unitId === null || $r['unit_id'] === $unitId)
        ));
    }

    public function findConfirmedCheckedOutBy(DateTimeImmutable $date): array
    {
        $day = $date->format('Y-m-d');

        return $this->map($this->reservations->select(fn (array $r): bool => $r['status'] === 'onaylandi' && $r['check_out'] <= $day));
    }

    public function all(): array
    {
        return $this->map($this->reservations->select());
    }

    public function createWithNights(Reservation $reservation, array $charges, array $nights): Reservation
    {
        // Atomik yazma: önce bütün kısıtlar denetlenir, hiçbiri ihlal edilmiyorsa hepsi yazılır.
        if ($this->numberExists($reservation->number)) {
            throw new UniqueConstraintException('reservations', ['reservation_no']);
        }
        foreach ($nights as $night) {
            $day = $night->format('Y-m-d');
            if ($this->nights->first(fn (array $r): bool => $r['unit_id'] === $reservation->unitId && $r['night_date'] === $day) !== null) {
                throw new UniqueConstraintException('reservation_nights', ['unit_id', 'night_date']);
            }
        }
        $data = $reservation->toArray();
        $data['id'] = null;
        $saved = Reservation::fromArray($this->reservations->insert($data));
        $id = (int) $saved->id;
        foreach ($charges as $charge) {
            $row = $charge->toArray();
            $row['reservation_id'] = $id;
            $this->charges->insert($row);
        }
        foreach ($nights as $night) {
            $this->nights->insert((new ReservationNight($id, $saved->unitId, $night))->toArray());
        }

        return $saved;
    }

    public function updateStatusIf(int $id, ReservationStatus $expected, ReservationStatus $new, array $changes = []): bool
    {
        $values = ['status' => $new->value];
        foreach ($changes as $key => $value) {
            $values[$key] = match (true) {
                $value instanceof DateTimeImmutable => Row::fmtDateTime($value),
                $value instanceof \BackedEnum => $value->value,
                in_array($key, ['refund_amount', 'total_price'], true) && is_int($value) => Decimal::fromInt($value),
                default => $value,
            };
        }

        return $this->reservations->update(fn (array $r): bool => $r['id'] === $id && $r['status'] === $expected->value, $values) === 1;
    }

    public function releaseNights(int $reservationId): int
    {
        return $this->nights->delete(fn (array $r): bool => $r['reservation_id'] === $reservationId);
    }

    public function nightsOf(int $reservationId): array
    {
        return array_map(ReservationNight::fromArray(...), $this->nights->select(fn (array $r): bool => $r['reservation_id'] === $reservationId));
    }

    public function allNights(): array
    {
        return array_map(ReservationNight::fromArray(...), $this->nights->select());
    }

    public function chargesOf(int $reservationId): array
    {
        return array_map(ReservationCharge::fromArray(...), $this->charges->select(fn (array $r): bool => $r['reservation_id'] === $reservationId));
    }

    public function lockUnit(int $unitId): void
    {
        // Bellekte tek süreç çalışır; kilit gerekmez. Gerçek kilit eşzamanlılık testlerinde sınanır.
    }

    public function reportRows(?int $ownerId, DateRange $period): array
    {
        throw new LogicException('Rapor sorgusu ilan ve ödeme tablolarını birleştirir; ReportServiceTest JSON deposuyla sınar.');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<Reservation>
     */
    private function map(array $rows): array
    {
        return array_map(Reservation::fromArray(...), $rows);
    }
}
