<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Exception\PersistenceException;
use App\Repository\DataSnapshotInterface;
use App\Repository\Json\JsonSchema;

/**
 * MySQL yedeği (NFR-09). Okuma REPEATABLE READ işlemde yapılır: tüm tablolar aynı ana ait tutarlı
 * bir görüntüden okunur ve yazanlar beklemez. Geri yükleme tek transaction'dır.
 */
final class MySqlDataSnapshot implements DataSnapshotInterface
{
    /** Satırlar JSON deposundakiyle aynı biçimde tutulur (TINYINT(1) -> bool). */
    private const BOOLEAN_COLUMNS = ['notifications' => ['is_read']];

    public function __construct(private readonly MySqlDatabase $db)
    {
    }

    public function exportTables(): array
    {
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); // yalnızca sıradaki işlem için

        return $this->db->transaction(function (): array {
            $tables = [];
            foreach (JsonSchema::tables() as $table => $definition) {
                $order = implode(', ', array_map(fn (string $c): string => '`' . $c . '`', $definition['pk']));
                $rows = $this->db->select('SELECT * FROM `' . $table . '` ORDER BY ' . $order);
                foreach (self::BOOLEAN_COLUMNS[$table] ?? [] as $column) {
                    foreach ($rows as $i => $row) {
                        $rows[$i][$column] = (bool) $row[$column];
                    }
                }
                $tables[$table] = $rows;
            }

            return $tables;
        });
    }

    public function importTables(array $tables): void
    {
        $known = JsonSchema::tables();
        $unknown = array_diff(array_keys($tables), array_keys($known));
        if ($unknown !== []) {
            throw new PersistenceException('Yedekte bilinmeyen tablo var: ' . implode(', ', $unknown));
        }
        $this->db->transaction(function () use ($tables, $known): void {
            // Satırlar yabancı anahtar sırasından bağımsız yüklenebilsin diye denetim işlem süresince kapatılır.
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach (array_keys($known) as $table) {
                    $this->db->execute('DELETE FROM `' . $table . '`');
                }
                foreach ($tables as $table => $rows) {
                    foreach ($rows as $row) {
                        $this->db->insert($table, $row);
                    }
                }
            } finally {
                $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        });
    }
}
