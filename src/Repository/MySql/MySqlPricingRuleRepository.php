<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\PricingRule;
use App\Repository\PricingRuleRepositoryInterface;

final class MySqlPricingRuleRepository extends MySqlRepository implements PricingRuleRepositoryInterface
{
    public function findById(int $id): ?PricingRule
    {
        return $this->first('SELECT * FROM pricing_rules WHERE id = ?', [$id], PricingRule::fromArray(...));
    }

    public function findByProperty(int $propertyId): array
    {
        return $this->many('SELECT * FROM pricing_rules WHERE property_id = ? ORDER BY id', [$propertyId], PricingRule::fromArray(...));
    }

    public function save(PricingRule $rule): PricingRule
    {
        if ($rule->id === null) {
            return PricingRule::fromArray($this->insertRow('pricing_rules', $rule->toArray()));
        }
        $this->db->updateById('pricing_rules', $rule->id, $rule->toArray());

        return $rule;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM pricing_rules WHERE id = ?', [$id]);
    }
}
