<?php
require __DIR__ . '/auth.php';

$isPengajar = $userAktif['role'] === 'pengajar';
$error = '';

$form = [
    'id' => 0,
    'judul' => '',
    'isi' => '',
    'tautan' => ''
];

// Menambah, mengubah, atau menghapus materi.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hak akses diperiksa di server.
    wajibPengajar();
    cekToken();

    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($aksi === 'hapus') {
        if ($id <= 0) {
            http_response_code(400);
            exit('ID materi tidak valid.');
        }

        $query = $pdo->prepare(
            'DELETE FROM materials WHERE id = ?'
        );
        $query->execute([$id]);

        $_SESSION['pesan_materi'] = $query->rowCount() > 0
            ? 'Materi berhasil dihapus.'
            : 'Materi tidak ditemukan atau sudah dihapus.';

        header('Location: materi.php');
        exit;
    }

    if ($aksi !== 'simpan') {
        http_response_code(400);
        exit('Tindakan tidak valid.');
    }

    $form = [
        'id' => $id,
        'judul' => trim((string) ($_POST['judul'] ?? '')),
        'isi' => trim((string) ($_POST['isi'] ?? '')),
        'tautan' => trim((string) ($_POST['tautan'] ?? ''))
    ];

    // Memeriksa data sebelum disimpan.
    if ($id < 0) {
        $error = 'ID materi tidak valid.';
    } elseif ($form['judul'] === '' || $form['isi'] === '') {
        $error = 'Judul dan isi materi wajib diisi.';
    } elseif (mb_strlen($form['judul'], 'UTF-8') > 200) {
        $error = 'Judul maksimal 200 karakter.';
    } elseif (mb_strlen($form['isi'], 'UTF-8') > 50000) {
        $error = 'Isi materi maksimal 50.000 karakter.';
    } elseif (strlen($form['tautan']) > 2048) {
        $error = 'Tautan terlalu panjang.';
    }

    // Tautan hanya boleh memakai http atau https.
    if ($error === '' && $form['tautan'] !== '') {
        $skema = strtolower(
            (string) parse_url($form['tautan'], PHP_URL_SCHEME)
        );

        if (
            !filter_var($form['tautan'], FILTER_VALIDATE_URL)
            || !in_array($skema, ['http', 'https'], true)
        ) {
            $error = 'Gunakan tautan lengkap dengan http:// atau https://.';
        }
    }

    if ($error === '') {
        if ($id > 0) {
            $cek = $pdo->prepare(
                'SELECT id FROM materials WHERE id = ?'
            );
            $cek->execute([$id]);

            if (!$cek->fetch()) {
                http_response_code(404);
                exit('Materi yang ingin diubah tidak ditemukan.');
            }

            $query = $pdo->prepare(
                'UPDATE materials
                 SET judul = ?, isi = ?, tautan = ?
                 WHERE id = ?'
            );

            $query->execute([
                $form['judul'],
                $form['isi'],
                $form['tautan'] !== '' ? $form['tautan'] : null,
                $id
            ]);

            $_SESSION['pesan_materi'] = 'Materi berhasil diperbarui.';
        } else {
            $query = $pdo->prepare(
                'INSERT INTO materials
                    (judul, isi, tautan, created_by)
                 VALUES (?, ?, ?, ?)'
            );

            $query->execute([
                $form['judul'],
                $form['isi'],
                $form['tautan'] !== '' ? $form['tautan'] : null,
                $userAktif['id']
            ]);

            $_SESSION['pesan_materi'] = 'Materi berhasil ditambahkan.';
        }

        header('Location: materi.php');
        exit;
    }
}

// Mengambil materi untuk formulir edit.
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['edit'])
) {
    wajibPengajar();

    $idEdit = (int) $_GET['edit'];

    $query = $pdo->prepare(
        'SELECT id, judul, isi, tautan
         FROM materials WHERE id = ?'
    );
    $query->execute([$idEdit]);

    $materiEdit = $query->fetch();

    if (!$materiEdit) {
        http_response_code(404);
        exit('Materi tidak ditemukan.');
    }

    $form = $materiEdit;
    $form['tautan'] = $form['tautan'] ?? '';
}

// Mengambil daftar materi beserta nama pembuat.
$query = $pdo->query(
    'SELECT materials.*, users.nama AS pembuat
     FROM materials
     JOIN users ON users.id = materials.created_by
     ORDER BY materials.created_at DESC, materials.id DESC'
);
$daftarMateri = $query->fetchAll();

// Pesan ditampilkan satu kali setelah tindakan berhasil.
$pesan = $_SESSION['pesan_materi'] ?? '';
unset($_SESSION['pesan_materi']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Pembelajaran</title>
    <link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-materi rb-page-materi">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("materi", "Materi Pembelajaran"); ?>
    <main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">EKSPLORASI PENGETAHUAN</span><h2>Satu bacaan, banyak pemahaman.</h2><p>Temukan materi kelas dan sumber belajar untuk langkah berikutnya.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("materi") ?></span></section>
        <a href="dashboard.php">← Kembali ke dashboard</a>

        <h1>Materi Pembelajaran</h1>

        <p>
            Login sebagai
            <strong><?= e($userAktif['nama']) ?></strong>
            — <?= e($userAktif['role']) ?>.
        </p>

        <?php if ($pesan !== ''): ?>
            <p class="sukses" role="status">
                <?= e($pesan) ?>
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="pesan" role="alert">
                <?= e($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($isPengajar): ?>
            <section class="panel">
                <h2>
                    <?= $form['id'] > 0
                        ? 'Edit Materi'
                        : 'Tambah Materi' ?>
                </h2>

                <form method="post" action="materi.php">
                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($_SESSION['csrf']) ?>"
                    >
                    <input type="hidden" name="aksi" value="simpan">
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $form['id'] ?>"
                    >

                    <label for="judul">Judul materi</label>
                    <input
                        type="text"
                        id="judul"
                        name="judul"
                        maxlength="200"
                        value="<?= e($form['judul']) ?>"
                        required
                    >

                    <label for="isi">Isi materi</label>
                    <textarea
                        id="isi"
                        name="isi"
                        rows="8"
                        maxlength="50000"
                        required
                    ><?= e($form['isi']) ?></textarea>

                    <label for="tautan">Tautan tambahan (opsional)</label>
                    <input
                        type="url"
                        id="tautan"
                        name="tautan"
                        maxlength="2048"
                        placeholder="https://..."
                        value="<?= e($form['tautan']) ?>"
                    >

                    <button type="submit">
                        <?= $form['id'] > 0
                            ? 'Simpan Perubahan'
                            : 'Tambah Materi' ?>
                    </button>

                    <?php if ($form['id'] > 0): ?>
                        <a class="batal" href="materi.php">Batal edit</a>
                    <?php endif; ?>
                </form>
            </section>
        <?php endif; ?>

        <h2>Daftar Materi</h2>

        <?php if (count($daftarMateri) === 0): ?>
            <p class="info">Belum ada materi yang ditambahkan.</p>
        <?php endif; ?>

        <?php foreach ($daftarMateri as $materi): ?>
            <article class="kartu-materi">
                <h3><?= e($materi['judul']) ?></h3>

                <p class="keterangan">
                    Dibuat oleh <?= e($materi['pembuat']) ?>
                    | <?= e($materi['created_at']) ?>
                </p>

                <div class="isi-materi">
                    <?= nl2br(e($materi['isi'])) ?>
                </div>

                <?php if (!empty($materi['tautan'])): ?>
                    <p>
                        <a
                            href="<?= e($materi['tautan']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Buka sumber tambahan ↗
                        </a>
                    </p>
                <?php endif; ?>

                <?php if ($isPengajar): ?>
                    <div class="aksi-materi">
                        <a href="materi.php?edit=<?= (int) $materi['id'] ?>">
                            Edit materi
                        </a>

                        <form
                            method="post"
                            action="materi.php"
                            onsubmit="return confirm('Hapus materi ini?');"
                        >
                            <input
                                type="hidden"
                                name="csrf"
                                value="<?= e($_SESSION['csrf']) ?>"
                            >
                            <input type="hidden" name="aksi" value="hapus">
                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $materi['id'] ?>"
                            >
                            <button type="submit" class="bahaya">
                                Hapus
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>