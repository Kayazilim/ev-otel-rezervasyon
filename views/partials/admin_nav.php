<?php
/**
 * Yönetici paneli menüsü: sayfanın üstünde yatay sekmeler (tablolar tam genişlik kullanır).
 *
 * @var App\Core\View $view
 * @var string $currentPath
 */
$items = [
    '/yonetim' => ['📊', 'Özet'],
    '/yonetim/kullanicilar' => ['👥', 'Kullanıcılar'],
    '/yonetim/ilanlar' => ['🏡', 'İlanlar'],
    '/yonetim/sikayetler' => ['💬', 'Şikâyetler'],
    '/yonetim/yorumlar' => ['⭐', 'Yorum denetimi'],
    '/yonetim/rapor' => ['📈', 'Doluluk ve gelir'],
    '/yonetim/denetim' => ['🗂️', 'Denetim kaydı'],
];
?>
<ul class="nav nav-pills flex-wrap mb-4 yonetim-sekmeleri">
    <?php foreach ($items as $path => [$emoji, $label]): ?>
        <li class="nav-item"><a class="nav-link<?= $currentPath === $path ? ' active' : '' ?>"<?= $currentPath === $path ? ' aria-current="page"' : '' ?> href="<?= e($view->url($path)) ?>"><span aria-hidden="true"><?= $emoji ?></span> <?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>
