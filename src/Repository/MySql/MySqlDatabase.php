<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Exception\BusyException;
use App\Exception\PersistenceException;
use App\Exception\StorageUnavailableException;
use App\Exception\UniqueConstraintException;
use BackedEnum;
use DateTimeImmutable;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Aşama 3 veri erişimi: PDO + MySQL (InnoDB).
 *
 *  - Tüm sorgular hazırlıklı ifadelerle (prepared statements) çalışır; kullanıcı verisi SQL metnine eklenmez.
 *  - transaction(): BEGIN ... COMMIT; hata olursa ROLLBACK (FR-33).
 *  - Kilit bekleme 5 sn ile sınırlıdır (NFR-13). Kilit zaman aşımı veya kilitlenmede (deadlock) işlem
 *    en çok 2 kez yeniden denenir, sonra BusyException ("yoğunluk") atılır (SRS 4.5).
 *  - Yalıtım düzeyi READ COMMITTED: birim satırı kilitlendikten sonra yapılan okumalar,
 *    kilidi bırakan işlemin yazdıklarını görür.
 *  - UNIQUE ihlali (MySQL 1062) UniqueConstraintException'a çevrilir: çift rezervasyona karşı son savunma.
 */
class MySqlDatabase
{
    private const LOCK_ERRORS = [1205, 1213]; // kilit bekleme zaman aşımı, kilitlenme

    private ?PDO $pdo = null;
    private int $depth = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $name,
        private readonly string $user,
        private readonly string $password,
        private readonly int $lockWaitSeconds = 5,
        private readonly int $retries = 2,
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect($this->name);
            $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . max(1, $this->lockWaitSeconds));
            $this->pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $this->pdo->exec("SET time_zone = '" . date('P') . "'"); // uygulama saat dilimiyle aynı (NFR-17)
        }

        return $this->pdo;
    }

    /** Veritabanı yoksa oluşturur (ilk kurulum: bin/migrate.php). */
    public function createDatabaseIfMissing(): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->name)) {
            throw new PersistenceException('Geçersiz veritabanı adı.');
        }
        $this->connect(null)->exec('CREATE DATABASE IF NOT EXISTS `' . $this->name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function hasTable(string $table): bool
    {
        return $this->one('SELECT 1 AS bulundu FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) !== null;
    }

    public function inTransaction(): bool
    {
        return $this->depth > 0;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Etkilenen satır sayısını döndürür (koşullu güncellemede kullanılır).
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** Parametresiz yapı komutları (migration: CREATE / DROP / TRUNCATE). */
    public function exec(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    /**
     * Model satırını ekler; otomatik artan kimliği döndürür. Sütun adları kod içinden gelir, değerler bağlanır.
     *
     * @param array<string, mixed> $row
     */
    public function insert(string $table, array $row): int
    {
        if (array_key_exists('id', $row) && $row['id'] === null) {
            unset($row['id']);
        }
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn (string $c): string => '`' . $c . '`', $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        $this->run($sql, array_values($row));

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $row */
    public function updateById(string $table, int $id, array $row): int
    {
        unset($row['id']);
        $sets = implode(', ', array_map(fn (string $c): string => '`' . $c . '` = ?', array_keys($row)));
        $params = array_values($row);
        $params[] = $id;

        return $this->execute(sprintf('UPDATE `%s` SET %s WHERE id = ?', $table, $sets), $params);
    }

    /**
     * Atomik işlem. İç içe çağrılar dıştaki işlemin parçasıdır.
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $fn();
            } finally {
                $this->depth--;
            }
        }

        for ($attempt = 0;; $attempt++) {
            $this->pdo()->beginTransaction();
            $this->depth = 1;
            try {
                $result = $fn();
                $this->pdo()->commit();

                return $result;
            } catch (Throwable $e) {
                if ($this->pdo()->inTransaction()) {
                    $this->pdo()->rollBack();
                }
                if (!$this->isLockError($e)) {
                    throw $e;
                }
                if ($attempt >= $this->retries) {
                    throw new BusyException();
                }
                usleep(random_int(20000, 80000));
            } finally {
                $this->depth = 0;
            }
        }
    }

    /** @param array<int|string, mixed> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute(array_map($this->value(...), $params));

            return $statement;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                preg_match("/for key '([^']+)'/", $e->getMessage(), $m);
                $key = $m[1] ?? 'unique';
                [$table, $index] = str_contains($key, '.') ? explode('.', $key, 2) : ['?', $key];
                throw new UniqueConstraintException($table, [$index]);
            }
            throw $e;
        }
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => (int) $value,
            $value instanceof DateTimeImmutable => $value->format('Y-m-d H:i:s'),
            $value instanceof BackedEnum => $value->value,
            default => $value,
        };
    }

    private function isLockError(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof PDOException && in_array((int) ($current->errorInfo[1] ?? 0), self::LOCK_ERRORS, true)) {
                return true;
            }
        }

        return false;
    }

    private function connect(?string $database): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $this->host, $this->port) . ($database !== null ? ';dbname=' . $database : '');
        try {
            return new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // gerçek hazırlıklı ifadeler
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            // Bağlantı ayrıntısı (parola vb.) kullanıcıya gösterilmez
            throw new StorageUnavailableException(
                'Veritabanına bağlanılamadı (MySQL çalışıyor mu? EVOTEL_DB_* ayarlarını kontrol edin). Hata kodu: ' . $e->getCode()
            );
        }
    }
}
