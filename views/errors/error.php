<?php
/**
 * Hata sayfası: büyük emoji, durum kodu, anlaşılır mesaj ve iki yol (geri dön / ana sayfa).
 *
 * @var App\Core\View $view
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string $home
 */
$emoji = match ($status) {
    403 => '🚫',
    404 => '🧭',
    419 => '🔐',
    503 => '⏳',
    default => '⚠️',
};
?>
<div class="hata-sayfasi">
    <div class="hata-emoji" aria-hidden="true"><?= $emoji ?></div>
    <h1><?= e($title) ?> <span class="text-body-secondary">(<?= (int) $status ?>)</span></h1>
    <p><?= e($message) ?></p>
    <div class="d-flex justify-content-center flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="javascript:history.back()">‹ Geri dön</a>
        <a class="btn btn-primary" href="<?= e($view->url($home)) ?>">Ana sayfa</a>
    </div>
</div>
