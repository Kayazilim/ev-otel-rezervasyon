<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\User;
use App\Repository\UserRepositoryInterface;

final class MySqlUserRepository extends MySqlRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return $this->first('SELECT * FROM users WHERE id = ?', [$id], User::fromArray(...));
    }

    public function findByEmail(string $email): ?User
    {
        return $this->first('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))], User::fromArray(...));
    }

    public function save(User $user): User
    {
        if ($user->id === null) {
            return User::fromArray($this->insertRow('users', $user->toArray()));
        }
        $this->db->updateById('users', $user->id, $user->toArray());

        return $user;
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM users ORDER BY id', [], User::fromArray(...));
    }
}
