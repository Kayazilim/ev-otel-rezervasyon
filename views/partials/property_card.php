<?php
/**
 * Dikey ilan kartı (ana sayfa ve favoriler).
 *
 * @var App\Core\View $view
 * @var App\Service\Dto\SearchResultItem $item
 * @var App\Model\User|null $currentUser
 */
$p = $item->property;
?>
<div class="card h-100 shadow-sm">
    <div class="p-2"><?= $view->partial('partials/photo', ['photoId' => $item->photoId, 'type' => $p->type, 'alt' => $p->name]) ?></div>
    <div class="card-body pt-1">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <h3 class="h6 card-title mb-1"><?= e($p->name) ?></h3>
            <span class="badge text-bg-light border"><?= e($p->type->label()) ?></span>
        </div>
        <p class="small text-body-secondary mb-1">📍 <?= e($p->city) ?> · en çok <?= (int) $item->maxCapacity ?> kişi</p>
        <p class="small mb-2"><?= $view->stars($item->rating, $item->reviewCount) ?></p>
        <p class="mb-0"><strong><?= $view->money($item->nightly) ?></strong> <span class="small text-body-secondary">/ gece'den</span></p>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <?php if ($currentUser !== null): ?>
            <?= $view->partial('partials/favorite_button', ['propertyId' => (int) $p->id, 'isFavorite' => $item->isFavorite]) ?>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
        <a class="btn btn-success btn-sm" href="<?= e($view->url('/ilan/' . $p->id)) ?>">Detayı gör</a>
    </div>
</div>
