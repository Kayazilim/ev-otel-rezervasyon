<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\PasswordResetToken;
use App\Repository\PasswordResetTokenRepositoryInterface;
use DateTimeImmutable;

final class MySqlPasswordResetTokenRepository extends MySqlRepository implements PasswordResetTokenRepositoryInterface
{
    public function save(PasswordResetToken $token): PasswordResetToken
    {
        return PasswordResetToken::fromArray($this->insertRow('password_reset_tokens', $token->toArray()));
    }

    public function findByHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->first('SELECT * FROM password_reset_tokens WHERE token_hash = ?', [$tokenHash], PasswordResetToken::fromArray(...));
    }

    public function markUsedIfUnused(int $id, DateTimeImmutable $usedAt): bool
    {
        // Koşullu güncelleme: iki sekmeden aynı jetonla gelen ikinci istek 0 satır etkiler (tek kullanımlık).
        return $this->db->execute('UPDATE password_reset_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL', [$usedAt, $id]) === 1;
    }

    public function invalidateForUser(int $userId, DateTimeImmutable $at): void
    {
        $this->db->execute('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [$at, $userId]);
    }
}
