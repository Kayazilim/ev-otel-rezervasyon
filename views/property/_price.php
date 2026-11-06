<?php
/**
 * Fiyat önizlemesi (FR-14, FR-44): gecelik döküm, indirim, vergi ve ek ücret.
 * Rezervasyonla aynı hesap kodu (PricingService) kullanılır.
 *
 * @var App\Core\View $view
 * @var App\Service\Dto\ReservationPreview|null $preview
 * @var array<string, string> $errors
 * @var App\Model\User|null $viewer
 */
?>
<?php if ($preview === null): ?>
    <div class="alert alert-warning py-2 mb-0">
        <?php foreach ($errors as $message): ?><div>⚠️ <?= e($message) ?></div><?php endforeach; ?>
    </div>
    <?php return; ?>
<?php endif; ?>
<?php $quote = $preview->quote; ?>
<?php if (!$preview->available): ?>
    <div class="alert alert-danger py-2">
        <strong>❌ Seçtiğiniz tarihler müsait değil.</strong>
        <?php if ($preview->alternatives !== []): ?>
            <div class="small mt-1">Yakın müsait tarihler:</div>
            <ul class="small mb-0">
                <?php foreach ($preview->alternatives as $alt): ?>
                    <li><a href="<?= e($view->url('/ilan/' . $preview->property->id, ['birim' => $preview->unit->id, 'giris' => $alt->checkIn->format('Y-m-d'), 'cikis' => $alt->checkOut->format('Y-m-d'), 'kisi' => $preview->guests])) ?>"><?= e((string) $alt) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="alert alert-success py-2 mb-2">✅ Bu tarihler şu an müsait. Kesin karar onay anında verilir.</div>
<?php endif; ?>
<table class="table table-sm dokum mb-1">
    <tbody>
    <?php foreach ($quote->nights as $night): ?>
        <tr><td class="small"><?= e($night->description()) ?></td><td><?= $view->money($night->price) ?></td></tr>
    <?php endforeach; ?>
    <tr class="fw-semibold"><td><?= (int) $quote->nightCount() ?> gece konaklama</td><td><?= $view->money($quote->stayTotal) ?></td></tr>
    <?php if ($quote->discount !== null): ?>
        <tr class="text-success"><td class="small"><?= e($quote->discount->description) ?></td><td><?= $view->money($quote->discount->amount) ?></td></tr>
    <?php endif; ?>
    <?php foreach ($quote->extras as $line): ?>
        <tr><td class="small"><?= e($line->description) ?></td><td><?= $view->money($line->amount) ?></td></tr>
    <?php endforeach; ?>
    <tr class="table-light fs-6 fw-bold"><td>TOPLAM</td><td><?= $view->money($quote->total) ?></td></tr>
    </tbody>
</table>
<p class="small text-body-secondary mb-0"><?= e($preview->policyText) ?></p>
