<?php

declare(strict_types=1);

/*
 * Kural bozma deneyi: testler ilgili davranışı gerçekten ölçüyor mu?
 *
 * Her deneyde çekirdek problemin önemli bir kuralı kaynak kodda GEÇİCİ olarak bozulur, testler çalıştırılır
 * ve kırılan testler kaydedilir. Dosya her durumda (hata veya Ctrl+C dahil) eski hâline döndürülür.
 *
 *   php bin/kural-boz.php                  -> bütün deneyler; sonuç docs/kural-bozma-deneyi.md dosyasına yazılır
 *   php bin/kural-boz.php --deney=hafta-sonu -> tek deney (sunumda canlı göstermek için), dosyaya yazmaz
 *   php bin/kural-boz.php --liste          -> deneyleri listeler
 *
 * Süre: her deney eşzamanlılık testleri hariç bütün testleri çalıştırır (deney başına yaklaşık 25 sn).
 */

$root = dirname(__DIR__);
$options = getopt('', ['deney::', 'liste']);

$deneyler = [
    'yari-acik-aralik' => [
        'kural' => 'BR-11 / BR-13: yarı açık aralık; çıkış günü başka misafirin giriş günü olabilir',
        'dosya' => 'src/Repository/Json/JsonReservationRepository.php',
        'eski' => '&& $in < $r[\'check_out\']',
        'yeni' => '&& $in <= $r[\'check_out\']',
        'anlam' => 'Çıkış günü de dolu sayılır; arka arkaya iki rezervasyon çakışıyor görünür.',
    ],
    'suresi-dolan-bekletme' => [
        'kural' => 'BR-12 / BR-15: süresi dolan Beklemede kayıt geceleri engellemez',
        'dosya' => 'src/Service/BookingConflictService.php',
        'eski' => 'ReservationStatus::Beklemede => $reservation->expiresAt === null || $reservation->expiresAt > $now,',
        'yeni' => 'ReservationStatus::Beklemede => true,',
        'anlam' => 'Süresi dolmuş bekletme de geceleri tutmaya devam eder.',
    ],
    'hafta-sonu' => [
        'kural' => 'BR-23: hafta sonu farkı Cuma ve Cumartesi gecelerine uygulanır',
        'dosya' => 'src/Service/Pricing/NightPrice.php',
        'eski' => "in_array((int) \$this->date->format('N'), [5, 6], true)",
        'yeni' => "in_array((int) \$this->date->format('N'), [6, 7], true)",
        'anlam' => 'Hafta sonu Cumartesi ve Pazar geceleri sayılır.',
    ],
    'uzun-konaklama' => [
        'kural' => 'BR-24: ulaşılan en yüksek uzun konaklama eşiği uygulanır',
        'dosya' => 'src/Service/Pricing/LongStayDiscountStrategy.php',
        'eski' => 'if ($best === null || $min > (int) $best->minNights',
        'yeni' => 'if ($best === null || $min < (int) $best->minNights',
        'anlam' => 'En düşük eşik seçilir (28 gecede %20 yerine %10).',
    ],
    'iade-orani' => [
        'kural' => 'BR-17: Orta politikada girişe en az 5 gün kala %100, 1–4 gün kala %50 iade',
        'dosya' => 'src/Service/Refund/ModeratePolicy.php',
        'eski' => 'return $daysLeft >= 5 ? 100 : 50;',
        'yeni' => 'return $daysLeft >= 4 ? 100 : 50;',
        'anlam' => 'Eşik bir gün kayar; 4 gün kala da tam iade verilir.',
    ],
    'kosullu-guncelleme' => [
        'kural' => 'Eşzamanlılık: koşullu güncelleme (yalnızca durum hâlâ beklenen değerse değişir)',
        'dosya' => 'src/Repository/Json/JsonReservationRepository.php',
        'eski' => "fn (array \$r): bool => \$r['id'] === \$id && \$r['status'] === \$expected->value,",
        'yeni' => "fn (array \$r): bool => \$r['id'] === \$id,",
        'anlam' => 'Durum az önce değişmiş olsa da üzerine yazılır (ör. iptal edilmiş kayıt onaylanır).',
    ],
    'gece-benzersizligi' => [
        'kural' => 'Çift rezervasyon önleme, son savunma: UNIQUE(birim, gece)',
        'dosya' => 'src/Repository/Json/JsonSchema.php',
        'eski' => "'unique' => [['unit_id', 'night_date']]",
        'yeni' => "'unique' => []",
        'anlam' => 'Aynı birimin aynı gecesi iki kez yazılabilir.',
    ],
];

if (isset($options['liste'])) {
    foreach ($deneyler as $kod => $d) {
        printf("%-22s %s\n", $kod, $d['kural']);
    }
    exit(0);
}
$secilen = is_string($options['deney'] ?? null) ? $options['deney'] : null;
if ($secilen !== null && !isset($deneyler[$secilen])) {
    fwrite(STDERR, "Bilinmeyen deney: $secilen (php bin/kural-boz.php --liste)\n");
    exit(1);
}
$calisacak = $secilen === null ? $deneyler : [$secilen => $deneyler[$secilen]];

// Bozulan dosya her koşulda geri yüklenir: normal bitiş, hata ve (Windows'ta) Ctrl+C.
$yedek = new class {
    /** @var array<string, string> dosya yolu => orijinal içerik */
    public array $dosyalar = [];

    public function geriYukle(): void
    {
        foreach ($this->dosyalar as $yol => $icerik) {
            file_put_contents($yol, $icerik);
        }
        $this->dosyalar = [];
    }
};
$geriYukle = static function () use ($yedek): void {
    $yedek->geriYukle();
};
register_shutdown_function($geriYukle);
if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(static function () use ($geriYukle): void {
        $geriYukle();
        exit(130);
    });
}

/** @return array{0: int, 1: list<string>} [test sayısı, kırılan testler] */
$testleriCalistir = static function () use ($root): array {
    $log = (string) tempnam(sys_get_temp_dir(), 'kural-boz-');
    $komut = [PHP_BINARY, $root . '/vendor/bin/phpunit', '--exclude-group', 'eszamanlilik', '--log-junit', $log];
    $surec = proc_open($komut, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $borular, $root);
    if (is_resource($surec)) {
        stream_get_contents($borular[1]);
        stream_get_contents($borular[2]);
        fclose($borular[1]);
        fclose($borular[2]);
        proc_close($surec);
    }
    $xml = is_file($log) && filesize($log) > 0 ? simplexml_load_file($log) : false;
    @unlink($log);
    $kirilan = [];
    $toplam = 0;
    foreach ($xml !== false ? $xml->xpath('//testcase') ?: [] : [] as $case) {
        $toplam++;
        if (isset($case->failure) || isset($case->error)) {
            $sinif = explode('\\', (string) $case['class']);
            $kirilan[] = end($sinif) . '::' . (string) $case['name'];
        }
    }

    return [$toplam, array_values(array_unique($kirilan))];
};

echo "Önce kurallar bozulmadan testler çalıştırılıyor...\n";
[$tabanToplam, $tabanKirilan] = $testleriCalistir();
if ($tabanKirilan !== []) {
    fwrite(STDERR, "Testler zaten kırık; deney anlamsız olur:\n  " . implode("\n  ", $tabanKirilan) . "\n");
    exit(1);
}
printf("  %d test, hepsi geçti.\n\n", $tabanToplam);

$sonuclar = [];
foreach ($calisacak as $kod => $d) {
    $yol = $root . '/' . $d['dosya'];
    $orijinal = (string) file_get_contents($yol);
    if (substr_count($orijinal, $d['eski']) !== 1) {
        fwrite(STDERR, "[$kod] Bozulacak satır bulunamadı ({$d['dosya']}); kod değişmiş olabilir.\n");
        exit(1);
    }
    printf("[%s] %s\n  bozuluyor: %s\n", $kod, $d['kural'], $d['dosya']);
    $yedek->dosyalar[$yol] = $orijinal;
    file_put_contents($yol, str_replace($d['eski'], $d['yeni'], $orijinal));
    try {
        [$toplam, $kirilan] = $testleriCalistir();
    } finally {
        $geriYukle();
    }
    if ((string) file_get_contents($yol) !== $orijinal) {
        fwrite(STDERR, "Dosya geri yüklenemedi: {$d['dosya']}\n");
        exit(1);
    }
    printf("  %d / %d test kırıldı%s\n  dosya eski hâline döndürüldü.\n\n", count($kirilan), $toplam, $kirilan === [] ? ' (KURAL TESTSİZ!)' : '');
    foreach ($kirilan as $test) {
        echo '    ✗ ' . $test . "\n";
    }
    $sonuclar[$kod] = $d + ['toplam' => $toplam, 'kirilan' => $kirilan];
}

echo "Kurallar geri yüklendikten sonra testler yeniden çalıştırılıyor...\n";
[$sonToplam, $sonKirilan] = $testleriCalistir();
printf("  %d test, %s\n", $sonToplam, $sonKirilan === [] ? 'hepsi geçti.' : count($sonKirilan) . ' kırık!');

if ($secilen !== null) {
    exit($sonKirilan === [] ? 0 : 1);
}

$tarih = (new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')))->format('d.m.Y H:i');
$yakalanan = count(array_filter($sonuclar, static fn (array $s): bool => $s['kirilan'] !== []));
$md = [];
$md[] = '# Kural Bozma Deneyi';
$md[] = '';
$md[] = '> Bu dosya `php bin/kural-boz.php` ile **otomatik üretilir**. Soru: testler ilgili davranışı gerçekten ölçüyor mu?';
$md[] = '> Her deneyde çekirdek problemin bir kuralı kaynak kodda geçici olarak bozuldu, testler (eşzamanlılık testleri hariç) çalıştırıldı,';
$md[] = '> kırılan testler kaydedildi ve dosya eski hâline döndürüldü. Tek bir deneyi sunumda canlı göstermek için: `php bin/kural-boz.php --deney=hafta-sonu`.';
$md[] = '';
$md[] = sprintf(
    'Son çalıştırma: %s · PHP %s · bozulmadan önce %d test geçti · %d deneyin %d tanesinde ilgili testler kırıldı · geri yüklendikten sonra %d test, %s.',
    $tarih,
    PHP_VERSION,
    $tabanToplam,
    count($sonuclar),
    $yakalanan,
    $sonToplam,
    $sonKirilan === [] ? 'hepsi geçti' : count($sonKirilan) . ' kırık'
);
$md[] = '';
$md[] = '| Deney | Bozulan kural | Bozulma ne demek | Kırılan test |';
$md[] = '| --- | --- | --- | ---: |';
foreach ($sonuclar as $kod => $s) {
    $md[] = sprintf('| `%s` | %s | %s | %d / %d |', $kod, $s['kural'], $s['anlam'], count($s['kirilan']), $s['toplam']);
}
$md[] = '';
foreach ($sonuclar as $kod => $s) {
    $md[] = '## `' . $kod . '`: ' . $s['kural'];
    $md[] = '';
    $md[] = 'Dosya: `' . $s['dosya'] . '`';
    $md[] = '';
    $md[] = '```diff';
    $md[] = '- ' . trim($s['eski']);
    $md[] = '+ ' . trim($s['yeni']);
    $md[] = '```';
    $md[] = '';
    $md[] = $s['kirilan'] === []
        ? '**Hiçbir test kırılmadı: bu kural testsiz.**'
        : 'Kırılan testler (' . count($s['kirilan']) . '): ' . implode(', ', array_map(static fn (string $t): string => '`' . $t . '`', $s['kirilan'])) . '.';
    $md[] = '';
}
file_put_contents($root . '/docs/kural-bozma-deneyi.md', implode("\n", $md));
echo "\ndocs/kural-bozma-deneyi.md yazıldı.\n";
exit($sonKirilan === [] ? 0 : 1);
