<?php
/**
 * İlan sahibi paneli yan menüsü (taslak 06).
 *
 * @var App\Core\View $view
 * @var string $currentPath
 */
$items = [
    '/panel' => ['📊', 'Panel'],
    '/panel/ilanlar' => ['🏡', 'İlanlarım'],
    '/panel/takvim' => ['📅', 'Takvim / Fiyat'],
    '/panel/rezervasyonlar' => ['🧳', 'Rezervasyonlar'],
    '/panel/rapor' => ['📈', 'Doluluk ve gelir'],
    '/panel/ilan/yeni' => ['➕', 'Yeni ilan ekle'],
];
?>
<nav class="yan-menu mb-3" aria-label="İlan sahibi paneli">
    <span class="yan-menu-baslik">İlan sahibi</span>
    <?php foreach ($items as $path => [$emoji, $label]): ?>
        <a class="yan-menu-oge<?= $currentPath === $path ? ' active' : '' ?>"<?= $currentPath === $path ? ' aria-current="page"' : '' ?> href="<?= e($view->url($path)) ?>"><span aria-hidden="true"><?= $emoji ?></span> <?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
