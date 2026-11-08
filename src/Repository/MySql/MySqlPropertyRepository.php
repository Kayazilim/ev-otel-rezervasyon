<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Property;
use App\Model\PropertyStatus;
use App\Repository\PropertyRepositoryInterface;

final class MySqlPropertyRepository extends MySqlRepository implements PropertyRepositoryInterface
{
    public function findById(int $id): ?Property
    {
        return $this->first('SELECT * FROM properties WHERE id = ?', [$id], Property::fromArray(...));
    }

    public function findByOwner(int $ownerId): array
    {
        return $this->many('SELECT * FROM properties WHERE owner_id = ? ORDER BY id', [$ownerId], Property::fromArray(...));
    }

    public function findByStatus(PropertyStatus $status): array
    {
        return $this->many('SELECT * FROM properties WHERE status = ? ORDER BY id', [$status->value], Property::fromArray(...));
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM properties ORDER BY id', [], Property::fromArray(...));
    }

    public function save(Property $property): Property
    {
        if ($property->id === null) {
            return Property::fromArray($this->insertRow('properties', $property->toArray()));
        }
        $this->db->updateById('properties', $property->id, $property->toArray());

        return $property;
    }

    public function amenityIdsOf(int $propertyId): array
    {
        $ids = [];
        foreach ($this->db->select('SELECT amenity_id FROM property_amenities WHERE property_id = ? ORDER BY amenity_id', [$propertyId]) as $row) {
            $ids[] = (int) $row['amenity_id'];
        }

        return $ids;
    }

    public function setAmenities(int $propertyId, array $amenityIds): void
    {
        $this->db->transaction(function () use ($propertyId, $amenityIds): void {
            $this->db->execute('DELETE FROM property_amenities WHERE property_id = ?', [$propertyId]);
            foreach (array_unique($amenityIds) as $amenityId) {
                $this->db->insert('property_amenities', ['property_id' => $propertyId, 'amenity_id' => $amenityId]);
            }
        });
    }
}
