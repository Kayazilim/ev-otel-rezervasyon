<?php

declare(strict_types=1);

/*
 * Ön denetleyici: her istek buradan girer.
 * public/index.php -> Kernel -> Router -> Controller -> Service -> Repository
 */

// PHP yerleşik sunucusu (composer serve): var olan dosyaları (css) doğrudan sun
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url(is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
    if (is_file($requested)) {
        return false;
    }
}

// Kullanıcıya teknik ayrıntı (dosya yolu, satır, yığın izi) gösterilmez; WAMP gibi geliştirme kurulumlarında
// display_errors açık olsa da. Hatalar günlüğe yazılır, kullanıcı sade bir hata sayfası görür (H-05).
ini_set('display_errors', '0');

require dirname(__DIR__) . '/vendor/autoload.php';

$config = require dirname(__DIR__) . '/config/app.php';
$container = App\Core\Container::fromConfig($config);

(new App\Core\Kernel($container))
    ->handle(App\Core\Request::fromGlobals())
    ->send();
