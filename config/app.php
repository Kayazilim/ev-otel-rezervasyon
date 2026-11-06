<?php

declare(strict_types=1);

/*
 * Uygulama ayarları. Süre, limit ve oran gibi sayısal kararlar tek yerde
 * tutulur (SRS 11.4, risk R12). Değer değişirse yalnızca bu dosya değişir.
 */

$kok = dirname(__DIR__);

return [
    'app_name' => 'EvOtel',
    'timezone' => 'Europe/Istanbul',          // NFR-17: tüm zaman kararları sunucu saatiyle
    'data_dir' => $kok . '/storage/data',      // Aşama 2: JSON tablo dosyaları
    'upload_dir' => $kok . '/storage/uploads', // FR-46: web kökünün dışında
    'log_file' => $kok . '/storage/logs/app.log',
    'mail_dir' => $kok . '/storage/mail',      // simüle e-posta kutusu (parola sıfırlama)
    'views_dir' => $kok . '/views',

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
