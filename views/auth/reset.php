<?php
/**
 * FR-04: yeni parola belirleme. Bağlantı açılınca önce geçerliliği serviste denetlenir:
 * geçersiz, kullanılmış veya süresi dolmuşsa form gösterilmez; nedeni ve yeni bağlantı isteme düğmesi gösterilir.
 *
 * @var App\Core\View $view
 * @var string $token
 * @var array<string, string> $errors
 * @var string|null $invalid geçersizlik nedeni (null ise bağlantı kullanılabilir)
 * @var DateTimeImmutable|null $expiresAt bağlantının geçerlilik sonu
 */
$invalid ??= null;
$expiresAt ??= null;
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4">Yeni parola</h1>
                <?php if ($invalid !== null): ?>
                    <div class="alert alert-warning" role="alert"><?= e($invalid) ?></div>
                    <a class="btn btn-success w-100" href="<?= e($view->url('/parola-sifirla')) ?>">Yeni bağlantı iste</a>
                <?php else: ?>
                    <?php if ($expiresAt !== null): ?>
                        <p class="small text-body-secondary">Bu bağlantının geçerlilik sonu: <strong><?= e($expiresAt->format('d.m.Y H:i')) ?></strong>. Bağlantı yalnızca bir kez kullanılabilir.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= e($view->url('/parola-sifirla/' . $token)) ?>" novalidate>
                        <?= $view->csrfField() ?>
                        <div class="mb-3">
                            <label for="parola" class="form-label fw-semibold">Yeni parola (en az 8 karakter)</label>
                            <input type="password" class="form-control<?= $view->invalid($errors, 'parola') ?>" id="parola" name="parola" minlength="8" autocomplete="new-password" required>
                            <?= $view->error($errors, 'parola') ?>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Parolayı güncelle</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
