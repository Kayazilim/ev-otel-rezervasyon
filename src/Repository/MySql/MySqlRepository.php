<?php

declare(strict_types=1);

namespace App\Repository\MySql;

/**
 * MySQL depoları için ortak yardımcılar. Satır ⇄ model dönüşümü modellerin
 * fromArray()/toArray() metotlarıyla yapılır; sütun adları JSON sürümüyle aynıdır.
 */
abstract class MySqlRepository
{
    public function __construct(protected readonly MySqlDatabase $db)
    {
    }

    /**
     * @template T
     * @param array<int|string, mixed> $params
     * @param callable(array<string, mixed>): T $map
     * @return T|null
     */
    protected function first(string $sql, array $params, callable $map): mixed
    {
        $row = $this->db->one($sql, $params);

        return $row === null ? null : $map($row);
    }

    /**
     * @template T
     * @param array<int|string, mixed> $params
     * @param callable(array<string, mixed>): T $map
     * @return list<T>
     */
    protected function many(string $sql, array $params, callable $map): array
    {
        return array_map($map, $this->db->select($sql, $params));
    }

    /**
     * Satırı ekler ve üretilen kimlikle birlikte döndürür.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function insertRow(string $table, array $row): array
    {
        $row['id'] = $this->db->insert($table, $row);

        return $row;
    }

    /**
     * "IN (?, ?, ?)" için yer tutucu listesi.
     *
     * @param array<mixed> $values
     */
    protected static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }
}
