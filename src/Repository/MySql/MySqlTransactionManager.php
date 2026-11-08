<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Repository\TransactionManagerInterface;

/** Aşama 3: PDO transaction (BEGIN / COMMIT / ROLLBACK). Servisler JSON sürümüyle aynı arayüzü kullanır. */
final class MySqlTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly MySqlDatabase $db)
    {
    }

    public function transactional(callable $fn): mixed
    {
        return $this->db->transaction($fn);
    }
}
