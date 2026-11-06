<?php
/**
 * Taslak 01: Ana sayfa / arama.
 *
 * @var App\Core\View $view
 * @var list<App\Service\Dto\SearchResultItem> $featured
 * @var array<string, string> $types
 */
?>
<section class="p-4 p-md-5 mb-4 bg-white rounded-3 border shadow-sm">
    <h1 class="display-6 fw-bold">Konaklamanızı bulun</h1>
    <p class="lead text-body-secondary">Otel, oda veya kiralık ev — tarihe ve kişiye uygun, müsait seçenekler.</p>

    <form method="get" action="<?= e($view->url('/ara')) ?>" class="row g-3 align-items-end" novalidate>
        <div class="col-12 col-md-4">
            <label for="konum" class="form-label fw-semibold">Konum / Şehir</label>
            <input type="text" class="form-control" id="konum" name="konum" placeholder="örn. Trabzon" autocomplete="off">
        </div>
        <div class="col-6 col-md-2">
            <label for="giris" class="form-label fw-semibold">Giriş tarihi</label>
            <input type="date" class="form-control" id="giris" name="giris">
        </div>
        <div class="col-6 col-md-2">
            <label for="cikis" class="form-label fw-semibold">Çıkış tarihi</label>
            <input type="date" class="form-control" id="cikis" name="cikis">
        </div>
        <div class="col-6 col-md-2">
            <label for="kisi" class="form-label fw-semibold">Kişi sayısı</label>
            <input type="number" class="form-control" id="kisi" name="kisi" value="2" min="1" max="20">
        </div>
        <div class="col-6 col-md-2">
            <label for="tur" class="form-label fw-semibold">Konaklama türü</label>
            <select class="form-select" id="tur" name="tur">
                <option value="">Tümü</option>
                <?php foreach ($types as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3 ms-auto">
            <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">🔎 ARA</button>
        </div>
    </form>
</section>

<h2 class="h5 mb-3">Öne çıkan konaklamalar</h2>
<div class="row g-3">
    <?php foreach ($featured as $item): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <?= $view->partial('partials/property_card', ['item' => $item]) ?>
        </div>
    <?php endforeach; ?>
    <?php if ($featured === []): ?>
        <p class="text-body-secondary">Henüz yayında ilan yok.</p>
    <?php endif; ?>
</div>
