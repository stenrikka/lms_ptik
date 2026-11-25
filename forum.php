<?php
require __DIR__ . '/auth.php';

$error = '';
$judul = '';
$isi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekToken();

    $judulInput = $_POST['judul'] ?? '';
    $isiInput = $_POST['isi'] ?? '';

    $judul = is_string($judulInput) ? trim($judulInput) : '';
    $isi = is_string($isiInput) ? trim($isiInput) : '';

    if ($judul === '' || $isi === '') {
        $error = 'Judul dan isi diskusi wajib diisi.';
    } elseif (mb_strlen($judul, 'UTF-8') > 200) {
        $error = 'Judul maksimal 200 karakter.';
    } elseif (mb_strlen($isi, 'UTF-8') > 10000) {
        $error = 'Isi diskusi maksimal 10.000 karakter.';
    } else {
        try {
            $query = $pdo->prepare(
                'INSERT INTO forum_topics (judul, isi, user_id)
                 VALUES (?, ?, ?)'
            );

            $query->execute([
                $judul,
                $isi,
                $userAktif['id']
            ]);

            $idBaru = (int) $pdo->lastInsertId();

            $_SESSION['pesan_forum'] = 'Topik berhasil dibuat.';

            header('Location: diskusi.php?id=' . $idBaru);
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Topik gagal disimpan. Silakan coba kembali.';
        }
    }
}

$query = $pdo->query(
    'SELECT t.*,
            u.nama AS penulis,
            u.role AS peran_penulis,
            (SELECT COUNT(*)
             FROM forum_replies r
             WHERE r.topic_id = t.id) AS jumlah_balasan
     FROM forum_topics t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC, t.id DESC'
);

$daftarTopik = $query->fetchAll();

$pesan = $_SESSION['pesan_forum'] ?? '';
unset($_SESSION['pesan_forum']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Diskusi LMS PTIK B</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="forum.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-forum rb-page-forum">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("forum", "Forum Diskusi"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">RUANG UNTUK BERTANYA</span><h2>Ide besar berawal dari obrolan.</h2><p>Tanyakan, tanggapi, dan temukan sudut pandang baru bersama kelas.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("forum") ?></span></section>
    <a href="dashboard.php">← Kembali ke dashboard</a>

    <h1>Forum Diskusi</h1>

    <p>
        Bertanya, berbagi pendapat, dan membahas materi pembelajaran
        bersama pengajar dan teman sekelas.
    </p>

    <?php if ($pesan !== ''): ?>
        <p class="forum-sukses" role="status">
            <?= e($pesan) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="pesan" role="alert">
            <?= e($error) ?>
        </p>
    <?php endif; ?>

    <section class="forum-form">
        <h2>Buat Topik Baru</h2>

        <form method="post" action="forum.php">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <label for="judul">Judul diskusi</label>
            <input
                id="judul"
                name="judul"
                type="text"
                maxlength="200"
                placeholder="Contoh: Apa perbedaan scope dan WBS?"
                value="<?= e($judul) ?>"
                required
            >

            <label for="isi">Pertanyaan atau pembahasan</label>
            <textarea
                id="isi"
                name="isi"
                rows="6"
                maxlength="10000"
                required
            ><?= e($isi) ?></textarea>

            <button type="submit">Buat Topik</button>
        </form>
    </section>

    <h2>Daftar Diskusi</h2>

    <p>Total topik: <strong><?= count($daftarTopik) ?></strong></p>

    <?php if (!$daftarTopik): ?>
        <p class="info">
            Belum ada diskusi. Buat topik pertama melalui formulir di atas.
        </p>
    <?php endif; ?>

    <?php foreach ($daftarTopik as $topik): ?>
        <?php
        $ringkasan = mb_substr($topik['isi'], 0, 180, 'UTF-8');

        if (mb_strlen($topik['isi'], 'UTF-8') > 180) {
            $ringkasan .= '…';
        }
        ?>

        <article class="forum-kartu">
            <h3><?= e($topik['judul']) ?></h3>

            <div class="forum-meta">
                <strong><?= e($topik['penulis']) ?></strong>

                <span class="forum-peran <?= $topik['peran_penulis'] === 'pengajar'
                    ? 'pengajar'
                    : '' ?>">
                    <?= e(ucfirst($topik['peran_penulis'])) ?>
                </span>

                <span><?= e($topik['created_at']) ?></span>
            </div>

            <p class="forum-ringkasan"><?= e($ringkasan) ?></p>

            <p>
                Balasan:
                <strong><?= (int) $topik['jumlah_balasan'] ?></strong>
            </p>

            <a
                class="forum-link"
                href="diskusi.php?id=<?= (int) $topik['id'] ?>"
            >
                Buka Diskusi
            </a>
        </article>
    <?php endforeach; ?>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>