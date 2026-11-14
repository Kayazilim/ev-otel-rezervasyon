<?php
/**
 * Taslak 01: Ana sayfa / arama. Hap biçimli arama çubuğu, tür kısayolları ve öne çıkan ilanlar.
 *
 * @var App\Core\View $view
 * @var list<App\Service\Dto\SearchResultItem> $featured
 * @var array<string, string> $types
 * @var App\Model\User|null $currentUser
 */

use App\Model\PropertyType;

?>
<section class="kahraman">
    <span class="ust-yazi">Yeni yerler, güzel anılar</span>
    <h1>Konaklamanızı bulun</h1>
    <p class="giris-metni">Oteller ve kiralık evler, tek yerde. Tarihlerinizi seçin, müsaitliği ve gecelik fiyatı hemen görün.</p>

    <form method="get" action="<?= e($view->url('/ara')) ?>" class="arama-paneli" role="search" novalidate>
        <div class="row g-1 align-items-center">
            <div class="col-12 col-lg-3 arama-bolme">
                <label for="konum" class="form-label">📍 Konum</label>
                <input type="text" class="form-control" id="konum" name="konum" placeholder="Nereye gitmek istersiniz?" autocomplete="off">
            </div>
            <div class="col-6 col-lg-2 arama-bolme">
                <label for="giris" class="form-label">📅 Giriş</label>
                <input type="date" class="form-control" id="giris" name="giris">
            </div>
            <div class="col-6 col-lg-2 arama-bolme">
                <label for="cikis" class="form-label">📅 Çıkış</label>
                <input type="date" class="form-control" id="cikis" name="cikis">
            </div>
            <div class="col-6 col-lg-1 arama-bolme">
                <label for="kisi" class="form-label">👤 Kişi</label>
                <input type="number" class="form-control" id="kisi" name="kisi" value="2" min="1" max="20">
            </div>
            <div class="col-6 col-lg-2 arama-bolme son">
                <label for="tur" class="form-label">🏡 Tür</label>
                <select class="form-select" id="tur" name="tur">
                    <option value="">Tümü</option>
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary arama-dugmesi"><span aria-hidden="true">🔍</span> Ara</button>
            </div>
        </div>
    </form>

    <nav class="kategori-cipleri" aria-label="Hızlı arama">
        <a class="cip" href="<?= e($view->url('/ara')) ?>">✨ Tüm konaklamalar</a>
        <?php foreach (PropertyType::cases() as $type): ?>
            <a class="cip" href="<?= e($view->url('/ara', ['tur' => $type->value])) ?>"><?= e($type->emoji()) ?> <?= e($type->label()) ?></a>
        <?php endforeach; ?>
    </nav>
</section>

<section>
    <div class="d-flex justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h2 class="bolum-baslik">Öne çıkan konaklamalar</h2>
            <p class="text-body-secondary small mb-0 mt-1">Misafirlerin en çok beğendiği yerler.</p>
        </div>
        <a href="<?= e($view->url('/ara')) ?>" class="btn btn-outline-secondary btn-sm rounded-pill">Tümünü gör →</a>
    </div>
    <div class="row g-4">
        <?php foreach ($featured as $item): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <?= $view->partial('partials/property_card', ['item' => $item]) ?>
            </div>
        <?php endforeach; ?>
        <?php if ($featured === []): ?>
            <p class="text-body-secondary">Henüz yayında ilan yok.</p>
        <?php endif; ?>
    </div>
</section>

<?php if (($currentUser ?? null) === null): ?>
<section class="ev-sahibi-cagri">
    <div>
        <h2>🏡 Eviniz, birinin güzel anısı olsun.</h2>
        <p>Otelinizi ya da kiralık evinizi ilan edin; takvimi, fiyatı ve rezervasyonları tek panelden yönetin.</p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e($view->url('/giris', ['rol' => 'ilan_sahibi'])) ?>#kayit">Ev sahibi ol →</a>
</section>
<?php endif; ?>
