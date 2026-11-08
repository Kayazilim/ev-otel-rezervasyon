<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\LoginAttempt;
use App\Repository\LoginAttemptRepositoryInterface;
use App\Support\Row;

final class MySqlLoginAttemptRepository extends MySqlRepository implements LoginAttemptRepositoryInterface
{
    public function find(string $email): ?LoginAttempt
    {
        return $this->first('SELECT * FROM login_attempts WHERE email = ?', [$email], LoginAttempt::fromArray(...));
    }

    public function save(LoginAttempt $attempt): void
    {
        // Tek komutta ekle-veya-güncelle: aynı e-postaya eşzamanlı hatalı girişlerde de tek satır kalır.
        $values = [$attempt->failedCount, Row::fmtDateTime($attempt->lastFailedAt), Row::fmtDateTime($attempt->lockedUntil)];
        $this->db->execute(
            'INSERT INTO login_attempts (email, failed_count, last_failed_at, locked_until) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE failed_count = ?, last_failed_at = ?, locked_until = ?',
            [$attempt->email, ...$values, ...$values]
        );
    }

    public function delete(string $email): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE email = ?', [$email]);
    }
}
