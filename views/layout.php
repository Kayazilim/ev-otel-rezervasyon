<?php
/**
 * Ortak sayfa yerleşimi: resmi ve minimalist tema (tasarım kaynağı Lovable prototipi, uygulama Bootstrap 5).
 *
 * @var App\Core\View $view
 * @var string $content
 * @var string $title
 * @var App\Model\User|null $currentUser
 * @var list<array{type: string, message: string}> $flashes
 * @var bool $mailInbox
 */

use App\Model\Role;

$currentUser ??= null;
$currentPath ??= '/';
$role = $currentUser?->role;
$nav = function (string $path, string $label) use ($view, $currentPath): string {
    $active = $currentPath === $path || ($path !== '/' && str_starts_with($currentPath, $path . '/'));

    return '<li class="nav-item"><a class="nav-link' . ($active ? ' active' : '') . '"' . ($active ? ' aria-current="page"' : '')
        . ' href="' . e($view->url($path)) . '">' . e($label) . '</a></li>';
};
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'EvOtel') ?> · <?= e($appName ?? 'EvOtel') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&amp;display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e($view->url('/assets/app.css')) ?>">
    <script>
    // Kısmi yenileme ve canlı arama yardımcıları (sayfa betiklerinden önce yüklenir).
    // Sunucu hata yanıtı verirse ya da ulaşılamazsa bölümün başında anlaşılır bir uyarı gösterilir;
    // oturum bittiyse (401) giriş sayfasına gidilir. Önemli kararlar (süre, yetki) her zaman sunucudadır.
    const girisAdresi = '<?= e($view->url('/giris')) ?>';
    const httpMesajlari = {
        400: 'Gönderilen bilgiler okunamadı.',
        403: 'Bu bölümü görme yetkiniz yok.',
        404: 'Bu bölümün içeriği bulunamadı.',
        503: 'Sunucu şu an yoğun.',
    };
    // mesaj null ise uyarı kaldırılır. "Yükleniyor..." yer tutucusu hata durumunda silinir.
    function bolumUyarisi(kutu, mesaj) {
        let uyari = kutu.querySelector(':scope > .bolum-uyari');
        if (mesaj === null) {
            if (uyari) {
                uyari.remove();
            }
            return;
        }
        kutu.querySelector(':scope > .yukleniyor')?.remove();
        if (!uyari) {
            uyari = document.createElement('div');
            uyari.className = 'bolum-uyari alert alert-warning py-1 px-2 small mb-2';
            uyari.setAttribute('role', 'alert');
            kutu.prepend(uyari);
        }
        uyari.textContent = mesaj;
    }
    // HTML parçası ister. Başarılıysa yanıtı döndürür; değilse uyarıyı gösterip null döndürür.
    async function bolumIstegi(adres, kutu) {
        let yanit;
        try {
            yanit = await fetch(adres, {headers: {'X-Requested-With': 'fetch'}});
        } catch (hata) {
            bolumUyarisi(kutu, 'Sunucuya ulaşılamadı; bağlantınızı kontrol edin. Bölüm birkaç saniye sonra yeniden denenecek.');
            return null;
        }
        if (yanit.status === 401) {
            window.location.href = girisAdresi;
            return null;
        }
        if (!yanit.ok) {
            bolumUyarisi(kutu, (httpMesajlari[yanit.status] || 'Sunucuda bir sorun oluştu.') + ' Bölüm güncellenemedi (HTTP ' + yanit.status + ').');
            return null;
        }
        return yanit;
    }
    // JSON ister ({"basarili": true, ...}). Başarılıysa veriyi döndürür; değilse uyarıyı gösterip null döndürür.
    async function jsonIstegi(adres, kutu) {
        let yanit;
        let veri = null;
        try {
            yanit = await fetch(adres, {headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}});
        } catch (hata) {
            bolumUyarisi(kutu, 'Sunucuya ulaşılamadı; bağlantınızı kontrol edin. Bölüm birkaç saniye sonra yeniden denenecek.');
            return null;
        }
        if (yanit.status === 401) {
            window.location.href = girisAdresi;
            return null;
        }
        try {
            veri = await yanit.json();
        } catch (hata) {
            veri = null;
        }
        if (!yanit.ok || veri === null || veri.basarili !== true) {
            const mesaj = veri !== null && typeof veri.hata === 'string' ? veri.hata : (httpMesajlari[yanit.status] || 'Sunucudan beklenmeyen bir yanıt geldi.');
            bolumUyarisi(kutu, mesaj + ' Bölüm güncellenemedi (HTTP ' + yanit.status + ').');
            return null;
        }
        bolumUyarisi(kutu, null);
        return veri;
    }
    </script>
</head>
<body>
<header class="ust-bar">
    <nav class="navbar navbar-expand-lg" aria-label="Ana menü">
        <div class="container">
            <a class="navbar-brand" href="<?= e($view->url('/')) ?>">EvOtel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ust-menu" aria-controls="ust-menu" aria-expanded="false" aria-label="Menüyü aç/kapat">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="ust-menu">
                <ul class="navbar-nav ms-lg-auto align-items-lg-center">
                    <?= $nav('/ara', 'Ara') ?>
                    <?php if ($role === Role::Musteri): ?>
                        <?= $nav('/favoriler', 'Favorilerim') ?>
                        <?= $nav('/rezervasyonlarim', 'Rezervasyonlarım') ?>
                    <?php elseif ($role === Role::IlanSahibi): ?>
                        <?= $nav('/panel', 'Panelim') ?>
                        <?= $nav('/panel/takvim', 'Takvim') ?>
                        <?= $nav('/panel/rezervasyonlar', 'Rezervasyonlar') ?>
                        <?= $nav('/panel/rapor', 'Rapor') ?>
                    <?php elseif ($role === Role::Yonetici): ?>
                        <?= $nav('/yonetim', 'Yönetim') ?>
                        <?= $nav('/yonetim/sikayetler', 'Şikâyetler') ?>
                        <?= $nav('/yonetim/kullanicilar', 'Kullanıcılar') ?>
                        <?= $nav('/yonetim/rapor', 'Rapor') ?>
                    <?php endif; ?>
                    <?php if ($currentUser === null): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e($view->url('/giris', ['rol' => 'ilan_sahibi'])) ?>#kayit">Ev sahibi ol</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= e($view->url('/giris')) ?>">Giriş yap</a></li>
                        <li class="nav-item ms-lg-2"><a class="btn btn-primary btn-sm" href="<?= e($view->url('/giris')) ?>#kayit">Kayıt ol</a></li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= e($view->url('/bildirimler')) ?>">Bildirimler <span id="bildirim-sayaci"></span></a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= e($currentUser->fullName()) ?> <span class="rozet ms-1"><?= e($currentUser->role->label()) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e($view->url('/profil')) ?>">Profilim</a></li>
                                <?php if ($role === Role::IlanSahibi): ?>
                                    <li><a class="dropdown-item" href="<?= e($view->url('/rezervasyonlarim')) ?>">Konakladığım yerler</a></li>
                                <?php endif; ?>
                                <?php if ($role !== Role::Yonetici): ?>
                                    <li><a class="dropdown-item" href="<?= e($view->url('/sikayetlerim')) ?>">Şikâyetlerim</a></li>
                                    <li><a class="dropdown-item" href="<?= e($view->url('/sikayet')) ?>">Şikâyet oluştur</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="<?= e($view->url('/cikis')) ?>" class="px-3">
                                        <?= $view->csrfField() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Çıkış yap</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main class="container sayfa">
    <?php foreach ($flashes ?? [] as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="alt-bar">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span>© EvOtel · Aşama 2 prototip: veriler JSON dosyalarında, ödemeler simüledir; kart bilgisi alınmaz.</span>
        <span class="d-flex flex-wrap gap-3">
            <?php if ($currentUser !== null && $role !== Role::Yonetici): ?>
                <a href="<?= e($view->url('/sikayet')) ?>">Şikâyet oluştur</a>
                <a href="<?= e($view->url('/sikayetlerim')) ?>">Şikâyetlerim</a>
            <?php endif; ?>
            <?php if ($mailInbox ?? false): ?>
                <a href="<?= e($view->url('/posta-kutusu')) ?>">Posta kutusu</a>
            <?php endif; ?>
        </span>
    </div>
    <?php if ($currentUser !== null): ?>
        <?php $kalan = (int) ($sessionRemaining ?? 3600); ?>
        <div class="container mt-2">
            <div id="oturum-sayaci" class="oturum-sayaci" role="timer" data-kalan="<?= $kalan ?>">
                Oturumun kapanmasına: <strong data-kalan-yazi><?= sprintf('%02d:%02d', intdiv($kalan, 60), $kalan % 60) ?></strong>
                <span class="d-none d-sm-inline">· Sayfada bir yere tıklamak ya da sayfayı yenilemek süreyi yeniden başlatır.</span>
            </div>
        </div>
    <?php endif; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
// Bonus: favori formu sayfa yenilenmeden gönderilir; PHP düğmenin yeni HTML'ini döndürür.
// Oturum sona erdiyse (401) giriş sayfasına gidilir; sunucuya ulaşılamazsa düğmenin altında uyarı çıkar.
document.addEventListener('submit', async function (olay) {
    const form = olay.target;
    if (!form.matches('[data-favori]')) {
        return;
    }
    olay.preventDefault();
    let yanit;
    try {
        yanit = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'fetch'}});
    } catch (hata) {
        let uyari = form.querySelector('.favori-uyari');
        if (!uyari) {
            uyari = document.createElement('div');
            uyari.className = 'favori-uyari small text-danger mt-1';
            uyari.setAttribute('role', 'alert');
            form.append(uyari);
        }
        uyari.textContent = 'Sunucuya ulaşılamadı; favori kaydedilemedi. Bağlantınızı kontrol edip tekrar deneyin.';
        return;
    }
    if (yanit.ok) {
        form.outerHTML = await yanit.text();
    } else if (yanit.status === 401) {
        window.location.href = girisAdresi;
    } else {
        form.submit();
    }
});
// Çift tıklamayı önlemek için gönderilen formun düğmesi kilitlenir (asıl güvence sunucudaki idempotency anahtarıdır).
document.addEventListener('submit', function (olay) {
    const dugme = olay.target.querySelector('[data-tek-tik]');
    if (dugme) {
        setTimeout(function () { dugme.disabled = true; }, 0);
    }
});
</script>
<?php if ($currentUser !== null): ?>
<script>
// Kısmi yenileme (ders kalıbı, "birkaç saniyede bir"): okunmamış bildirim sayısı 5 saniyede bir güncellenir.
async function bildirimYenile() {
    const sayac = document.getElementById('bildirim-sayaci');
    let yanit;
    try {
        yanit = await fetch('<?= e($view->url('/parca/bildirim-sayaci')) ?>');
    } catch (hata) {
        yanit = null;
    }
    if (yanit !== null && yanit.status === 401) {
        window.location.href = girisAdresi;
        return;
    }
    if (yanit === null || !yanit.ok) {
        // Menüdeki küçük sayaçta yer dar: uyarı işareti ve açıklaması (ekran okuyucu ve fare üzerine gelince) gösterilir.
        sayac.innerHTML = '';
        const isaret = document.createElement('span');
        isaret.className = 'badge text-bg-warning';
        isaret.textContent = '!';
        isaret.title = 'Bildirim sayısı güncellenemedi; birkaç saniye sonra yeniden denenecek.';
        isaret.setAttribute('aria-label', isaret.title);
        sayac.append(isaret);
        return;
    }
    sayac.innerHTML = await yanit.text();
}
bildirimYenile();
setInterval(bildirimYenile, 5000);

// Bonus (oturum zaman aşımı): sayfanın altındaki sayaç oturumun kapanmasına kalan süreyi gösterir.
// Sayfa yenilenince ve sayfada bir yere tıklanınca süre yeniden başlar (tıklama sunucuya bildirilir; karar sunucudadır).
// Sayaç sıfıra inince sunucuya sorulur: başka sekmede işlem yapılmadıysa oturum kapanmıştır, giriş sayfasına gidilir.
(function () {
    const kutu = document.getElementById('oturum-sayaci');
    if (!kutu) {
        return;
    }
    const yazi = kutu.querySelector('[data-kalan-yazi]');
    const adres = '<?= e($view->url('/api/oturum')) ?>';
    const jeton = '<?= e($csrfToken ?? '') ?>';
    let bitis = Date.now() + Number(kutu.dataset.kalan) * 1000;
    let sonBildirim = Date.now();
    let soruluyor = false;

    async function sunucuya(yontem) {
        const ayar = {method: yontem, headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}};
        if (yontem === 'POST') {
            const veri = new FormData();
            veri.append('_csrf', jeton);
            ayar.body = veri;
        }
        let yanit;
        try {
            yanit = await fetch(adres, ayar);
        } catch (hata) {
            return null; // bağlantı yok: sayaç yerel olarak sürer, sonra yeniden sorulur
        }
        if (yanit.status === 401) {
            window.location.href = girisAdresi; // oturum kapandı; giriş sayfası nedenini söyler
            return null;
        }
        if (!yanit.ok) {
            return null;
        }
        const veri = await yanit.json();
        bitis = Date.now() + veri.kalan_saniye * 1000;
        return veri.kalan_saniye;
    }

    function goster() {
        const kalan = Math.max(0, Math.ceil((bitis - Date.now()) / 1000));
        yazi.textContent = String(Math.floor(kalan / 60)).padStart(2, '0') + ':' + String(kalan % 60).padStart(2, '0');
        kutu.classList.toggle('oturum-az', kalan <= 300);
        if (kalan === 0 && !soruluyor) {
            soruluyor = true;
            // Sunucu süreyi saniyenin sonunda doldurur; bir saniye bekleyip sorulur (GET süreyi uzatmaz).
            setTimeout(function () {
                sunucuya('GET').finally(function () { soruluyor = false; });
            }, 1500);
        }
    }

    document.addEventListener('click', function () {
        // Art arda tıklamalarda sunucuya en çok 10 saniyede bir bildirilir.
        if (Date.now() - sonBildirim < 10000) {
            return;
        }
        sonBildirim = Date.now();
        sunucuya('POST');
    });
    goster();
    setInterval(goster, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
