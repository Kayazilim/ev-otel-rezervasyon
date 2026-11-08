<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Amenity;
use App\Repository\AmenityRepositoryInterface;

final class MySqlAmenityRepository extends MySqlRepository implements AmenityRepositoryInterface
{
    public function all(): array
    {
        return $this->many('SELECT * FROM amenities ORDER BY id', [], Amenity::fromArray(...));
    }

    public function save(Amenity $amenity): Amenity
    {
        return Amenity::fromArray($this->insertRow('amenities', $amenity->toArray()));
    }
}
