<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\AuditLog;
use App\Repository\AuditLogRepositoryInterface;

final class MySqlAuditLogRepository extends MySqlRepository implements AuditLogRepositoryInterface
{
    public function add(AuditLog $log): AuditLog
    {
        return AuditLog::fromArray($this->insertRow('audit_logs', $log->toArray()));
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC', [], AuditLog::fromArray(...));
    }
}
