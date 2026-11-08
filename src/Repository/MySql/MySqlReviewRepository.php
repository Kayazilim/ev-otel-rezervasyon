<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Model\ModerationStatus;
use App\Model\Review;
use App\Repository\ReviewRepositoryInterface;

final class MySqlReviewRepository extends MySqlRepository implements ReviewRepositoryInterface
{
    public function findById(int $id): ?Review
    {
        return $this->first('SELECT * FROM reviews WHERE id = ?', [$id], Review::fromArray(...));
    }

    public function findByReservation(int $reservationId): ?Review
    {
        return $this->first('SELECT * FROM reviews WHERE reservation_id = ?', [$reservationId], Review::fromArray(...));
    }

    public function findForProperty(int $propertyId, bool $publishedOnly = true): array
    {
        // 3NF: yorumun ilanı ayrı sütunda değil; reviews ⋈ reservations ⋈ bookable_units ile bulunur.
        $sql = 'SELECT rv.* FROM reviews rv
                JOIN reservations r ON r.id = rv.reservation_id
                JOIN bookable_units u ON u.id = r.unit_id
                WHERE u.property_id = ?';
        $params = [$propertyId];
        if ($publishedOnly) {
            $sql .= ' AND rv.moderation_status = ?';
            $params[] = ModerationStatus::Yayinda->value;
        }

        return $this->many($sql . ' ORDER BY rv.created_at DESC, rv.id', $params, Review::fromArray(...));
    }

    public function ratingSummaries(): array
    {
        $rows = $this->db->select(
            'SELECT u.property_id, AVG(rv.rating) AS avg_rating, COUNT(*) AS review_count
             FROM reviews rv
             JOIN reservations r ON r.id = rv.reservation_id
             JOIN bookable_units u ON u.id = r.unit_id
             WHERE rv.moderation_status = ?
             GROUP BY u.property_id',
            [ModerationStatus::Yayinda->value]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['property_id']] = ['avg' => round((float) $row['avg_rating'], 1), 'count' => (int) $row['review_count']];
        }

        return $result;
    }

    public function all(): array
    {
        return $this->many('SELECT * FROM reviews ORDER BY created_at DESC, id', [], Review::fromArray(...));
    }

    public function save(Review $review): Review
    {
        if ($review->id === null) {
            return Review::fromArray($this->insertRow('reviews', $review->toArray()));
        }
        $this->db->updateById('reviews', $review->id, $review->toArray());

        return $review;
    }
}
