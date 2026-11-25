<?php
function rbIcon($key) {
    $paths = [
      'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
      'materi'=>'<path d="M12 5v16M12 5C8 2 4 3 2 4v16c4-2 7-1 10 1 3-2 6-3 10-1V4c-4-2-7-1-10 1Z"/>',
      'kuis'=>'<rect x="5" y="3" width="14" height="19" rx="3"/><path d="M9 3V1h6v2M9 9h6M9 13h6M9 17h3"/>',
      'forum'=>'<path d="M21 11a9 9 0 0 1-9 9H4l-3 3 2-8a9 9 0 1 1 18-4Z"/><path d="M7 9h10M7 13h7"/>',
      'logout'=>'<path d="M9 3H4v18h5M9 12h12m-5-5 5 5-5 5"/>'];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$key] ?? $paths['materi']).'</svg>';
}
function rbShell($active, $title) {
    global $userAktif;
    $name = $userAktif['nama'];
    $initial = function_exists('mb_substr') ? mb_substr($name,0,1,'UTF-8') : substr($name,0,1);
?>
<a class="rb-skip" href="#konten">Langsung ke konten</a>
<aside class="rb-sidebar" aria-label="Menu utama">
<a class="rb-brand" href="dashboard.php"><span class="rb-mark"><?= rbIcon('materi') ?></span><span>Ruang Belajar<small>LMS / PTIK B</small></span></a>
<p class="rb-nav-caption">RUANG KELAS</p>
<nav><?php foreach (['dashboard'=>'Dashboard','materi'=>'Materi Pembelajaran','kuis'=>'Kuis dan Nilai','forum'=>'Forum Diskusi'] as $key=>$label): ?>
<a href="<?= $key ?>.php" class="rb-nav <?= $active===$key ? 'is-active' : '' ?>" <?= $active===$key ? 'aria-current="page"' : '' ?>><?= rbIcon($key) ?><span><?= $label ?></span></a>
<?php endforeach; ?></nav>
<div class="rb-side-bottom"><div class="rb-note"><span>✦</span><strong>Sedikit demi sedikit,<br>jadi lebih mengerti.</strong><p>Temukan hal baru di kelas hari ini.</p></div>
<form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button class="rb-logout" type="submit"><?= rbIcon('logout') ?> Keluar dari akun</button></form></div>
</aside>
<header class="rb-topbar"><div><span class="rb-eyebrow">RUANG BELAJARMU</span><strong><?= e($title) ?></strong></div><div class="rb-profile"><span class="rb-avatar"><?= e($initial) ?></span><div><b><?= e($name) ?></b><small><?= $userAktif['role']==='pengajar' ? 'Pengajar' : 'Mahasiswa' ?></small></div></div></header>
<?php }
