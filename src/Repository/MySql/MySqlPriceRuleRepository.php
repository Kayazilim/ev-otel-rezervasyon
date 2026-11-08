<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\PriceRule;
use App\Repository\PriceRuleRepositoryInterface;

final class MySqlPriceRuleRepository extends MySqlRepository implements PriceRuleRepositoryInterface
{
    public function findById(int $id): ?PriceRule
    {
        return $this->first('SELECT * FROM price_rules WHERE id = ?', [$id], PriceRule::fromArray(...));
    }

    public function findByProperty(int $propertyId): array
    {
        return $this->many('SELECT * FROM price_rules WHERE property_id = ? ORDER BY id', [$propertyId], PriceRule::fromArray(...));
    }

    public function save(PriceRule $rule): PriceRule
    {
        if ($rule->id === null) {
            return PriceRule::fromArray($this->insertRow('price_rules', $rule->toArray()));
        }
        $this->db->updateById('price_rules', $rule->id, $rule->toArray());

        return $rule;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM price_rules WHERE id = ?', [$id]);
    }
}
