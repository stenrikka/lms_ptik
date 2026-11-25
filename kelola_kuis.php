<?php
require __DIR__ . '/auth.php';
wajibPengajar();

$id = (int) ($_GET['id'] ?? 0);
$error = '';

if ($id <= 0) {
    http_response_code(400);
    exit('ID kuis tidak valid.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekToken();
    $aksi = (string) ($_POST['aksi'] ?? '');

    try {
        $pdo->beginTransaction();

        // Mengunci baris kuis selama perubahan berlangsung.
        $query = $pdo->prepare(
            'SELECT * FROM quizzes WHERE id = ? FOR UPDATE'
        );
        $query->execute([$id]);
        $kuis = $query->fetch();

        if (!$kuis) {
            throw new DomainException('Kuis tidak ditemukan.');
        }

        if ($kuis['status'] !== 'draft') {
            throw new DomainException(
                'Kuis sudah diterbitkan. Isinya tidak dapat diubah.'
            );
        }

        $tujuan = 'kelola_kuis.php?id=' . $id;
        $pesan = '';

        if ($aksi === 'info') {
            $judul = trim((string) ($_POST['judul'] ?? ''));
            $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));

            if ($judul === '' || mb_strlen($judul, 'UTF-8') > 200) {
                throw new DomainException(
                    'Judul wajib diisi dan maksimal 200 karakter.'
                );
            }

            if (mb_strlen($deskripsi, 'UTF-8') > 5000) {
                throw new DomainException(
                    'Deskripsi maksimal 5.000 karakter.'
                );
            }

            $query = $pdo->prepare(
                'UPDATE quizzes SET judul = ?, deskripsi = ?
                 WHERE id = ?'
            );
            $query->execute([$judul, $deskripsi, $id]);

            $pesan = 'Informasi kuis berhasil diperbarui.';
        } elseif ($aksi === 'tambah_soal') {
            $pertanyaan = trim(
                (string) ($_POST['pertanyaan'] ?? '')
            );

            $opsi = [];

            foreach (['a', 'b', 'c', 'd'] as $huruf) {
                $opsi[$huruf] = trim(
                    (string) ($_POST['opsi_' . $huruf] ?? '')
                );

                if (
                    $opsi[$huruf] === ''
                    || mb_strlen($opsi[$huruf], 'UTF-8') > 500
                ) {
                    throw new DomainException(
                        'Semua pilihan wajib diisi, maksimal 500 karakter.'
                    );
                }
            }

            $kunci = (string) ($_POST['kunci'] ?? '');

            if (
                $pertanyaan === ''
                || mb_strlen($pertanyaan, 'UTF-8') > 5000
            ) {
                throw new DomainException(
                    'Pertanyaan wajib diisi, maksimal 5.000 karakter.'
                );
            }

            if (!in_array($kunci, ['A', 'B', 'C', 'D'], true)) {
                throw new DomainException('Pilih kunci jawaban yang valid.');
            }

            $query = $pdo->prepare(
                'SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?'
            );
            $query->execute([$id]);

            if ((int) $query->fetchColumn() >= 50) {
                throw new DomainException(
                    'Versi ini membatasi satu kuis maksimal 50 soal.'
                );
            }

            $query = $pdo->prepare(
                'INSERT INTO quiz_questions
                 (quiz_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $query->execute([
                $id,
                $pertanyaan,
                $opsi['a'],
                $opsi['b'],
                $opsi['c'],
                $opsi['d'],
                $kunci
            ]);

            $pesan = 'Soal berhasil ditambahkan.';
        } elseif ($aksi === 'hapus_soal') {
            $soalId = (int) ($_POST['soal_id'] ?? 0);

            $query = $pdo->prepare(
                'DELETE FROM quiz_questions
                 WHERE id = ? AND quiz_id = ?'
            );
            $query->execute([$soalId, $id]);

            $pesan = 'Soal dihapus jika masih tersedia.';
        } elseif ($aksi === 'terbitkan') {
            $query = $pdo->prepare(
                'SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?'
            );
            $query->execute([$id]);

            if ((int) $query->fetchColumn() === 0) {
                throw new DomainException(
                    'Tambahkan minimal satu soal sebelum menerbitkan kuis.'
                );
            }

            $query = $pdo->prepare(
                "UPDATE quizzes SET status = 'published' WHERE id = ?"
            );
            $query->execute([$id]);

            $pesan = 'Kuis diterbitkan dan sudah dapat dikerjakan mahasiswa.';
        } elseif ($aksi === 'hapus_kuis') {
            $query = $pdo->prepare(
                'DELETE FROM quizzes WHERE id = ?'
            );
            $query->execute([$id]);

            $pesan = 'Kuis draf berhasil dihapus.';
            $tujuan = 'kuis.php';
        } else {
            throw new DomainException('Tindakan tidak valid.');
        }

        $pdo->commit();

        $_SESSION['pesan_kuis'] = $pesan;
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
            $error = 'Perubahan gagal disimpan. Silakan coba kembali.';
        }
    }
}

$query = $pdo->prepare('SELECT * FROM quizzes WHERE id = ?');
$query->execute([$id]);
$kuis = $query->fetch();

if (!$kuis) {
    http_response_code(404);
    exit('Kuis tidak ditemukan.');
}

$query = $pdo->prepare(
    'SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id'
);
$query->execute([$id]);
$daftarSoal = $query->fetchAll();

$pesan = $_SESSION['pesan_kuis'] ?? '';
unset($_SESSION['pesan_kuis']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kuis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="kuis.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-kuis rb-page-kelola_kuis">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("kuis", "Kelola Kuis"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">RUANG PENGAJAR</span><h2>Rancang latihan yang bermakna.</h2><p>Susun pertanyaan yang membantu mahasiswa memahami materi.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("kuis") ?></span></section>
    <a href="kuis.php">← Kembali ke daftar kuis</a>

    <h1><?= e($kuis['judul']) ?></h1>

    <p>
        Status:
        <strong>
            <?= $kuis['status'] === 'draft' ? 'Draf' : 'Diterbitkan' ?>
        </strong>
        | Jumlah soal: <?= count($daftarSoal) ?>
    </p>

    <?php if ($pesan !== ''): ?>
        <p class="pesan-sukses" role="status"><?= e($pesan) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="pesan" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($kuis['status'] === 'draft'): ?>
        <section class="kartu-kuis">
            <h2>Informasi Kuis</h2>

            <form method="post">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="info">

                <label for="judul">Judul</label>
                <input id="judul" name="judul"
                       value="<?= e($kuis['judul']) ?>"
                       maxlength="200" required>

                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi"
                          rows="3" maxlength="5000"
                ><?= e($kuis['deskripsi']) ?></textarea>

                <button type="submit">Simpan Informasi</button>
            </form>
        </section>

        <section class="kartu-kuis">
            <h2>Tambah Soal</h2>

            <form method="post">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="tambah_soal">

                <label for="pertanyaan">Pertanyaan</label>
                <textarea id="pertanyaan" name="pertanyaan"
                          rows="4" maxlength="5000" required></textarea>

                <?php foreach (['a', 'b', 'c', 'd'] as $huruf): ?>
                    <label for="opsi_<?= $huruf ?>">
                        Pilihan <?= strtoupper($huruf) ?>
                    </label>
                    <input
                        id="opsi_<?= $huruf ?>"
                        name="opsi_<?= $huruf ?>"
                        maxlength="500"
                        required
                    >
                <?php endforeach; ?>

                <label for="kunci">Kunci jawaban</label>
                <select id="kunci" name="kunci" required>
                    <option value="">Pilih jawaban yang benar</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>

                <button type="submit">Simpan Soal</button>
            </form>
        </section>
    <?php else: ?>
        <p class="info">
            Kuis sudah diterbitkan. Soal dikunci agar hasil penilaian
            tidak berubah. Buat kuis baru jika diperlukan revisi.
        </p>
    <?php endif; ?>

    <h2>Daftar Soal</h2>

    <?php if (!$daftarSoal): ?>
        <p>Belum ada soal.</p>
    <?php endif; ?>

    <?php foreach ($daftarSoal as $nomor => $soal): ?>
        <article class="kartu-kuis">
            <h3>Soal <?= $nomor + 1 ?></h3>

            <p class="teks-soal"><?= e($soal['pertanyaan']) ?></p>

            <?php foreach (['a', 'b', 'c', 'd'] as $huruf): ?>
                <p>
                    <strong><?= strtoupper($huruf) ?>.</strong>
                    <?= e($soal['opsi_' . $huruf]) ?>
                </p>
            <?php endforeach; ?>

            <p><strong>Kunci: <?= e($soal['kunci']) ?></strong></p>

            <?php if ($kuis['status'] === 'draft'): ?>
                <form method="post"
                      onsubmit="return confirm('Hapus soal ini?');">
                    <input type="hidden" name="csrf"
                           value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="aksi" value="hapus_soal">
                    <input type="hidden" name="soal_id"
                           value="<?= (int) $soal['id'] ?>">

                    <button class="tombol-hapus" type="submit">
                        Hapus Soal
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if ($kuis['status'] === 'draft'): ?>
        <section class="kartu-kuis">
            <h2>Selesaikan Kuis</h2>
            <p>
                Periksa semua pertanyaan, pilihan, dan kunci.
                Setelah diterbitkan, isi kuis tidak dapat diubah.
            </p>

            <form method="post"
                  onsubmit="return confirm('Terbitkan dan kunci kuis ini?');">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="terbitkan">
                <button type="submit">Terbitkan Kuis</button>
            </form>

            <form method="post"
                  onsubmit="return confirm('Hapus kuis draf beserta seluruh soalnya?');">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="hapus_kuis">
                <button class="tombol-hapus" type="submit">
                    Hapus Kuis Draf
                </button>
            </form>
        </section>
    <?php endif; ?>

    <div class="navigasi">
        <a class="tautan-tombol"
           href="hasil_kuis.php?id=<?= $id ?>">
            Lihat Nilai Peserta
        </a>
    </div>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>