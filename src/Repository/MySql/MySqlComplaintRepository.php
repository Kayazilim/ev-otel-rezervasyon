<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Complaint;
use App\Repository\ComplaintRepositoryInterface;

final class MySqlComplaintRepository extends MySqlRepository implements ComplaintRepositoryInterface
{
    public function findById(int $id): ?Complaint
    {
        return $this->first('SELECT * FROM complaints WHERE id = ?', [$id], Complaint::fromArray(...));
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM complaints ORDER BY created_at DESC, id DESC', [], Complaint::fromArray(...));
    }

    public function save(Complaint $complaint): Complaint
    {
        if ($complaint->id === null) {
            return Complaint::fromArray($this->insertRow('complaints', $complaint->toArray()));
        }
        $this->db->updateById('complaints', $complaint->id, $complaint->toArray());

        return $complaint;
    }
}
