<?php
require __DIR__ . '/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$isPengajar = $userAktif['role'] === 'pengajar';

$error = '';
$isiBalasan = '';

if ($id <= 0) {
    http_response_code(400);
    exit('ID topik tidak valid.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekToken();

    $aksiInput = $_POST['aksi'] ?? '';
    $aksi = is_string($aksiInput) ? $aksiInput : '';

    // Penghapusan hanya boleh dilakukan pengajar.
    if (in_array($aksi, ['hapus_topik', 'hapus_balasan'], true)) {
        wajibPengajar();
    }

    try {
        $pdo->beginTransaction();

        // Mengunci topik selama penambahan atau penghapusan.
        $query = $pdo->prepare(
            'SELECT id FROM forum_topics WHERE id = ? FOR UPDATE'
        );
        $query->execute([$id]);

        if (!$query->fetch()) {
            throw new DomainException(
                'Topik tidak ditemukan atau sudah dihapus.'
            );
        }

        $tujuan = 'diskusi.php?id=' . $id;
        $pesan = '';

        if ($aksi === 'balas') {
            $input = $_POST['isi'] ?? '';
            $isiBalasan = is_string($input) ? trim($input) : '';

            if ($isiBalasan === '') {
                throw new DomainException('Balasan tidak boleh kosong.');
            }

            if (mb_strlen($isiBalasan, 'UTF-8') > 10000) {
                throw new DomainException(
                    'Balasan maksimal 10.000 karakter.'
                );
            }

            $query = $pdo->prepare(
                'INSERT INTO forum_replies (topic_id, user_id, isi)
                 VALUES (?, ?, ?)'
            );

            $query->execute([
                $id,
                $userAktif['id'],
                $isiBalasan
            ]);

            $pesan = 'Balasan berhasil dikirim.';
        } elseif ($aksi === 'hapus_balasan') {
            $balasanId = (int) ($_POST['balasan_id'] ?? 0);

            $query = $pdo->prepare(
                'DELETE FROM forum_replies
                 WHERE id = ? AND topic_id = ?'
            );
            $query->execute([$balasanId, $id]);

            if ($query->rowCount() === 0) {
                throw new DomainException(
                    'Balasan tidak ditemukan atau sudah dihapus.'
                );
            }

            $pesan = 'Balasan berhasil dihapus.';
        } elseif ($aksi === 'hapus_topik') {
            // Balasan ikut terhapus melalui ON DELETE CASCADE.
            $query = $pdo->prepare(
                'DELETE FROM forum_topics WHERE id = ?'
            );
            $query->execute([$id]);

            $pesan = 'Topik beserta seluruh balasannya berhasil dihapus.';
            $tujuan = 'forum.php';
        } else {
            throw new DomainException('Tindakan tidak valid.');
        }

        $pdo->commit();

        $_SESSION['pesan_forum'] = $pesan;
        header('Location: ' . $tujuan);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof DomainException) {
            $error = $e->getMessage();
        } else {
            error_log($e->getMessage());
            $error = 'Tindakan gagal diproses. Silakan coba kembali.';
        }
    }
}

$query = $pdo->prepare(
    'SELECT t.*,
            u.nama AS penulis,
            u.role AS peran_penulis
     FROM forum_topics t
     JOIN users u ON u.id = t.user_id
     WHERE t.id = ?'
);
$query->execute([$id]);
$topik = $query->fetch();

if (!$topik) {
    http_response_code(404);
    echo '<p>Topik tidak ditemukan atau sudah dihapus.</p>';
    echo '<p><a href="forum.php">Kembali ke forum</a></p>';
    exit;
}

$query = $pdo->prepare(
    'SELECT r.*,
            u.nama AS penulis,
            u.role AS peran_penulis
     FROM forum_replies r
     JOIN users u ON u.id = r.user_id
     WHERE r.topic_id = ?
     ORDER BY r.created_at ASC, r.id ASC'
);
$query->execute([$id]);
$daftarBalasan = $query->fetchAll();

$pesan = $_SESSION['pesan_forum'] ?? '';
unset($_SESSION['pesan_forum']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($topik['judul']) ?> — Forum LMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="forum.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-forum rb-page-diskusi">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("forum", "Percakapan Kelas"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">BELAJAR BERSAMA</span><h2>Setiap pendapat punya tempat.</h2><p>Baca percakapannya, lalu bagikan pemikiranmu dengan bahasa yang baik.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("forum") ?></span></section>
    <a href="forum.php">← Kembali ke daftar diskusi</a>

    <h1><?= e($topik['judul']) ?></h1>

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

    <article class="forum-kartu">
        <div class="forum-meta">
            <strong><?= e($topik['penulis']) ?></strong>

            <span class="forum-peran <?= $topik['peran_penulis'] === 'pengajar'
                ? 'pengajar'
                : '' ?>">
                <?= e(ucfirst($topik['peran_penulis'])) ?>
            </span>

            <span><?= e($topik['created_at']) ?></span>
        </div>

        <div class="forum-isi"><?= e($topik['isi']) ?></div>

        <?php if ($isPengajar): ?>
            <form
                class="forum-aksi"
                method="post"
                action="diskusi.php?id=<?= $id ?>"
                onsubmit="return confirm('Hapus topik dan seluruh balasannya? Tindakan ini tidak dapat dibatalkan.');"
            >
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['csrf']) ?>"
                >
                <input type="hidden" name="aksi" value="hapus_topik">

                <button class="forum-hapus" type="submit">
                    Hapus Topik
                </button>
            </form>
        <?php endif; ?>
    </article>

    <h2>Balasan (<?= count($daftarBalasan) ?>)</h2>

    <?php if (!$daftarBalasan): ?>
        <p class="info">
            Belum ada balasan. Kamu bisa memulai tanggapan di bawah.
        </p>
    <?php endif; ?>

    <?php foreach ($daftarBalasan as $balasan): ?>
        <article class="forum-kartu <?= $balasan['peran_penulis'] === 'pengajar'
            ? 'forum-balasan-pengajar'
            : '' ?>">

            <div class="forum-meta">
                <strong><?= e($balasan['penulis']) ?></strong>

                <span class="forum-peran <?= $balasan['peran_penulis'] === 'pengajar'
                    ? 'pengajar'
                    : '' ?>">
                    <?= e(ucfirst($balasan['peran_penulis'])) ?>
                </span>

                <span><?= e($balasan['created_at']) ?></span>
            </div>

            <div class="forum-isi"><?= e($balasan['isi']) ?></div>

            <?php if ($isPengajar): ?>
                <form
                    class="forum-aksi"
                    method="post"
                    action="diskusi.php?id=<?= $id ?>"
                    onsubmit="return confirm('Hapus balasan ini?');"
                >
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($_SESSION['csrf']) ?>"
                    >
                    <input
                        type="hidden"
                        name="aksi"
                        value="hapus_balasan"
                    >
                    <input
                        type="hidden"
                        name="balasan_id"
                        value="<?= (int) $balasan['id'] ?>"
                    >

                    <button class="forum-hapus" type="submit">
                        Hapus Balasan
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <section class="forum-form">
        <h2>Tulis Balasan</h2>

        <p>
            Membalas sebagai
            <strong><?= e($userAktif['nama']) ?></strong>.
        </p>

        <form method="post" action="diskusi.php?id=<?= $id ?>">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >
            <input type="hidden" name="aksi" value="balas">

            <label for="isiBalasan">Isi balasan</label>
            <textarea
                id="isiBalasan"
                name="isi"
                rows="5"
                maxlength="10000"
                required
            ><?= e($isiBalasan) ?></textarea>

            <button type="submit">Kirim Balasan</button>
        </form>
    </section>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>