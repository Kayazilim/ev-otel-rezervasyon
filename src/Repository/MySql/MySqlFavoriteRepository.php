<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Favorite;
use App\Repository\FavoriteRepositoryInterface;

final class MySqlFavoriteRepository extends MySqlRepository implements FavoriteRepositoryInterface
{
    public function exists(int $userId, int $propertyId): bool
    {
        return $this->db->one('SELECT 1 AS bulundu FROM favorites WHERE user_id = ? AND property_id = ?', [$userId, $propertyId]) !== null;
    }

    public function add(Favorite $favorite): void
    {
        $this->db->insert('favorites', $favorite->toArray());
    }

    public function remove(int $userId, int $propertyId): void
    {
        $this->db->execute('DELETE FROM favorites WHERE user_id = ? AND property_id = ?', [$userId, $propertyId]);
    }

    public function findByUser(int $userId): array
    {
        return $this->many('SELECT * FROM favorites WHERE user_id = ? ORDER BY added_at DESC, property_id', [$userId], Favorite::fromArray(...));
    }
}
