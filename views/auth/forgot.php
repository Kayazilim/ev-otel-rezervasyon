<?php
/**
 * FR-04: parola sıfırlama talebi.
 *
 * @var App\Core\View $view
 * @var array<string, string> $errors
 */
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4">Parola sıfırlama</h1>
                <p class="small text-body-secondary">E-posta adresinizi girin. Hesabınız varsa 60 dakika geçerli, tek kullanımlık bir bağlantı gönderilir.
                    Bu prototipte e-posta simüledir: ileti <code>storage/mail</code> klasörüne yazılır.</p>
                <form method="post" action="<?= e($view->url('/parola-sifirla')) ?>" novalidate>
                    <?= $view->csrfField() ?>
                    <div class="mb-3">
                        <label for="eposta" class="form-label fw-semibold">E-posta</label>
                        <input type="email" class="form-control<?= $view->invalid($errors, 'eposta') ?>" id="eposta" name="eposta" required>
                        <?= $view->error($errors, 'eposta') ?>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Bağlantı gönder</button>
                </form>
            </div>
        </div>
    </div>
</div>
