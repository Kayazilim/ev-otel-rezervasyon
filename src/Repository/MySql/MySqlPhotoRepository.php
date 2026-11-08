<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Photo;
use App\Repository\PhotoRepositoryInterface;

final class MySqlPhotoRepository extends MySqlRepository implements PhotoRepositoryInterface
{
    public function findById(int $id): ?Photo
    {
        return $this->first('SELECT * FROM photos WHERE id = ?', [$id], Photo::fromArray(...));
    }

    public function findByProperty(int $propertyId): array
    {
        return $this->many('SELECT * FROM photos WHERE property_id = ? ORDER BY sort_order, id', [$propertyId], Photo::fromArray(...));
    }

    public function save(Photo $photo): Photo
    {
        if ($photo->id === null) {
            return Photo::fromArray($this->insertRow('photos', $photo->toArray()));
        }
        $this->db->updateById('photos', $photo->id, $photo->toArray());

        return $photo;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM photos WHERE id = ?', [$id]);
    }
}
