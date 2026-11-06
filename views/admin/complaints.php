<?php
/**
 * FR-27: şikâyetlerin incelenmesi.
 *
 * @var App\Core\View $view
 * @var App\Support\Paginator<array{complaint: App\Model\Complaint, sender: string, property: string, number: string}> $page
 * @var string $status
 * @var array<string, string> $statuses
 */
?>
<?= $view->partial('partials/admin_nav') ?>
<h1 class="h4">Şikâyetler</h1>
<ul class="nav nav-pills mb-3 flex-wrap">
    <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="<?= e($view->url('/yonetim/sikayetler')) ?>">Tümü</a></li>
    <?php foreach ($statuses as $value => $label): ?>
        <li class="nav-item"><a class="nav-link<?= $status === $value ? ' active' : '' ?>" href="<?= e($view->url('/yonetim/sikayetler', ['durum' => $value])) ?>"><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
<?php foreach ($page->items as $row): ?>
    <?php $c = $row['complaint']; ?>
    <div class="card shadow-sm mb-2"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div><strong>#<?= (int) $c->id ?></strong> · <?= e($row['sender']) ?> · İlan: <?= e($row['property']) ?> · Rezervasyon: <span class="font-monospace"><?= e($row['number']) ?></span></div>
            <div class="small text-body-secondary"><?= $view->date($c->createdAt, 'd.m.Y H:i') ?> · <span class="badge text-bg-secondary"><?= e($c->status->label()) ?></span></div>
        </div>
        <p class="my-2"><?= nl2br(e($c->description)) ?></p>
        <?php if ($c->adminNote !== null): ?><p class="small mb-2">Yönetici notu: <?= e($c->adminNote) ?></p><?php endif; ?>
        <form method="post" action="<?= e($view->url('/yonetim/sikayet/' . $c->id)) ?>" class="row g-2">
            <?= $view->csrfField() ?>
            <div class="col-12 col-md-3"><select name="durum" class="form-select form-select-sm" aria-label="Durum"><?php foreach ($statuses as $value => $label): ?><option value="<?= e($value) ?>"<?= $view->selected($c->status->value, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-7"><input type="text" name="not" class="form-control form-control-sm" placeholder="Yönetici notu" aria-label="Yönetici notu"></div>
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-outline-success btn-sm w-100">Kaydet</button></div>
        </form>
    </div></div>
<?php endforeach; ?>
<?php if ($page->total === 0): ?><p class="text-body-secondary">Şikâyet yok.</p><?php endif; ?>
<?= $view->partial('partials/pagination', ['paginator' => $page, 'path' => '/yonetim/sikayetler', 'query' => ['durum' => $status]]) ?>
