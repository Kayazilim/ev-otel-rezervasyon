<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\Notification;
use App\Repository\NotificationRepositoryInterface;

final class MySqlNotificationRepository extends MySqlRepository implements NotificationRepositoryInterface
{
    public function save(Notification $notification): Notification
    {
        if ($notification->id === null) {
            return Notification::fromArray($this->insertRow('notifications', $notification->toArray()));
        }
        $this->db->updateById('notifications', $notification->id, $notification->toArray());

        return $notification;
    }

    public function findByUser(int $userId): array
    {
        return $this->many('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$userId], Notification::fromArray(...));
    }

    public function unreadCount(int $userId): int
    {
        $row = $this->db->one('SELECT COUNT(*) AS adet FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);

        return (int) ($row['adet'] ?? 0);
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }
}
