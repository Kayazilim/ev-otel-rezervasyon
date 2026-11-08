<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\BookableUnit;
use App\Repository\BookableUnitRepositoryInterface;

final class MySqlBookableUnitRepository extends MySqlRepository implements BookableUnitRepositoryInterface
{
    public function findById(int $id): ?BookableUnit
    {
        return $this->first('SELECT * FROM bookable_units WHERE id = ?', [$id], BookableUnit::fromArray(...));
    }

    public function findByProperty(int $propertyId): array
    {
        return $this->many('SELECT * FROM bookable_units WHERE property_id = ? ORDER BY id', [$propertyId], BookableUnit::fromArray(...));
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM bookable_units ORDER BY id', [], BookableUnit::fromArray(...));
    }

    public function save(BookableUnit $unit): BookableUnit
    {
        if ($unit->id === null) {
            return BookableUnit::fromArray($this->insertRow('bookable_units', $unit->toArray()));
        }
        $this->db->updateById('bookable_units', $unit->id, $unit->toArray());

        return $unit;
    }
}
