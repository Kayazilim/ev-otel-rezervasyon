<?php
/**
 * Taslak 03: İlan detayı + aylık müsaitlik takvimi + rezervasyon alanı (FR-09, FR-10, FR-44).
 *
 * @var App\Core\View $view
 * @var App\Service\Dto\PropertyDetail $detail
 * @var int $unitId
 * @var array{0: int, 1: int} $month
 * @var array{giris: string, cikis: string, kisi: string} $form
 * @var App\Model\User|null $currentUser
 */
use App\Model\BookingMode;

$p = $detail->property;
$selectedUnit = null;
foreach ($detail->units as $unit) {
    if ($unit->id === $unitId) {
        $selectedUnit = $unit;
    }
}
$selectedUnit ??= $detail->units[0] ?? null;
$ay = sprintf('%04d-%02d', $month[0], $month[1]);
$first = new DateTimeImmutable($ay . '-01');
$prevMonth = $first->modify('-1 month')->format('Y-m');
$nextMonth = $first->modify('+1 month')->format('Y-m');
$keep = ['birim' => $selectedUnit?->id, 'giris' => $form['giris'], 'cikis' => $form['cikis'], 'kisi' => $form['kisi']];
?>
<a href="<?= e($view->url('/ara')) ?>" class="small">‹ Sonuçlara dön</a>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-1 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($p->name) ?> — <?= e($p->city) ?></h1>
        <div class="small"><?= $view->stars($detail->rating, $detail->reviewCount) ?> · <?= e($p->type->label()) ?>
            <?php if (!$p->isBookable()): ?> · <span class="badge text-bg-warning">İlan durumu: <?= e($p->status->label()) ?></span><?php endif; ?>
        </div>
    </div>
    <?php if ($currentUser !== null): ?>
        <?= $view->partial('partials/favorite_button', ['propertyId' => (int) $p->id, 'isFavorite' => $detail->isFavorite]) ?>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        <?php if ($detail->photos !== []): ?>
            <img src="<?= e($view->url('/foto/' . $detail->photos[0]->id)) ?>" class="ilan-foto mb-2" alt="<?= e($p->name) ?>">
            <?php if (count($detail->photos) > 1): ?>
                <div class="row g-2 mb-3">
                    <?php foreach (array_slice($detail->photos, 1, 4) as $photo): ?>
                        <div class="col-3"><img src="<?= e($view->url('/foto/' . $photo->id)) ?>" class="kucuk-foto" alt="<?= e($p->name) ?> fotoğraf" loading="lazy"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="emoji-foto mb-3" role="img" aria-label="<?= e($p->name) ?>"><?= e($p->type->emoji()) ?></div>
        <?php endif; ?>

        <h2 class="h5">Açıklama</h2>
        <p><?= nl2br(e($p->description)) ?></p>

        <p class="mb-1"><strong>Olanaklar:</strong>
            <?php foreach ($detail->amenities as $amenity): ?><span class="badge text-bg-light border me-1"><?= e($amenity->name) ?></span><?php endforeach; ?>
            <?php if ($detail->amenities === []): ?><span class="text-body-secondary">belirtilmemiş</span><?php endif; ?>
        </p>
        <p class="mb-1"><strong>Rezervasyon modu:</strong> <?= e($p->bookingMode->label()) ?>
            <span class="small text-body-secondary">(<?= $p->bookingMode === BookingMode::Aninda ? 'ödeme ile hemen kesinleşir, 10 dk bekletme' : 'ilan sahibi 24 saat içinde onaylar' ?>)</span>
        </p>
        <p class="mb-3"><strong>İptal koşulu:</strong> <?= e($detail->policyText) ?></p>

        <h2 class="h5">Odalar / birimler</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Ad</th><th>Oda tipi</th><th>Kapasite</th><th class="text-end">Temel gecelik</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($detail->units as $unit): ?>
                    <tr class="<?= $unit->id === $selectedUnit?->id ? 'table-success' : '' ?>">
                        <td><?= e($unit->name) ?></td>
                        <td><?= e($unit->roomType->label()) ?></td>
                        <td><?= (int) $unit->capacity ?> kişi</td>
                        <td class="text-end"><?= $view->money($unit->basePrice) ?></td>
                        <td class="text-end"><a class="btn btn-outline-success btn-sm" href="<?= e($view->url('/ilan/' . $p->id, ['birim' => $unit->id] + $keep + ['ay' => $ay])) ?>">Seç</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="h5">Konum</h2>
        <div class="border rounded p-3 bg-white mb-3">📍 <?= e($p->address) ?>, <?= e($p->city) ?> <span class="small text-body-secondary">(ilk sürümde harita yerine adres metni)</span></div>
        <?php if ($detail->host !== null): ?>
            <p class="small">Ev sahibi: <?= e($detail->host->firstName . ' ' . mb_substr($detail->host->lastName, 0, 1) . '.') ?></p>
        <?php endif; ?>

        <h2 class="h5">Yorumlar</h2>
        <?php foreach ($detail->reviews as $review): ?>
            <div class="border rounded bg-white p-2 mb-2">
                <div class="small"><span class="text-warning"><?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?></span> · <?= e($review['author']) ?> · <?= $view->date($review['date']) ?></div>
                <div><?= e($review['comment']) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($detail->reviews === []): ?><p class="text-body-secondary small">Henüz yorum yok.</p><?php endif; ?>
        <?php if ($currentUser !== null): ?>
            <a class="small text-danger" href="<?= e($view->url('/sikayet', ['ilan' => $p->id])) ?>">⚠️ Bu ilanı şikâyet et</a>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card shadow-sm sabit-panel">
            <div class="card-body">
                <?php if ($selectedUnit === null): ?>
                    <p class="text-body-secondary mb-0">Bu ilanda rezervasyona açık oda yok.</p>
                <?php else: ?>
                    <p class="fs-4 fw-bold mb-1"><?= $view->money($selectedUnit->basePrice) ?> <span class="fs-6 fw-normal">/ gece</span></p>
                    <form method="get" action="<?= e($view->url('/ilan/' . $p->id)) ?>" class="mb-2">
                        <label for="birim-sec" class="form-label small fw-semibold mb-1">Oda / birim</label>
                        <select id="birim-sec" name="birim" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($detail->units as $unit): ?>
                                <option value="<?= (int) $unit->id ?>"<?= $view->selected($unit->id, $selectedUnit->id) ?>><?= e($unit->name . ' · ' . $unit->roomType->label() . ' · ' . $unit->capacity . ' kişi') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="giris" value="<?= e($form['giris']) ?>">
                        <input type="hidden" name="cikis" value="<?= e($form['cikis']) ?>">
                        <input type="hidden" name="kisi" value="<?= e($form['kisi']) ?>">
                        <input type="hidden" name="ay" value="<?= e($ay) ?>">
                    </form>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold">MÜSAİTLİK TAKVİMİ</span>
                        <span>
                            <a class="btn btn-outline-secondary btn-sm py-0" href="<?= e($view->url('/ilan/' . $p->id, ['ay' => $prevMonth] + $keep)) ?>" aria-label="Önceki ay">‹</a>
                            <a class="btn btn-outline-secondary btn-sm py-0" href="<?= e($view->url('/ilan/' . $p->id, ['ay' => $nextMonth] + $keep)) ?>" aria-label="Sonraki ay">›</a>
                        </span>
                    </div>
                    <!-- Kısmi yenileme: takvim bölümü 5 saniyede bir PHP'den yeniden alınır -->
                    <div id="takvim-bolumu" class="mb-3">Yükleniyor...</div>
                    <script>
                    async function takvimYenile() {
                        const yanit = await fetch('<?= e($view->url('/parca/takvim', ['birim' => $selectedUnit->id, 'ay' => $ay])) ?>');
                        document.getElementById('takvim-bolumu').innerHTML = await yanit.text();
                    }
                    takvimYenile();
                    setInterval(takvimYenile, 5000);
                    </script>

                    <form method="get" action="<?= e($view->url('/rezervasyon/onizleme')) ?>" id="rezervasyon-formu" novalidate>
                        <input type="hidden" name="birim" value="<?= (int) $selectedUnit->id ?>">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="giris" class="form-label small fw-semibold mb-1">Giriş</label>
                                <input type="date" class="form-control" id="giris" name="giris" value="<?= e($form['giris']) ?>" required>
                            </div>
                            <div class="col-6">
                                <label for="cikis" class="form-label small fw-semibold mb-1">Çıkış</label>
                                <input type="date" class="form-control" id="cikis" name="cikis" value="<?= e($form['cikis']) ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="kisi" class="form-label small fw-semibold mb-1">Kişi sayısı</label>
                                <input type="number" class="form-control" id="kisi" name="kisi" min="1" max="<?= (int) $selectedUnit->capacity ?>" value="<?= e($form['kisi']) ?>" required>
                                <div class="form-text">Bu oda en çok <?= (int) $selectedUnit->capacity ?> kişiliktir.</div>
                            </div>
                        </div>
                        <div id="fiyat-onizleme" class="my-2"></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-success" id="fiyat-goster">Fiyatı gör (sayfa yenilenmeden)</button>
                            <button type="submit" class="btn btn-success btn-lg fw-semibold">REZERVASYON YAP</button>
                        </div>
                        <?php if ($currentUser === null): ?>
                            <p class="small text-body-secondary mt-2 mb-0">Giriş yapmadıysanız giriş sayfasına yönlendirilirsiniz; seçimleriniz korunur.</p>
                        <?php endif; ?>
                    </form>
                    <script>
                    // Bonus: form sayfa yenilenmeden gönderilir; PHP fiyat dökümünü üretir.
                    async function fiyatGetir() {
                        const parametreler = new URLSearchParams(new FormData(document.getElementById('rezervasyon-formu')));
                        const yanit = await fetch('<?= e($view->url('/parca/fiyat')) ?>?' + parametreler.toString());
                        document.getElementById('fiyat-onizleme').innerHTML = await yanit.text();
                    }
                    document.getElementById('fiyat-goster').addEventListener('click', fiyatGetir);
                    <?php if ($form['giris'] !== '' && $form['cikis'] !== ''): ?>fiyatGetir();<?php endif; ?>
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
