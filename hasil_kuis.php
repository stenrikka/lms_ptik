<?php
require __DIR__ . '/auth.php';

$isPengajar = $userAktif['role'] === 'pengajar';
$id = (int) ($_GET['id'] ?? 0);

$query = $pdo->prepare(
    'SELECT * FROM quizzes WHERE id = ?'
);
$query->execute([$id]);
$kuis = $query->fetch();

if (
    !$kuis
    || (!$isPengajar && $kuis['status'] !== 'published')
) {
    http_response_code(404);
    exit('Kuis tidak ditemukan.');
}

$hasil = null;
$daftarHasil = [];

if ($isPengajar) {
    $query = $pdo->prepare(
        'SELECT a.*, u.nama, u.username
         FROM quiz_attempts a
         JOIN users u ON u.id = a.user_id
         WHERE a.quiz_id = ?
         ORDER BY a.nilai DESC, a.submitted_at ASC'
    );
    $query->execute([$id]);
    $daftarHasil = $query->fetchAll();
} else {
    // ID mahasiswa berasal dari sesi, bukan dari URL.
    $query = $pdo->prepare(
        'SELECT * FROM quiz_attempts
         WHERE quiz_id = ? AND user_id = ?'
    );
    $query->execute([$id, $userAktif['id']]);
    $hasil = $query->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Kuis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="kuis.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-kuis rb-page-hasil_kuis">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("kuis", "Hasil Pembelajaran"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">REFLEKSI BELAJAR</span><h2>Lihat hasil, lanjutkan langkah.</h2><p>Gunakan hasil latihan sebagai bekal untuk belajar berikutnya.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("kuis") ?></span></section>
    <a href="kuis.php">← Kembali ke daftar kuis</a>

    <h1>Hasil Kuis</h1>
    <h2><?= e($kuis['judul']) ?></h2>

    <?php if ($isPengajar): ?>
        <p>
            Jumlah mahasiswa yang sudah mengirim:
            <strong><?= count($daftarHasil) ?></strong>
        </p>

        <?php if (!$daftarHasil): ?>
            <p class="info">Belum ada mahasiswa yang mengirim jawaban.</p>
        <?php else: ?>
            <div class="tabel-container">
                <table class="tabel-nilai">
                    <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Benar</th>
                        <th>Nilai</th>
                        <th>Waktu Kirim</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftarHasil as $baris): ?>
                        <tr>
                            <td><?= e($baris['nama']) ?></td>
                            <td><?= e($baris['username']) ?></td>
                            <td>
                                <?= (int) $baris['jumlah_benar'] ?>
                                /
                                <?= (int) $baris['jumlah_soal'] ?>
                            </td>
                            <td>
                                <?= number_format(
                                    (float) $baris['nilai'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                            <td><?= e($baris['submitted_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="navigasi">
            <a class="tautan-tombol"
               href="kelola_kuis.php?id=<?= $id ?>">
                Kembali ke Pengelolaan
            </a>
        </div>
    <?php elseif (!$hasil): ?>
        <p class="info">Kamu belum mengerjakan kuis ini.</p>

        <a class="tautan-tombol"
           href="kerjakan_kuis.php?id=<?= $id ?>">
            Kerjakan Sekarang
        </a>
    <?php else: ?>
        <section class="kartu-kuis">
            <p>Nama: <strong><?= e($userAktif['nama']) ?></strong></p>

            <p>Nilai kamu:</p>

            <div class="nilai-besar">
                <?= number_format((float) $hasil['nilai'], 2, ',', '.') ?>
            </div>

            <p>
                Jawaban benar:
                <strong>
                    <?= (int) $hasil['jumlah_benar'] ?>
                    dari
                    <?= (int) $hasil['jumlah_soal'] ?>
                    soal
                </strong>
            </p>

            <p>
                Jawaban salah atau kosong:
                <?= (int) $hasil['jumlah_soal']
                    - (int) $hasil['jumlah_benar'] ?>
            </p>

            <p>
                Waktu pengiriman:
                <?= e($hasil['submitted_at']) ?>
            </p>

            <p class="info">
                Hasil sudah tersimpan.
                Kuis ini tidak dapat dikerjakan ulang.
            </p>
        </section>
    <?php endif; ?>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>