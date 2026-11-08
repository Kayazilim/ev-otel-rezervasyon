<?php

declare(strict_types=1);

/*
 * Aşama 3 kurulum: MySQL veritabanını ve tablolarını oluşturur (database/migrations/*.sql).
 *
 *   php bin/migrate.php            -> uygulanmamış migration'ları sırayla uygular
 *   php bin/migrate.php --fresh    -> tüm tabloları silip baştan kurar (veriler silinir!)
 *   php bin/migrate.php --seed     -> kurulumdan sonra örnek veriyi yükler
 *
 * Bağlantı ayarları EVOTEL_DB_HOST / EVOTEL_DB_PORT / EVOTEL_DB_NAME / EVOTEL_DB_USER / EVOTEL_DB_PASSWORD
 * ortam değişkenlerinden okunur (config/app.php). Uygulamanın MySQL kullanması için EVOTEL_STORAGE=mysql.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/app.php';
$config['storage'] = 'mysql'; // bu betik her zaman MySQL için çalışır
$container = App\Core\Container::fromConfig($config);

try {
    $container->mysqlDatabase()->createDatabaseIfMissing();
    $migrator = $container->migrator();
    $applied = in_array('--fresh', $argv, true) ? $migrator->fresh() : $migrator->migrate();
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration başarısız: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $applied === [] ? "Şema güncel; uygulanacak migration yok.\n" : "Uygulanan migration'lar:\n  " . implode("\n  ", $applied) . "\n";

if (in_array('--seed', $argv, true)) {
    $summary = (new App\Seed\DemoSeeder($container))->run();
    echo "Örnek veri yüklendi:\n";
    foreach ($summary as $label => $count) {
        echo '  ' . $label . str_repeat(' ', max(1, 14 - mb_strlen($label))) . $count . "\n";
    }
}
