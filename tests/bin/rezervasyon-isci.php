<?php

declare(strict_types=1);

/*
 * Eşzamanlılık testlerinin işçi süreci (T-05, T-06, T-10).
 * Kullanım: php rezervasyon-isci.php <veri-dizini> <işlem> <json-parametreler>
 * Tüm işçiler aynı anda başlasın diye verilen zamana kadar bekler.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Container;
use App\Exception\BusyException;
use App\Exception\ConflictException;
use App\Service\Dto\CreateReservationRequest;
use App\Support\DateRange;
use App\Support\FrozenClock;
use App\Support\Settings;

[$script, $dataDir, $operation, $json] = $argv + [null, '', '', '{}'];
/** @var array<string, mixed> $params */
$params = json_decode((string) $json, true) ?: [];

$clock = new FrozenClock((string) ($params['now'] ?? '2026-11-01 10:00:00'));
$container = new Container(new Settings(dataDir: (string) $dataDir, logFile: ''), $clock);

$startAt = (float) ($params['start_at'] ?? 0);
while (microtime(true) < $startAt) {
    usleep(500);
}

try {
    if ($operation === 'rezervasyon') {
        $result = $container->reservationService()->create(new CreateReservationRequest(
            (int) $params['customer'],
            (int) $params['unit'],
            DateRange::fromStrings((string) $params['in'], (string) $params['out']),
            1,
            bin2hex(random_bytes(16)),
            (int) $params['total'],
            'Paralel Test',
            '0532 000 00 00',
            null,
        ));
        echo 'OK ' . $result->reservation->number;
    } elseif ($operation === 'yaz') {
        for ($i = 0; $i < (int) $params['count']; $i++) {
            $container->db()->insert('notifications', [
                'user_id' => (int) $params['user'],
                'type' => 'test',
                'message' => 'Süreç ' . getmypid() . ' #' . $i,
                'is_read' => false,
                'created_at' => '2026-11-01 10:00:00',
            ]);
        }
        echo 'OK';
    }
} catch (ConflictException) {
    echo 'CONFLICT';
} catch (BusyException) {
    echo 'BUSY';
} catch (Throwable $e) {
    echo 'ERROR ' . $e::class . ': ' . $e->getMessage();
}
