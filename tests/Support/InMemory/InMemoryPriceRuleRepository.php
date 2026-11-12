<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\PriceRule;
use App\Repository\PriceRuleRepositoryInterface;

/** Bellekte çalışan sahte fiyat kuralı deposu (sezon, hafta sonu, uzun konaklama). */
final class InMemoryPriceRuleRepository implements PriceRuleRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function findById(int $id): ?PriceRule
    {
        $row = $this->table->first(fn (array $r): bool => $r['id'] === $id);

        return $row === null ? null : PriceRule::fromArray($row);
    }

    public function findByProperty(int $propertyId): array
    {
        return array_map(PriceRule::fromArray(...), $this->table->select(fn (array $r): bool => $r['property_id'] === $propertyId));
    }

    public function save(PriceRule $rule): PriceRule
    {
        if ($rule->id === null) {
            return PriceRule::fromArray($this->table->insert($rule->toArray()));
        }
        $id = $rule->id;
        $this->table->update(fn (array $r): bool => $r['id'] === $id, $rule->toArray());

        return $rule;
    }

    public function delete(int $id): void
    {
        $this->table->delete(fn (array $r): bool => $r['id'] === $id);
    }
}
