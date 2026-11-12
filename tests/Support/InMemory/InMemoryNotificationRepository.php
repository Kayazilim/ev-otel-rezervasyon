<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\Notification;
use App\Repository\NotificationRepositoryInterface;

/** Bellekte çalışan sahte bildirim deposu. */
final class InMemoryNotificationRepository implements NotificationRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function save(Notification $notification): Notification
    {
        if ($notification->id === null) {
            return Notification::fromArray($this->table->insert($notification->toArray()));
        }
        $id = $notification->id;
        $this->table->update(fn (array $r): bool => $r['id'] === $id, $notification->toArray());

        return $notification;
    }

    public function findByUser(int $userId): array
    {
        $list = array_map(Notification::fromArray(...), $this->table->select(fn (array $r): bool => $r['user_id'] === $userId));
        usort($list, fn (Notification $a, Notification $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return $list;
    }

    public function unreadCount(int $userId): int
    {
        return count($this->table->select(fn (array $r): bool => $r['user_id'] === $userId && $r['is_read'] === false));
    }

    public function markAllRead(int $userId): int
    {
        return $this->table->update(fn (array $r): bool => $r['user_id'] === $userId && $r['is_read'] === false, ['is_read' => true]);
    }
}
