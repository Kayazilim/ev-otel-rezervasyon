<?php
/**
 * FR-27: ilan veya rezervasyon hakkında şikâyet.
 *
 * @var App\Core\View $view
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4">Şikâyet oluştur</h1>
                <p class="small text-body-secondary">Şikâyetiniz yönetici paneline iletilir.</p>
                <form method="post" action="<?= e($view->url('/sikayet')) ?>" novalidate>
                    <?= $view->csrfField() ?>
                    <?php if (($old['ilan'] ?? '') !== ''): ?>
                        <input type="hidden" name="ilan" value="<?= e($old['ilan']) ?>">
                        <p>İlan no: <strong><?= e($old['ilan']) ?></strong></p>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="rezervasyon" class="form-label">Rezervasyon numarası <span class="text-body-secondary">(rezervasyonla ilgiliyse)</span></label>
                        <input type="text" class="form-control<?= $view->invalid($errors, 'rezervasyon') ?>" id="rezervasyon" name="rezervasyon" placeholder="RZ-20261107-AB12CD" value="<?= e($old['rezervasyon'] ?? '') ?>">
                        <?= $view->error($errors, 'rezervasyon') ?>
                    </div>
                    <div class="mb-3">
                        <label for="aciklama" class="form-label">Açıklama</label>
                        <textarea class="form-control<?= $view->invalid($errors, 'aciklama') ?>" id="aciklama" name="aciklama" rows="5" minlength="10" maxlength="2000" required><?= e($old['aciklama'] ?? '') ?></textarea>
                        <?= $view->error($errors, 'aciklama') ?>
                    </div>
                    <button type="submit" class="btn btn-danger">Gönder</button>
                </form>
            </div>
        </div>
    </div>
</div>
