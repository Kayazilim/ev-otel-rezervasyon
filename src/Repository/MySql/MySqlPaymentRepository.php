<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Payment;
use App\Repository\PaymentRepositoryInterface;

final class MySqlPaymentRepository extends MySqlRepository implements PaymentRepositoryInterface
{
    public function findByReservation(int $reservationId): array
    {
        return $this->many('SELECT * FROM payments WHERE reservation_id = ? ORDER BY id', [$reservationId], Payment::fromArray(...));
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM payments ORDER BY id', [], Payment::fromArray(...));
    }

    public function save(Payment $payment): Payment
    {
        if ($payment->id === null) {
            return Payment::fromArray($this->insertRow('payments', $payment->toArray()));
        }
        $this->db->updateById('payments', $payment->id, $payment->toArray());

        return $payment;
    }
}
