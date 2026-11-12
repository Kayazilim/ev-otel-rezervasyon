<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Model\CalendarEntry;
use App\Repository\CalendarRepositoryInterface;
use DateTimeImmutable;

/** Bellekte çalışan sahte takvim deposu (kapalı geceler ve gece fiyatı istisnaları). */
final class InMemoryCalendarRepository implements CalendarRepositoryInterface
{
    private MemoryTable $table;

    public function __construct()
    {
        $this->table = new MemoryTable();
    }

    public function findForUnit(int $unitId, DateTimeImmutable $from, DateTimeImmutable $toExclusive): array
    {
        $start = $from->format('Y-m-d');
        $end = $toExclusive->format('Y-m-d');
        $entries = [];
        foreach ($this->table->select(fn (array $r): bool => $r['unit_id'] === $unitId && $r['date'] >= $start && $r['date'] < $end) as $row) {
            $entry = CalendarEntry::fromArray($row);
            $entries[$entry->date->format('Y-m-d')] = $entry;
        }

        return $entries;
    }

    public function upsert(CalendarEntry $entry): CalendarEntry
    {
        // Aynı birim ve gün için tek kayıt (JSON deposundaki "varsa güncelle, yoksa ekle" davranışı).
        $this->deleteForUnitDate($entry->unitId, $entry->date);
        $data = $entry->toArray();
        $data['id'] = null;

        return CalendarEntry::fromArray($this->table->insert($data));
    }

    public function deleteForUnitDate(int $unitId, DateTimeImmutable $date): void
    {
        $day = $date->format('Y-m-d');
        $this->table->delete(fn (array $r): bool => $r['unit_id'] === $unitId && $r['date'] === $day);
    }
}
