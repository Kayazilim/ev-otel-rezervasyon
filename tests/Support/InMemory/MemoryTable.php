<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

/**
 * Bellekte çalışan tablo: JsonDatabase'in ekle / seç / güncelle / sil davranışını dosya, kilit ve
 * geçici dosya olmadan taklit eder. Satırlar modellerin toArray() biçiminde saklanır; böylece sahte
 * depolar da gerçek depo gibi "kaydet → oku" dönüşümünden geçer (tarih biçimi, enum değeri, para).
 */
final class MemoryTable
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    private int $nextId = 1;

    /**
     * @param bool $autoId Kimlik sütunu var mı? (gece tablosunda yoktur; anahtar birim + gecedir)
     */
    public function __construct(private readonly bool $autoId = true)
    {
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function insert(array $row): array
    {
        $key = $this->nextId++;
        if ($this->autoId) {
            $row['id'] = $key;
        }
        $this->rows[$key] = $row;

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function select(?callable $where = null): array
    {
        return array_values($where === null ? $this->rows : array_filter($this->rows, $where));
    }

    /** @return array<string, mixed>|null */
    public function first(callable $where): ?array
    {
        foreach ($this->rows as $row) {
            if ($where($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $values
     * @return int Etkilenen satır sayısı (koşullu güncellemenin sonucu buna bakılarak verilir)
     */
    public function update(callable $where, array $values): int
    {
        $count = 0;
        foreach ($this->rows as $key => $row) {
            if ($where($row)) {
                $this->rows[$key] = array_replace($row, $values);
                $count++;
            }
        }

        return $count;
    }

    public function delete(callable $where): int
    {
        $count = 0;
        foreach ($this->rows as $key => $row) {
            if ($where($row)) {
                unset($this->rows[$key]);
                $count++;
            }
        }

        return $count;
    }
}
