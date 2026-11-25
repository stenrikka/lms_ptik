<?php
require __DIR__ . '/auth.php';

$isPengajar = $userAktif['role'] === 'pengajar';

$jumlahMateri = (int) $pdo->query(
    'SELECT COUNT(*) FROM materials'
)->fetchColumn();

$jumlahTopik = (int) $pdo->query(
    'SELECT COUNT(*) FROM forum_topics'
)->fetchColumn();

if ($isPengajar) {
    $jumlahKuis = (int) $pdo->query(
        'SELECT COUNT(*) FROM quizzes'
    )->fetchColumn();

    $jumlahMahasiswa = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'"
    )->fetchColumn();

    $jumlahPengerjaan = (int) $pdo->query(
        'SELECT COUNT(*) FROM quiz_attempts'
    )->fetchColumn();
} else {
    $jumlahKuis = (int) $pdo->query(
        "SELECT COUNT(*) FROM quizzes WHERE status = 'published'"
    )->fetchColumn();

    $query = $pdo->prepare(
        'SELECT COUNT(*) AS selesai, AVG(nilai) AS rata_rata
         FROM quiz_attempts WHERE user_id = ?'
    );
    $query->execute([$userAktif['id']]);

    $ringkasan = $query->fetch();
    $jumlahSelesai = (int) $ringkasan['selesai'];
    $rataRata = $ringkasan['rata_rata'];
}

$topikTerbaru = $pdo->query(
    'SELECT t.id, t.judul, u.nama AS penulis
     FROM forum_topics t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC
     LIMIT 3'
)->fetchAll();

$nama = $userAktif['nama'];
$inisial = mb_strtoupper(mb_substr($nama, 0, 1, 'UTF-8'), 'UTF-8');

$zona = new DateTimeZone('Asia/Jakarta');
$sekarang = new DateTimeImmutable('now', $zona);
$jam = (int) $sekarang->format('G');

$sapaan = $jam < 11
    ? 'Selamat pagi'
    : ($jam < 15
        ? 'Selamat siang'
        : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

$bulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April',
    'Mei', 'Juni', 'Juli', 'Agustus',
    'September', 'Oktober', 'November', 'Desember'
];

$tanggal = $sekarang->format('j') . ' '
    . $bulan[(int) $sekarang->format('n')] . ' '
    . $sekarang->format('Y');

// Ikon SVG bawaan, tanpa library eksternal.
function ikonDashboard($namaIkon)
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/>
                        <rect x="14" y="3" width="7" height="7" rx="2"/>
                        <rect x="3" y="14" width="7" height="7" rx="2"/>
                        <rect x="14" y="14" width="7" height="7" rx="2"/>',

        'materi' => '<path d="M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1
                     3-2 6-2 9-1V4c-3-1-6-1-9 1Z"/>
                    <path d="M12 5v15"/>',

        'kuis' => '<rect x="5" y="4" width="14" height="17" rx="2"/>
                   <path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/>',

        'forum' => '<path d="M21 11a8 8 0 0 1-8 8H7l-5 3 2-6
                    a8 8 0 1 1 17-5Z"/>
                    <path d="M8 10h8M8 14h5"/>',

        'users' => '<circle cx="9" cy="8" r="3"/>
                    <path d="M3 21v-2a6 6 0 0 1 12 0v2
                    M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5"/>',

        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',

        'logout' => '<path d="M9 4H4v16h5M9 12h12M17 8l4 4-4 4"/>',

        'check' => '<circle cx="12" cy="12" r="9"/>
                    <path d="m8 12 3 3 5-6"/>',

        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>'
    ];

    return '<svg viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.7"
                stroke-linecap="round" stroke-linejoin="round"
                aria-hidden="true">'
        . ($paths[$namaIkon] ?? $paths['dashboard'])
        . '</svg>';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Dashboard — LMS PTIK B</title>

    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --text: #18243b;
            --muted: #69758b;
            --blue: #3459e6;
            --line: #e8edf5;
            --radius: 22px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; }
        svg { width: 23px; height: 23px; flex-shrink: 0; }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid #ffbe55;
            outline-offset: 5px;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 248px;
            padding: 32px 20px 22px;
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border-right: 1px solid var(--line);
            z-index: 20;
        }

        .brand {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 0 12px 32px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 43px;
            height: 43px;
            border-radius: 14px;
            background: var(--blue);
            color: white;
            box-shadow: 0 6px 16px #3459e624;
        }

        .brand strong {
            display: block;
            font-size: 20px;
            letter-spacing: -.6px;
        }

        .brand small {
            display: block;
            font-size: 11px;
            color: var(--muted);
            letter-spacing: 1.2px;
        }

        .nav-label {
            margin: 8px 16px 12px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 16px;
            margin: 5px 0;
            border-radius: 13px;
            color: #67738a;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s, color .2s, transform .2s;
        }

        .nav-link:hover {
            background: #f3f6ff;
            color: var(--blue);
            transform: translateX(3px);
        }

        .nav-link.active {
            color: var(--blue);
            background: #edf1ff;
        }

        .sidebar-bottom { margin-top: auto; padding-top: 30px; }

        .class-note {
            padding: 18px;
            margin-bottom: 18px;
            background: #f6f8fc;
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .class-note strong { display: block; font-size: 13px; }
        .class-note p { margin: 5px 0 0; font-size: 12px; color: var(--muted); }

        .logout {
            display: flex;
            gap: 12px;
            align-items: center;
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 12px;
            color: #a34b55;
            background: transparent;
            text-align: left;
        }

        .logout:hover { background: #fff1f2; }

        .main {
            margin-left: 248px;
            padding: 30px 42px 45px;
            max-width: 1800px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .eyebrow {
            margin: 0 0 4px;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: 27px;
            letter-spacing: -.8px;
        }

        .topbar-right { display: flex; align-items: center; gap: 22px; }
        .date { color: var(--muted); font-size: 13px; }

        .profile { display: flex; align-items: center; gap: 11px; }
        .profile strong { display: block; font-size: 13px; max-width: 220px; overflow-wrap: anywhere; }
        .profile small { display: block; color: var(--muted); font-size: 11px; }

        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #ffead3;
            color: #a36326;
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        .hero {
            position: relative;
            overflow: hidden;
            min-height: 250px;
            padding: 34px 38px;
            border-radius: var(--radius);
            background: linear-gradient(115deg, #243fa8, #4063e6 68%, #718bfa);
            color: white;
        }

        .hero-content { position: relative; z-index: 2; max-width: 640px; }
        .hero-tag { font-size: 11px; letter-spacing: 1.8px; opacity: .85; }
        .hero h2 { margin: 12px 0 10px; font-size: clamp(25px, 3vw, 36px); line-height: 1.25; letter-spacing: -.8px; overflow-wrap: anywhere; }
        .hero p { margin: 0 0 22px; max-width: 470px; color: #e0e7ff; font-size: 14px; }

        .hero-button {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: white;
            color: #2847b5;
            border-radius: 11px;
            padding: 10px 17px;
            font-size: 13px;
            font-weight: 700;
            transition: transform .2s, box-shadow .2s;
        }

        .hero-button:hover { transform: translateY(-3px); box-shadow: 0 8px 20px #122f8a40; }

        .hero-art {
            position: absolute;
            width: 300px;
            height: 300px;
            right: -25px;
            top: -20px;
            border: 45px solid #ffffff0d;
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-art::before {
            content: "";
            position: absolute;
            width: 130px;
            height: 130px;
            inset: 40px;
            border: 1px solid #ffffff38;
            border-radius: 32px;
            transform: rotate(25deg);
        }

        .hero-art::after {
            content: "";
            position: absolute;
            width: 50px;
            height: 50px;
            right: 20px;
            bottom: 35px;
            background: #ffffff25;
            border-radius: 14px;
            transform: rotate(-15deg);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin: 22px 0 30px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 23px 20px;
            border-radius: 18px;
            background: var(--surface);
            border: 1px solid var(--line);
            transition: transform .2s, box-shadow .2s;
            min-width: 0;
        }

        .stat:hover { transform: translateY(-4px); box-shadow: 0 10px 25px #263c6509; }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .blue { background: #eef2ff; color: #4162df; }
        .purple { background: #f3edff; color: #8c57d4; }
        .orange { background: #fff1e4; color: #bd6f25; }
        .green { background: #e7f6ef; color: #248862; }

        .stat-value { display: block; font-size: 28px; font-weight: 700; line-height: 1.15; letter-spacing: -.8px; }
        .stat-label { display: block; color: var(--muted); font-size: 12px; margin-top: 5px; }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 14px;
            margin-bottom: 15px;
        }

        .section-heading h2 { margin: 0; font-size: 18px; letter-spacing: -.4px; }
        .section-heading p { margin: 0; color: var(--muted); font-size: 12px; }
        .section-heading a { color: var(--blue); font-size: 12px; font-weight: 600; }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .menu-card {
            position: relative;
            display: flex;
            flex-direction: column;
            padding: 25px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--surface);
            transition: transform .25s, box-shadow .25s, border-color .25s;
        }

        .menu-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 35px #263c6510;
            border-color: #cbd6fb;
        }

        .menu-card h3 { margin: 20px 0 8px; font-size: 17px; }
        .menu-card p { margin: 0 0 25px; color: var(--muted); font-size: 13px; }

        .card-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            color: var(--blue);
            font-size: 12px;
            font-weight: 700;
        }

        .card-link svg { width: 19px; transition: transform .2s; }
        .menu-card:hover .card-link svg { transform: translateX(4px); }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
            margin-top: 26px;
        }

        .panel {
            padding: 25px;
            border-radius: 20px;
            background: var(--surface);
            border: 1px solid var(--line);
            min-width: 0;
        }

        .topic {
            display: flex;
            gap: 13px;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--line);
        }

        .topic:last-child { border-bottom: 0; padding-bottom: 0; }
        .topic .icon-box { width: 36px; height: 36px; border-radius: 11px; }
        .topic svg { width: 18px; height: 18px; }
        .topic strong { display: block; font-size: 13px; overflow-wrap: anywhere; }
        .topic small { color: var(--muted); font-size: 11px; }
        .topic:hover strong { color: var(--blue); }

        .summary-number { margin: 18px 0 4px; font-size: 38px; font-weight: 700; letter-spacing: -1px; }
        .muted { color: var(--muted); font-size: 13px; }
        .summary-note { display: flex; gap: 9px; align-items: flex-start; margin-top: 20px; color: #38745e; font-size: 12px; }
        .summary-note svg { width: 18px; height: 18px; }

        footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 10px;
            margin-top: 28px;
            font-size: 11px;
            color: var(--muted);
        }

        .mobile-button { display: none; }
        .overlay { display: none; }

        .reveal {
            animation: masuk .6s cubic-bezier(.2,.7,.2,1) both;
            animation-delay: var(--delay, 0ms);
        }

        @keyframes masuk {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (min-width: 1600px) {
            .main { padding-left: 60px; padding-right: 60px; }
        }

        @media (max-width: 1200px) {
            .main { padding: 28px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .date { display: none; }
            .hero-art { opacity: .5; }
        }

        @media (max-width: 900px) {
            .sidebar { width: 220px; }
            .main { margin-left: 220px; padding: 24px; }
            .menu-grid, .bottom-grid { grid-template-columns: 1fr; }
            .hero { padding: 28px; }
            .hero-art { right: -150px; }
            .profile-text { display: none; }
            .section-heading { flex-wrap: wrap; }
        }

        @media (max-width: 680px) {
            .sidebar {
                width: 248px;
                transform: translateX(-100%);
                transition: transform .25s;
            }

            body.menu-open .sidebar { transform: translateX(0); }
            .main { margin-left: 0; padding: 20px 16px; }
            .topbar { gap: 10px; margin-bottom: 20px; }
            .topbar-right { gap: 12px; }
            h1 { font-size: 23px; }

            .mobile-button {
                display: grid;
                place-items: center;
                width: 40px;
                height: 40px;
                border: 1px solid var(--line);
                border-radius: 11px;
                background: white;
                color: var(--text);
            }

            body.menu-open .overlay {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 19;
                border: 0;
                background: #0f172a66;
            }

            .hero { padding: 26px 23px; }
            .hero h2 { font-size: 27px; }
            .stats { gap: 10px; }
            .stat { padding: 17px 13px; gap: 10px; }
            .stat .icon-box { width: 36px; height: 36px; border-radius: 11px; }
            .stat-value { font-size: 24px; }
            .stat-label { font-size: 11px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>

<body class="rb-dashboard">
<button class="overlay" id="overlay" type="button"
        aria-label="Tutup menu navigasi" tabindex="-1"></button>

<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="brand" aria-label="LMS PTIK B beranda">
        <span class="brand-mark"><?= ikonDashboard('materi') ?></span>
        <span>
            <strong>Ruang Belajar</strong>
            <small>LMS · PTIK B</small>
        </span>
    </a>

    <p class="nav-label">MENU UTAMA</p>

    <nav aria-label="Navigasi utama">
        <a class="nav-link active" href="dashboard.php" aria-current="page">
            <?= ikonDashboard('dashboard') ?> Dashboard
        </a>
        <a class="nav-link" href="materi.php">
            <?= ikonDashboard('materi') ?> Materi Pembelajaran
        </a>
        <a class="nav-link" href="kuis.php">
            <?= ikonDashboard('kuis') ?> Kuis dan Nilai
        </a>
        <a class="nav-link" href="forum.php">
            <?= ikonDashboard('forum') ?> Forum Diskusi
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="class-note">
            <strong>Kelas PTIK B</strong>
            <p>Satu ruang untuk belajar, berlatih, dan bertukar pemikiran.</p>
        </div>

        <form method="post" action="logout.php">
            <input type="hidden" name="csrf"
                   value="<?= e($_SESSION['csrf']) ?>">
            <button type="submit" class="logout">
                <?= ikonDashboard('logout') ?> Keluar dari akun
            </button>
        </form>
    </div>
</aside>

<main class="main">
    <header class="topbar reveal">
        <div>
            <p class="eyebrow">RUANG BELAJARMU</p>
            <h1>Dashboard</h1>
        </div>

        <div class="topbar-right">
            <span class="date"><?= e($tanggal) ?></span>

            <div class="profile">
                <span class="avatar" aria-hidden="true"><?= e($inisial) ?></span>
                <div class="profile-text">
                    <strong><?= e($nama) ?></strong>
                    <small><?= $isPengajar ? 'Pengajar' : 'Mahasiswa' ?></small>
                </div>
            </div>

            <button class="mobile-button" id="menuButton" type="button"
                    aria-label="Buka menu navigasi"
                    aria-controls="sidebar" aria-expanded="false">
                <?= ikonDashboard('menu') ?>
            </button>
        </div>
    </header>

    <section class="hero reveal" style="--delay: 60ms">
        <div class="hero-art" aria-hidden="true"></div>

        <div class="hero-content">
            <span class="hero-tag">BELAJAR · BERLATIH · BERTUMBUH</span>

            <h2><?= e($sapaan) ?>,<br><?= e($nama) ?>.</h2>

            <p>
                <?= $isPengajar
                    ? 'Siapkan pembelajaran yang bermakna. Kelola materi, pantau hasil kuis, dan dampingi diskusi kelas.'
                    : 'Mulai dari satu hal baru hari ini. Pelajari materi, uji pemahamanmu, dan lanjutkan diskusi bersama.' ?>
            </p>

            <a class="hero-button" href="materi.php">
                <?= $isPengajar ? 'Kelola materi kelas' : 'Mulai belajar' ?>
                <?= ikonDashboard('arrow') ?>
            </a>
        </div>
    </section>

    <section class="stats" aria-label="Statistik kelas">
        <article class="stat reveal" style="--delay: 120ms">
            <span class="icon-box blue"><?= ikonDashboard('materi') ?></span>
            <div>
                <span class="stat-value"><?= $jumlahMateri ?></span>
                <span class="stat-label">Materi tersedia</span>
            </div>
        </article>

        <article class="stat reveal" style="--delay: 170ms">
            <span class="icon-box purple"><?= ikonDashboard('kuis') ?></span>
            <div>
                <span class="stat-value"><?= $jumlahKuis ?></span>
                <span class="stat-label">
                    <?= $isPengajar ? 'Kuis, termasuk draf' : 'Kuis tersedia' ?>
                </span>
            </div>
        </article>

        <article class="stat reveal" style="--delay: 220ms">
            <span class="icon-box orange"><?= ikonDashboard('forum') ?></span>
            <div>
                <span class="stat-value"><?= $jumlahTopik ?></span>
                <span class="stat-label">Topik diskusi</span>
            </div>
        </article>

        <article class="stat reveal" style="--delay: 270ms">
            <span class="icon-box green">
                <?= ikonDashboard($isPengajar ? 'users' : 'check') ?>
            </span>
            <div>
                <span class="stat-value">
                    <?= $isPengajar ? $jumlahMahasiswa : $jumlahSelesai ?>
                </span>
                <span class="stat-label">
                    <?= $isPengajar ? 'Mahasiswa' : 'Kuis selesai' ?>
                </span>
            </div>
        </article>
    </section>

    <section class="reveal" style="--delay: 320ms">
        <div class="section-heading">
            <h2>Semua kebutuhan belajarmu</h2>
            <p>Pilih aktivitas untuk melanjutkan</p>
        </div>

        <div class="menu-grid">
            <a href="materi.php" class="menu-card">
                <span class="icon-box blue"><?= ikonDashboard('materi') ?></span>
                <h3>Materi Pembelajaran</h3>
                <p>
                    <?= $isPengajar
                        ? 'Susun dan bagikan materi serta sumber belajar untuk kelas.'
                        : 'Baca pembahasan dan temukan sumber belajar tambahan.' ?>
                </p>
                <span class="card-link">
                    <?= $isPengajar ? 'Kelola materi' : 'Jelajahi materi' ?>
                    <?= ikonDashboard('arrow') ?>
                </span>
            </a>

            <a href="kuis.php" class="menu-card">
                <span class="icon-box purple"><?= ikonDashboard('kuis') ?></span>
                <h3>Kuis dan Evaluasi</h3>
                <p>
                    <?= $isPengajar
                        ? 'Buat soal, terbitkan kuis, dan lihat hasil pengerjaan mahasiswa.'
                        : 'Uji pemahaman lewat kuis dan lihat hasil belajarmu.' ?>
                </p>
                <span class="card-link">
                    <?= $isPengajar ? 'Kelola kuis' : 'Buka kuis dan nilai' ?>
                    <?= ikonDashboard('arrow') ?>
                </span>
            </a>

            <a href="forum.php" class="menu-card">
                <span class="icon-box orange"><?= ikonDashboard('forum') ?></span>
                <h3>Forum Diskusi</h3>
                <p>Tanyakan hal yang belum jelas dan bagikan pemikiran bersama kelas.</p>
                <span class="card-link">
                    Bergabung dalam diskusi
                    <?= ikonDashboard('arrow') ?>
                </span>
            </a>
        </div>
    </section>

    <div class="bottom-grid reveal" style="--delay: 380ms">
        <section class="panel">
            <div class="section-heading">
                <h2>Topik terbaru</h2>
                <a href="forum.php">Lihat semua →</a>
            </div>

            <?php if (!$topikTerbaru): ?>
                <p class="muted">
                    Belum ada topik diskusi. Mulai percakapan pertama di forum kelas.
                </p>
            <?php endif; ?>

            <?php foreach ($topikTerbaru as $topik): ?>
                <a class="topic" href="diskusi.php?id=<?= (int) $topik['id'] ?>">
                    <span class="icon-box orange">
                        <?= ikonDashboard('forum') ?>
                    </span>
                    <div>
                        <strong><?= e($topik['judul']) ?></strong>
                        <small>Oleh <?= e($topik['penulis']) ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="panel">
            <div class="section-heading">
                <h2><?= $isPengajar ? 'Partisipasi kuis' : 'Hasil belajarmu' ?></h2>
            </div>

            <?php if ($isPengajar): ?>
                <div class="summary-number"><?= $jumlahPengerjaan ?></div>
                <div class="muted">Total pengiriman jawaban yang sudah dinilai.</div>

                <div class="summary-note">
                    <?= ikonDashboard('check') ?>
                    <span>Lihat rincian nilai peserta melalui menu kuis.</span>
                </div>
            <?php else: ?>
                <div class="summary-number">
                    <?= $rataRata === null
                        ? '—'
                        : number_format((float) $rataRata, 2, ',', '.') ?>
                </div>

                <div class="muted">
                    <?= $rataRata === null
                        ? 'Belum ada nilai. Mulai dari kuis yang tersedia.'
                        : 'Rata-rata nilai dari kuis yang sudah kamu selesaikan.' ?>
                </div>

                <div class="summary-note">
                    <?= ikonDashboard('check') ?>
                    <span>
                        <?= $jumlahSelesai ?> dari <?= $jumlahKuis ?> kuis tersedia
                        sudah selesai.
                    </span>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <span>LMS PTIK B · Ruang Belajar</span>
        <span>Materi, latihan, dan diskusi dalam satu tempat.</span>
    </footer>
</main>

<script>
const menuButton = document.getElementById('menuButton');
const overlay = document.getElementById('overlay');
const sidebar = document.getElementById('sidebar');
const mobile = window.matchMedia('(max-width: 680px)');

function aturMenu(buka) {
    document.body.classList.toggle('menu-open', buka);
    menuButton.setAttribute('aria-expanded', String(buka));
    menuButton.setAttribute(
        'aria-label',
        buka ? 'Tutup menu navigasi' : 'Buka menu navigasi'
    );

    sidebar.inert = mobile.matches && !buka;
}

menuButton.addEventListener('click', function () {
    aturMenu(!document.body.classList.contains('menu-open'));
});

overlay.addEventListener('click', function () {
    aturMenu(false);
    menuButton.focus();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        aturMenu(false);
    }
});

mobile.addEventListener('change', function () {
    aturMenu(false);
});

aturMenu(false);
</script>
</body>
</html>