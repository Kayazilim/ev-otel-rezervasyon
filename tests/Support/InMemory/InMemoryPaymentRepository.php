<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Payment;
use App\Repository\PaymentRepositoryInterface;

/** Bellekte çalışan sahte ödeme deposu (simüle ödeme ve iade kayıtları). */
final class InMemoryPaymentRepository implements PaymentRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function findByReservation(int $reservationId): array
    {
        return array_map(Payment::fromArray(...), $this->table->select(fn (array $r): bool => $r['reservation_id'] === $reservationId));
    }

    public function all(): array
    {
        return array_map(Payment::fromArray(...), $this->table->select());
    }

    public function save(Payment $payment): Payment
    {
        if ($payment->id === null) {
            return Payment::fromArray($this->table->insert($payment->toArray()));
        }
        $id = $payment->id;
        $this->table->update(fn (array $r): bool => $r['id'] === $id, $payment->toArray());

        return $payment;
    }
}
