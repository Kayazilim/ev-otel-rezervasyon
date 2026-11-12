<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\AuditLog;
use App\Repository\AuditLogRepositoryInterface;

/** Bellekte çalışan sahte denetim kaydı deposu. */
final class InMemoryAuditLogRepository implements AuditLogRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function add(AuditLog $log): AuditLog
    {
        return AuditLog::fromArray($this->table->insert($log->toArray()));
    }

    public function all(): array
    {
        $list = array_map(AuditLog::fromArray(...), $this->table->select());
        usort($list, fn (AuditLog $a, AuditLog $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return $list;
    }
}
