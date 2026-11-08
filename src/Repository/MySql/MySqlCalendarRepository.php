<?php

declare(strict_types=1);

namespace App\Repository\MySql;

use App\Exception\PersistenceException;
use App\Model\CalendarEntry;
use App\Repository\CalendarRepositoryInterface;
use App\Support\Row;
use DateTimeImmutable;

final class MySqlCalendarRepository extends MySqlRepository implements CalendarRepositoryInterface
{
    public function findForUnit(int $unitId, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $entries = [];
        $rows = $this->db->select(
            'SELECT * FROM calendar_entries WHERE unit_id = ? AND `date` >= ? AND `date` < ? ORDER BY `date`',
            [$unitId, $from->format('Y-m-d'), $toExclusive->format('Y-m-d')]
        );
        foreach ($rows as $row) {
            $entry = CalendarEntry::fromArray($row);
            $entries[$entry->date->format('Y-m-d')] = $entry;
        }

        return $entries;
    }

    public function upsert(CalendarEntry $entry): CalendarEntry
    {
        // UNIQUE(unit_id, date) sayesinde tek komutta ekle-veya-güncelle
        $date = $entry->date->format('Y-m-d');
        $price = Row::fmtDecimal($entry->specialPrice);
        $this->db->execute(
            'INSERT INTO calendar_entries (unit_id, `date`, status, special_price) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = ?, special_price = ?',
            [$entry->unitId, $date, $entry->status->value, $price, $entry->status->value, $price]
        );

        return $this->first('SELECT * FROM calendar_entries WHERE unit_id = ? AND `date` = ?', [$entry->unitId, $date], CalendarEntry::fromArray(...))
            ?? throw new PersistenceException('Takvim kaydı okunamadı.');
    }

    public function deleteForUnitDate(int $unitId, DateTimeImmutable $date): void
    {
        $this->db->execute('DELETE FROM calendar_entries WHERE unit_id = ? AND `date` = ?', [$unitId, $date->format('Y-m-d')]);
    }
}
