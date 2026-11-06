<?php
/**
 * Arama sonuç listesi (sayfada ve canlı aramada aynı parça kullanılır).
 *
 * @var App\Core\View $view
 * @var App\Service\Dto\SearchResult $result
 * @var array<string, mixed> $q
 * @var App\Model\User|null $currentUser
 */
$c = $result->criteria;
?>
<?php if ($result->errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach ($result->errors as $message): ?>
            <div>⚠️ <?= e($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php elseif ($result->items->total === 0): ?>
    <div class="alert alert-info">Bu ölçütlere uygun müsait konaklama bulunamadı. Tarihleri veya filtreleri değiştirmeyi deneyin.</div>
<?php else: ?>
    <p class="small text-body-secondary"><?= (int) $result->items->total ?> konaklama bulundu<?= $result->range !== null ? ' · tüm geceleri müsait olanlar listeleniyor' : '' ?>.</p>
<?php endif; ?>

<?php foreach ($result->items->items as $item): ?>
    <?php
    $p = $item->property;
    $detailQuery = ['birim' => $item->cheapestUnit?->id, 'giris' => $c->checkIn, 'cikis' => $c->checkOut, 'kisi' => $c->guests];
    ?>
    <div class="card shadow-sm mb-3">
        <div class="row g-0">
            <div class="col-12 col-sm-4 p-2">
                <?= $view->partial('partials/photo', ['photoId' => $item->photoId, 'type' => $p->type, 'alt' => $p->name]) ?>
            </div>
            <div class="col-12 col-sm-8">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h2 class="h5 card-title mb-1"><?= e($p->name) ?> <span class="badge text-bg-light border align-middle"><?= e($p->type->label()) ?></span></h2>
                        <?php if (($currentUser ?? null) !== null): ?>
                            <?= $view->partial('partials/favorite_button', ['propertyId' => (int) $p->id, 'isFavorite' => $item->isFavorite]) ?>
                        <?php endif; ?>
                    </div>
                    <p class="small mb-1">📍 <?= e($p->address) ?>, <?= e($p->city) ?></p>
                    <p class="small mb-1"><?= $view->stars($item->rating, $item->reviewCount) ?> · Maks. <?= (int) $item->maxCapacity ?> kişi
                        <?php if ($p->type === App\Model\PropertyType::Otel): ?> · <strong><?= (int) $item->availableUnits ?></strong> uygun oda<?php endif; ?>
                    </p>
                    <p class="small text-body-secondary mb-2"><?= e(implode(' · ', $item->amenityNames)) ?></p>
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
                        <div>
                            <span class="fs-5 fw-bold"><?= $view->money($item->nightly) ?></span> <span class="small">/ gece<?= $item->quote === null ? "'den" : ' (ort.)' ?></span>
                            <?php if ($item->quote !== null): ?>
                                <div class="small text-body-secondary">Toplam <?= $view->money($item->quote->total) ?> · <?= (int) $item->quote->nightCount() ?> gece, vergi dahil</div>
                            <?php endif; ?>
                        </div>
                        <a class="btn btn-success" href="<?= e($view->url('/ilan/' . $p->id, $detailQuery)) ?>">Detayı gör</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?= $view->partial('partials/pagination', ['paginator' => $result->items, 'path' => '/ara', 'query' => array_diff_key($q, ['sayfa' => true])]) ?>
