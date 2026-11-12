<?php
/**
 * Rezervasyon durumu bölümü. Sayfa ilk açılışta bu HTML'i basar; sonra JavaScript 5 saniyede bir
 * GET /api/rezervasyon/{no}/durum adresinden JSON alıp yalnızca data-alan işaretli öğeleri günceller.
 *
 * @var App\Core\View $view
 * @var array{durum: string, etiket: string, rozet: string, kalan_saniye: int|null, kalan_metin: string|null, bitis: string|null, mesaj: string, saat: string} $durum
 */
?>
<div class="d-flex flex-wrap align-items-center gap-2">
    <span class="fs-5"><span class="badge text-bg-<?= e($durum['rozet']) ?>" data-alan="etiket"><?= e($durum['etiket']) ?></span></span>
    <span data-alan="kalan-kutu"<?= $durum['kalan_metin'] === null ? ' hidden' : '' ?>>
        ⏳ Kalan bekletme süresi: <strong data-alan="kalan"><?= e($durum['kalan_metin'] ?? '') ?></strong>
        <span class="small text-body-secondary">(<span data-alan="bitis"><?= e($durum['bitis'] ?? '') ?></span>'e kadar)</span>
    </span>
    <span class="small text-body-secondary ms-auto" title="Bu bölüm 5 saniyede bir sunucudan JSON olarak güncellenir">⟳ <span data-alan="saat"><?= e($durum['saat']) ?></span></span>
</div>
<p class="small mb-0 mt-1" data-alan="mesaj"><?= e($durum['mesaj']) ?></p>
<p class="small mb-0 mt-1" data-alan="degisti" hidden>
    Rezervasyonun durumu değişti. Güncel işlemleri görmek için <a href="">sayfayı yenileyin</a>.
</p>
