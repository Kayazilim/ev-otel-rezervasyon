<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Settings;
use RuntimeException;

/**
 * Test ayarları. Varsayılan olarak testler JSON deposunda (geçici klasör) koşar.
 * EVOTEL_TEST_STORAGE=mysql verilirse aynı testler MySQL'de koşar (composer test:mysql):
 *   EVOTEL_TEST_DB_HOST / _PORT / _NAME (varsayılan evotel_test) / _USER / _PASSWORD
 * Eşzamanlılık testinin işçi süreçleri de aynı ayarları ortam değişkenlerinden okur.
 */
final class TestSettings
{
    public static function create(string $dir, string $logFile = '', bool $mailInbox = false, bool $demoPhotos = false): Settings
    {
        $env = static fn (string $name, string $default): string => is_string(getenv($name)) && getenv($name) !== '' ? (string) getenv($name) : $default;
        $root = dirname(__DIR__, 2);

        $settings = new Settings(
            dataDir: $dir . '/data',
            uploadDir: $dir . '/uploads',
            logFile: $logFile,
            mailDir: $dir . '/mail',
            viewsDir: $root . '/views',
            storage: $env('EVOTEL_TEST_STORAGE', 'json') === 'mysql' ? 'mysql' : 'json',
            dbHost: $env('EVOTEL_TEST_DB_HOST', '127.0.0.1'),
            dbPort: (int) $env('EVOTEL_TEST_DB_PORT', '3306'),
            dbName: $env('EVOTEL_TEST_DB_NAME', 'evotel_test'),
            dbUser: $env('EVOTEL_TEST_DB_USER', 'root'),
            dbPassword: $env('EVOTEL_TEST_DB_PASSWORD', ''),
            backupDir: $dir . '/backups',
            migrationsDir: $root . '/database/migrations',
            mailInbox: $mailInbox,
            demoPhotos: $demoPhotos, // örnek veri fotoğrafları yalnızca fotoğraf testinde çizilir (hız)
        );
        if ($settings->usesMySql() && !str_ends_with($settings->dbName, '_test')) {
            // Testler her seferinde tabloları boşaltır; gerçek veritabanı yanlışlıkla silinmesin.
            throw new RuntimeException('Test veritabanının adı "_test" ile bitmelidir: ' . $settings->dbName);
        }

        return $settings;
    }
}
