<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\PricingRule;
use App\Repository\PricingRuleRepositoryInterface;

/** Bellekte çalışan sahte vergi / ek ücret kuralı deposu. */
final class InMemoryPricingRuleRepository implements PricingRuleRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function findById(int $id): ?PricingRule
    {
        $row = $this->table->first(fn (array $r): bool => $r['id'] === $id);

        return $row === null ? null : PricingRule::fromArray($row);
    }

    public function findByProperty(int $propertyId): array
    {
        return array_map(PricingRule::fromArray(...), $this->table->select(fn (array $r): bool => $r['property_id'] === $propertyId));
    }

    public function save(PricingRule $rule): PricingRule
    {
        if ($rule->id === null) {
            return PricingRule::fromArray($this->table->insert($rule->toArray()));
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
