<?php
/**
 * Dikey ilan kartı (ana sayfa ve favoriler). Kartın tamamı ilana bağlanır (stretched-link);
 * favori düğmesi bağlantının üstünde ayrı bir formdur. Tür rozeti görselin sol altındadır.
 *
 * @var App\Core\View $view
 * @var App\Service\Dto\SearchResultItem $item
 * @var App\Model\User|null $currentUser
 */
$p = $item->property;
?>
<article class="ilan-karti">
    <div class="ilan-karti-foto">
        <?= $view->partial('partials/photo', ['photoId' => $item->photoId, 'type' => $p->type, 'alt' => $p->name]) ?>
        <span class="rozet ilan-karti-tur"><?= e($p->type->emoji()) ?> <?= e($p->type->label()) ?></span>
    </div>
    <div class="ilan-karti-govde">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <h3 class="ilan-karti-baslik"><a class="stretched-link" href="<?= e($view->url('/ilan/' . $p->id)) ?>"><?= e($p->name) ?></a></h3>
        </div>
        <p class="ilan-karti-sehir">📍 <?= e($p->city) ?> · en çok <?= (int) $item->maxCapacity ?> kişi</p>
        <p class="ilan-karti-puan"><?= $view->stars($item->rating, $item->reviewCount) ?></p>
        <p class="ilan-karti-fiyat"><strong><?= $view->money($item->nightly) ?></strong> <span class="text-body-secondary">/ gece'den</span></p>
    </div>
    <?php if ($currentUser !== null): ?>
        <div class="ilan-karti-favori"><?= $view->partial('partials/favorite_button', ['propertyId' => (int) $p->id, 'isFavorite' => $item->isFavorite]) ?></div>
    <?php endif; ?>
</article>
