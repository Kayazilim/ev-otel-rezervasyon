<?php

declare(strict_types=1);

/*
 * Uygulama ayarları. Süre, limit ve oran gibi sayısal kararlar tek yerde
 * tutulur (SRS 11.4, risk R12). Değer değişirse yalnızca bu dosya değişir.
 * Bilgisayara özel veya gizli değerler (ör. SMTP uygulama şifresi) config/yerel.php
 * dosyasına yazılır; o dosya git'e girmez (örnek: config/yerel.ornek.php).
 */

$kok = dirname(__DIR__);
$ortam = static fn (string $ad, string $varsayilan): string => is_string(getenv($ad)) && getenv($ad) !== '' ? (string) getenv($ad) : $varsayilan;

$ayarlar = [
    'app_name' => 'EvOtel',
    'timezone' => 'Europe/Istanbul',          // NFR-17: tüm zaman kararları sunucu saatiyle

    // Veri deposu: 'json' (varsayılan; veriler storage/data altındaki JSON dosyalarında)
    // veya 'mysql' (Aşama 3: PDO + MySQL). Değiştirmek için EVOTEL_STORAGE ortam değişkeni.
    'storage' => $ortam('EVOTEL_STORAGE', 'json'),
    'db' => [
        'host' => $ortam('EVOTEL_DB_HOST', '127.0.0.1'),
        'port' => (int) $ortam('EVOTEL_DB_PORT', '3306'),
        'name' => $ortam('EVOTEL_DB_NAME', 'evotel'),
        'user' => $ortam('EVOTEL_DB_USER', 'root'),
        'password' => $ortam('EVOTEL_DB_PASSWORD', ''),
    ],

    'data_dir' => $kok . '/storage/data',      // Aşama 2: JSON tablo dosyaları
    'backup_dir' => $kok . '/storage/backups', // NFR-09: günlük yedekler (son 7)
    'migrations_dir' => $kok . '/database/migrations',
    'upload_dir' => $kok . '/storage/uploads', // FR-46: web kökünün dışında
    'log_file' => $kok . '/storage/logs/app.log',
    'mail_dir' => $kok . '/storage/mail',      // simüle e-posta kutusu (parola sıfırlama)
    'views_dir' => $kok . '/views',

    // FR-04 e-postası: 'dosya' (varsayılan; ileti storage/mail klasörüne yazılır, gerçek adrese gitmez)
    // veya 'smtp' (gerçek gönderim, ör. Gmail uygulama şifresiyle). SMTP değerleri config/yerel.php'ye yazılır.
    'mail_driver' => $ortam('EVOTEL_MAIL', 'dosya'),
    'mail_from' => $ortam('EVOTEL_MAIL_FROM', ''),          // boşsa SMTP kullanıcı adı
    'mail_from_name' => 'EvOtel',
    'smtp' => [
        'host' => $ortam('EVOTEL_SMTP_HOST', ''),
        'port' => (int) $ortam('EVOTEL_SMTP_PORT', '587'),
        'secure' => $ortam('EVOTEL_SMTP_SECURE', 'tls'),   // tls (STARTTLS, 587) · ssl (465) · yok
        'user' => $ortam('EVOTEL_SMTP_USER', ''),
        'password' => $ortam('EVOTEL_SMTP_PASSWORD', ''),
    ],
    // Geliştirme: simüle e-postalar /posta-kutusu sayfasında görünür (yalnızca bu bilgisayardan açılır).
    // Gerçek bir sunucuda EVOTEL_POSTA_KUTUSU=0 ile kapatılmalıdır.
    'mail_inbox' => $ortam('EVOTEL_POSTA_KUTUSU', '1') === '1',

    'session_timeout_minutes' => 30,          // bonus: oturum zaman aşımı
    'hold_minutes_instant' => 10,             // BR-15: anında mod bekletme
    'hold_hours_approval' => 24,              // BR-15: onaylı mod bekletme
    'max_nights' => 30,                       // BR-14
    'max_days_ahead' => 365,                  // BR-14
    'alternative_count' => 3,                 // FR-36
    'lock_timeout_seconds' => 5.0,            // NFR-13: kilit bekleme en çok 5 sn
    'lock_retries' => 2,                      // SRS 4.5: en çok 2 yeniden deneme
    'login_max_attempts' => 5,                // NFR-15
    'login_lock_minutes' => 15,               // NFR-15
    'password_reset_minutes' => 60,           // FR-04
    'upload_max_bytes' => 2 * 1024 * 1024,    // NFR-19: en çok 2 MB
    'per_page' => 6,                          // bonus: sayfalama
];

// Yerel ayarlar (git'e girmez) varsa üzerine yazılır.
$yerel = __DIR__ . '/yerel.php';
if (is_file($yerel)) {
    $yukle = static fn (string $dosya): mixed => require $dosya;
    $ayarlar = array_replace_recursive($ayarlar, (array) $yukle($yerel));
}

return $ayarlar;
