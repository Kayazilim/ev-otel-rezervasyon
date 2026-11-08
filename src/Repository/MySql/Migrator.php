<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Exception\PersistenceException;
use PDOException;

/**
 * SQL migration çalıştırıcısı: database/migrations/*.sql dosyalarını ad sırasıyla bir kez uygular
 * ve uygulananları schema_migrations tablosuna yazar (bin/migrate.php).
 */
final class Migrator
{
    public function __construct(private readonly MySqlDatabase $db, private readonly string $directory)
    {
    }

    /** @return list<string> bu çalıştırmada uygulanan dosyalar */
    public function migrate(): array
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
        $applied = array_column($this->db->select('SELECT name FROM schema_migrations'), 'name');
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);
        $done = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            foreach ($this->statements((string) file_get_contents($file)) as $statement) {
                $this->db->exec($statement);
            }
            $this->db->execute('INSERT INTO schema_migrations (name, applied_at) VALUES (?, NOW())', [$name]);
            $done[] = $name;
        }

        return $done;
    }

    /**
     * Tüm tabloları siler ve migration'ları baştan uygular.
     *
     * @return list<string>
     */
    public function fresh(): array
    {
        $this->withoutForeignKeys(function (): void {
            foreach ($this->tables(true) as $table) {
                $this->db->exec('DROP TABLE IF EXISTS `' . $table . '`');
            }
        });

        return $this->migrate();
    }

    /**
     * Şemayı koruyup verileri siler ve kimlik sayaçlarını 1'e çeker (örnek veri yüklemeden önce ve her test başında).
     * Hız için yalnızca dolu ya da sayacı ilerlemiş tablolara dokunulur (TRUNCATE, tablo dosyasını yeniden oluşturduğu için yavaştır).
     */
    public function truncateAll(): void
    {
        $tables = $this->tables(false);
        if ($tables === []) {
            return;
        }
        $exists = array_map(fn (string $t): string => "SELECT '" . $t . "' AS tablo FROM DUAL WHERE EXISTS (SELECT 1 FROM `" . $t . '`)', $tables);
        $nonEmpty = array_column($this->db->select(implode(' UNION ALL ', $exists)), 'tablo');
        $advanced = $this->tablesWithAdvancedCounter();

        $this->withoutForeignKeys(function () use ($tables, $nonEmpty, $advanced): void {
            foreach ($tables as $table) {
                if (in_array($table, $nonEmpty, true)) {
                    $this->db->exec('DELETE FROM `' . $table . '`');
                }
                if (in_array($table, $advanced, true)) {
                    $this->db->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
                }
            }
        });
    }

    /** @return list<string> AUTO_INCREMENT sayacı 1'den büyük olan tablolar */
    private function tablesWithAdvancedCounter(): array
    {
        try {
            // MySQL 8 information_schema istatistiklerini önbellekler; anlık değer için önbellek kapatılır (MariaDB'de değişken yoktur, zaten anlıktır).
            $this->db->exec('SET SESSION information_schema_stats_expiry = 0');
        } catch (PDOException) {
        }
        $rows = $this->db->select(
            'SELECT TABLE_NAME AS tablo FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND AUTO_INCREMENT > 1'
        );

        return array_values(array_filter(array_map(fn (array $r): string => (string) $r['tablo'], $rows), fn (string $t): bool => $t !== 'schema_migrations'));
    }

    /** @return list<string> */
    private function tables(bool $includeMigrations): array
    {
        $tables = [];
        foreach ($this->db->select('SHOW TABLES') as $row) {
            $name = (string) array_values($row)[0];
            if (!preg_match('/^[a-z_]+$/', $name)) {
                throw new PersistenceException('Beklenmeyen tablo adı: ' . $name);
            }
            if ($includeMigrations || $name !== 'schema_migrations') {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    private function withoutForeignKeys(callable $fn): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $fn();
        } finally {
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * SQL dosyasını komutlara böler (satır sonundaki ';' ile); "--" ile başlayan satırlar açıklamadır.
     *
     * @return list<string>
     */
    private function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );
        $parts = preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $s): bool => $s !== ''));
    }
}
