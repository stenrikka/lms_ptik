<?php
require __DIR__ . '/auth.php';

$isPengajar = $userAktif['role'] === 'pengajar';
$error = '';
$judul = '';
$deskripsi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibPengajar();
    cekToken();

    $judul = trim((string) ($_POST['judul'] ?? ''));
    $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));

    if ($judul === '') {
        $error = 'Judul kuis wajib diisi.';
    } elseif (mb_strlen($judul, 'UTF-8') > 200) {
        $error = 'Judul maksimal 200 karakter.';
    } elseif (mb_strlen($deskripsi, 'UTF-8') > 5000) {
        $error = 'Deskripsi maksimal 5.000 karakter.';
    } else {
        $query = $pdo->prepare(
            'INSERT INTO quizzes (judul, deskripsi, created_by)
             VALUES (?, ?, ?)'
        );

        $query->execute([
            $judul,
            $deskripsi,
            $userAktif['id']
        ]);

        $idBaru = (int) $pdo->lastInsertId();

        header('Location: kelola_kuis.php?id=' . $idBaru);
        exit;
    }
}

if ($isPengajar) {
    $query = $pdo->query(
        'SELECT q.*,
                u.nama AS pembuat,
                (SELECT COUNT(*)
                 FROM quiz_questions s
                 WHERE s.quiz_id = q.id) AS jumlah_soal
         FROM quizzes q
         JOIN users u ON u.id = q.created_by
         ORDER BY q.id DESC'
    );
} else {
    $query = $pdo->prepare(
        "SELECT q.*,
                u.nama AS pembuat,
                a.id AS attempt_id,
                a.nilai,
                (SELECT COUNT(*)
                 FROM quiz_questions s
                 WHERE s.quiz_id = q.id) AS jumlah_soal
         FROM quizzes q
         JOIN users u ON u.id = q.created_by
         LEFT JOIN quiz_attempts a
             ON a.quiz_id = q.id AND a.user_id = ?
         WHERE q.status = 'published'
         ORDER BY q.id DESC"
    );

    $query->execute([$userAktif['id']]);
}

$daftarKuis = $query->fetchAll();

$pesan = $_SESSION['pesan_kuis'] ?? '';
unset($_SESSION['pesan_kuis']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuis LMS PTIK B</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="kuis.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-kuis rb-page-kuis">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("kuis", "Kuis dan Nilai"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">WAKTUNYA BERLATIH</span><h2>Seberapa jauh kamu memahami?</h2><p>Latihan kecil hari ini membantu membangun pemahaman yang lebih kuat.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("kuis") ?></span></section>
    <a href="dashboard.php">← Kembali ke dashboard</a>

    <h1>Kuis Pembelajaran</h1>

    <?php if ($pesan !== ''): ?>
        <p class="pesan-sukses" role="status">
            <?= e($pesan) ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="pesan" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($isPengajar): ?>
        <section class="kartu-kuis">
            <h2>Buat Kuis Baru</h2>

            <form method="post" action="kuis.php">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['csrf']) ?>"
                >

                <label for="judul">Judul kuis</label>
                <input
                    id="judul"
                    name="judul"
                    type="text"
                    maxlength="200"
                    value="<?= e($judul) ?>"
                    required
                >

                <label for="deskripsi">Petunjuk atau deskripsi</label>
                <textarea
                    id="deskripsi"
                    name="deskripsi"
                    rows="4"
                    maxlength="5000"
                ><?= e($deskripsi) ?></textarea>

                <button type="submit">Buat dan Tambahkan Soal</button>
            </form>
        </section>
    <?php else: ?>
        <p class="info">
            Setiap kuis hanya dapat dikirim satu kali.
            Periksa jawaban sebelum menekan tombol kirim.
        </p>
    <?php endif; ?>

    <h2>Daftar Kuis</h2>

    <?php if (!$daftarKuis): ?>
        <p>Belum ada kuis yang tersedia.</p>
    <?php endif; ?>

    <?php foreach ($daftarKuis as $kuis): ?>
        <article class="kartu-kuis">
            <h3><?= e($kuis['judul']) ?></h3>

            <p><?= nl2br(e($kuis['deskripsi'])) ?></p>

            <p>
                Pembuat: <?= e($kuis['pembuat']) ?><br>
                Jumlah soal: <?= (int) $kuis['jumlah_soal'] ?>
            </p>

            <?php if ($isPengajar): ?>
                <span class="status-kuis">
                    <?= $kuis['status'] === 'draft'
                        ? 'Draf'
                        : 'Diterbitkan' ?>
                </span>

                <div class="navigasi">
                    <a
                        class="tautan-tombol"
                        href="kelola_kuis.php?id=<?= (int) $kuis['id'] ?>"
                    >
                        Kelola Kuis
                    </a>

                    <a
                        class="tautan-tombol"
                        href="hasil_kuis.php?id=<?= (int) $kuis['id'] ?>"
                    >
                        Lihat Nilai
                    </a>
                </div>
            <?php elseif (!empty($kuis['attempt_id'])): ?>
                <p>
                    Sudah dikerjakan.
                    Nilai:
                    <strong>
                        <?= number_format((float) $kuis['nilai'], 2, ',', '.') ?>
                    </strong>
                </p>

                <a
                    class="tautan-tombol"
                    href="hasil_kuis.php?id=<?= (int) $kuis['id'] ?>"
                >
                    Lihat Hasil
                </a>
            <?php else: ?>
                <a
                    class="tautan-tombol"
                    href="kerjakan_kuis.php?id=<?= (int) $kuis['id'] ?>"
                >
                    Kerjakan Kuis
                </a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>