<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Model\BookableUnit;
use App\Model\ReservationStatus;
use App\Model\Role;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AppTestCase;

/**
 * Gerçek paralel süreçlerle eşzamanlılık (SRS 4.5, Aşama 2 dosya kilidi):
 *  T-05: 50 paralel istek, aynı birim ve aralık -> tam 1 başarı, 49 çakışma, gece sayısı = gece sayısı
 *  T-06: kısmen kesişen istekler -> tek başarı; arka arkaya istekler -> iki başarı
 *  T-10: iki süreç aynı JSON dosyasına eşzamanlı yazar -> dosya bozulmaz, çift kayıt olmaz
 */
#[Group('eszamanlilik')]
final class ParallelReservationTest extends AppTestCase
{
    private int $customerId;
    private BookableUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $host = $this->user(Role::IlanSahibi);
        $this->customerId = (int) $this->user()->id;
        $this->unit = $this->unit((int) $this->property((int) $host->id)->id, 1000, 2);
    }

    /**
     * @param list<array<string, mixed>> $jobs
     * @return list<string> her sürecin çıktısı
     */
    private function runParallel(string $operation, array $jobs): array
    {
        $worker = dirname(__DIR__) . '/bin/rezervasyon-isci.php';
        $startAt = microtime(true) + 1.5 + count($jobs) * 0.03; // tüm süreçler başlasın, sonra aynı anda yarışsınlar
        $processes = [];
        foreach ($jobs as $i => $params) {
            $params['start_at'] = $startAt;
            $params['now'] = '2026-11-01 10:00:00';
            $command = [PHP_BINARY, $worker, $this->dir . '/data', $operation, (string) json_encode($params)];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $processes[$i] = [$process, $pipes];
        }
        $outputs = [];
        foreach ($processes as [$process, $pipes]) {
            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $outputs[] = trim($out . ($err !== '' ? ' STDERR: ' . $err : ''));
        }

        return $outputs;
    }

    private function total(string $in, string $out): int
    {
        return $this->c->pricingService()->quote($this->unit, $this->range($in, $out))->total;
    }

    public function testT05FiftyParallelRequestsCreateExactlyOneReservation(): void
    {
        $job = ['customer' => $this->customerId, 'unit' => $this->unit->id, 'in' => '2026-11-10', 'out' => '2026-11-13', 'total' => $this->total('2026-11-10', '2026-11-13')];
        $outputs = $this->runParallel('rezervasyon', array_fill(0, 50, $job));

        $ok = array_filter($outputs, fn (string $o): bool => str_starts_with($o, 'OK'));
        $conflicts = array_filter($outputs, fn (string $o): bool => $o === 'CONFLICT');
        self::assertCount(1, $ok, implode("\n", array_unique($outputs)));
        self::assertCount(49, $conflicts, implode("\n", array_unique($outputs)));

        $reservations = $this->c->reservations()->all();
        self::assertCount(1, $reservations);
        self::assertSame(ReservationStatus::Beklemede, $reservations[0]->status);
        self::assertCount(3, $this->c->reservations()->allNights(), 'Gece kaydı sayısı = gece sayısı (AC-13)');
        self::assertSame([], $this->c->consistencyAuditService()->violations(), 'T-11');
    }

    public function testT06PartialOverlapOneWinsBackToBackBothWin(): void
    {
        $partial = $this->runParallel('rezervasyon', [
            ['customer' => $this->customerId, 'unit' => $this->unit->id, 'in' => '2026-11-10', 'out' => '2026-11-12', 'total' => $this->total('2026-11-10', '2026-11-12')],
            ['customer' => $this->customerId, 'unit' => $this->unit->id, 'in' => '2026-11-11', 'out' => '2026-11-13', 'total' => $this->total('2026-11-11', '2026-11-13')],
        ]);
        self::assertCount(1, array_filter($partial, fn (string $o): bool => str_starts_with($o, 'OK')), implode("\n", $partial));

        $backToBack = $this->runParallel('rezervasyon', [
            ['customer' => $this->customerId, 'unit' => $this->unit->id, 'in' => '2026-11-20', 'out' => '2026-11-22', 'total' => $this->total('2026-11-20', '2026-11-22')],
            ['customer' => $this->customerId, 'unit' => $this->unit->id, 'in' => '2026-11-22', 'out' => '2026-11-24', 'total' => $this->total('2026-11-22', '2026-11-24')],
        ]);
        self::assertCount(2, array_filter($backToBack, fn (string $o): bool => str_starts_with($o, 'OK')), implode("\n", $backToBack));
        self::assertSame([], $this->c->consistencyAuditService()->violations());
    }

    public function testT10ConcurrentWritesDoNotCorruptJsonFiles(): void
    {
        $outputs = $this->runParallel('yaz', [
            ['user' => $this->customerId, 'count' => 40],
            ['user' => $this->customerId, 'count' => 40],
        ]);
        self::assertSame(['OK', 'OK'], $outputs);

        $json = (string) file_get_contents($this->dir . '/data/notifications.json');
        self::assertJson($json, 'Dosya bozulmadı');
        $rows = $this->c->db()->select('notifications');
        self::assertCount(80, $rows, 'Hiçbir yazma kaybolmadı');
        $ids = array_column($rows, 'id');
        self::assertSame($ids, array_unique($ids), 'Çift kimlik yok');
    }
}
