<?php
require __DIR__ . '/auth.php';

// Durasi untuk pengerjaan yang BARU dimulai.
$durasiMenit = 2;

if ($userAktif['role'] !== 'mahasiswa') {
    http_response_code(403);
    exit('Halaman pengerjaan hanya untuk mahasiswa.');
}

$id = (int) ($_GET['id'] ?? 0);
$userId = (int) $userAktif['id'];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$isAjax = $isPost && ($_POST['ajax'] ?? '') === '1';

$aksi = $isPost ? (string) ($_POST['aksi'] ?? '') : '';
$error = '';
$sesi = null;
$jawabanTersimpan = [];
$sisaDetik = 0;

if ($id <= 0) {
    http_response_code(400);
    exit('ID kuis tidak valid.');
}

// Mengirim respons ke JavaScript atau mengalihkan halaman.
function responsKuis($data)
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    if (!empty($data['redirect'])) {
        header('Location: ' . $data['redirect']);
        exit;
    }
}

// Memeriksa dan membersihkan jawaban dari formulir.
function bersihkanJawaban($input, $daftarSoal)
{
    if (!is_array($input)) {
        throw new DomainException('Format jawaban tidak valid.');
    }

    $bersih = [];

    foreach ($daftarSoal as $soal) {
        $soalId = (int) $soal['id'];
        $pilihan = $input[$soalId] ?? '';

        if (
            !is_string($pilihan)
            || !in_array($pilihan, ['', 'A', 'B', 'C', 'D'], true)
        ) {
            throw new DomainException('Pilihan jawaban tidak valid.');
        }

        $bersih[$soalId] = $pilihan;
    }

    return $bersih;
}

// Menghitung nilai dan menyimpan hasil akhir.
// Dipanggil di dalam transaksi database.
function simpanHasilAkhir($pdo, $quizId, $userId, $soal, $jawaban)
{
    $total = count($soal);

    if ($total === 0) {
        throw new DomainException('Kuis belum memiliki soal.');
    }

    $benar = 0;

    foreach ($soal as $baris) {
        $pilihan = $jawaban[$baris['id']] ?? '';

        if ($pilihan === $baris['kunci']) {
            $benar++;
        }
    }

    $nilai = round(($benar / $total) * 100, 2);

    $query = $pdo->prepare(
        'INSERT INTO quiz_attempts
         (quiz_id, user_id, jumlah_benar, jumlah_soal, nilai, jawaban_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $query->execute([
        $quizId,
        $userId,
        $benar,
        $total,
        $nilai,
        json_encode($jawaban, JSON_THROW_ON_ERROR)
    ]);
}

if ($isPost) {
    cekToken();
}

try {
    $pdo->beginTransaction();

    // Penguncian mencegah dua permintaan menyimpan hasil bersamaan.
    $query = $pdo->prepare(
        "SELECT * FROM quizzes
         WHERE id = ? AND status = 'published'
         FOR UPDATE"
    );
    $query->execute([$id]);
    $kuis = $query->fetch();

    if (!$kuis) {
        throw new DomainException(
            'Kuis tidak ditemukan atau belum diterbitkan.'
        );
    }

    $query = $pdo->prepare(
        'SELECT id FROM quiz_attempts
         WHERE quiz_id = ? AND user_id = ?
         FOR UPDATE'
    );
    $query->execute([$id, $userId]);

    // Nilai yang sudah ada tidak dihitung atau ditimpa kembali.
    if ($query->fetch()) {
        $pdo->commit();

        responsKuis([
            'ok' => true,
            'redirect' => 'hasil_kuis.php?id=' . $id
        ]);
    }

    $query = $pdo->prepare(
        'SELECT * FROM quiz_questions
         WHERE quiz_id = ? ORDER BY id'
    );
    $query->execute([$id]);
    $daftarSoal = $query->fetchAll();

    if (!$daftarSoal) {
        throw new DomainException('Kuis belum memiliki soal.');
    }

    $query = $pdo->prepare(
        'SELECT * FROM quiz_sessions
         WHERE quiz_id = ? AND user_id = ?
         FOR UPDATE'
    );
    $query->execute([$id, $userId]);
    $sesi = $query->fetch();

    // Mengambil waktu dari server database.
    $sekarang = (int) $pdo->query(
        'SELECT UNIX_TIMESTAMP()'
    )->fetchColumn();

    if ($isPost && !in_array(
        $aksi,
        ['mulai', 'simpan', 'status', 'selesai'],
        true
    )) {
        throw new DomainException('Tindakan tidak valid.');
    }

    // Timer dibuat hanya sekali ketika tombol Mulai ditekan.
    if ($isPost && $aksi === 'mulai' && !$sesi) {
        $batasAkhir = $sekarang + ($durasiMenit * 60);

        $query = $pdo->prepare(
            'INSERT INTO quiz_sessions
             (quiz_id, user_id, started_at, expires_at, jawaban_json)
             VALUES (?, ?, ?, ?, ?)'
        );

        $query->execute([
            $id,
            $userId,
            $sekarang,
            $batasAkhir,
            '{}'
        ]);

        $sesi = [
            'started_at' => $sekarang,
            'expires_at' => $batasAkhir,
            'jawaban_json' => '{}'
        ];
    }

    if ($sesi) {
        $jawabanTersimpan = json_decode(
            $sesi['jawaban_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $sisaDetik = max(
            0,
            (int) $sesi['expires_at'] - $sekarang
        );

        // Setelah waktu habis, jawaban baru tidak diterima.
        // Hanya jawaban yang tersimpan sebelumnya yang dinilai.
        if ($sisaDetik === 0) {
            simpanHasilAkhir(
                $pdo,
                $id,
                $userId,
                $daftarSoal,
                $jawabanTersimpan
            );

            $pdo->commit();

            responsKuis([
                'ok' => true,
                'redirect' => 'hasil_kuis.php?id=' . $id
            ]);
        }

        if ($isPost && in_array($aksi, ['simpan', 'selesai'], true)) {
            $jawabanTersimpan = bersihkanJawaban(
                $_POST['jawaban'] ?? [],
                $daftarSoal
            );

            $query = $pdo->prepare(
                'UPDATE quiz_sessions SET jawaban_json = ?
                 WHERE quiz_id = ? AND user_id = ?'
            );

            $query->execute([
                json_encode($jawabanTersimpan, JSON_THROW_ON_ERROR),
                $id,
                $userId
            ]);

            if ($aksi === 'selesai') {
                simpanHasilAkhir(
                    $pdo,
                    $id,
                    $userId,
                    $daftarSoal,
                    $jawabanTersimpan
                );

                $pdo->commit();

                responsKuis([
                    'ok' => true,
                    'redirect' => 'hasil_kuis.php?id=' . $id
                ]);
            }
        }
    } elseif ($isPost) {
        throw new DomainException(
            'Tekan Mulai Kuis terlebih dahulu.'
        );
    }

    $pdo->commit();

    if ($isAjax) {
        responsKuis([
            'ok' => true,
            'remaining' => $sisaDetik
        ]);
    }

    // Refresh setelah memulai tidak mengirim ulang formulir.
    if ($isPost && $aksi === 'mulai') {
        header('Location: kerjakan_kuis.php?id=' . $id);
        exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof DomainException) {
        $error = $e->getMessage();
    } else {
        error_log($e->getMessage());
        $error = 'Proses gagal. Periksa koneksi database lalu coba kembali.';
    }

    if ($isAjax) {
        http_response_code(400);
        responsKuis([
            'ok' => false,
            'message' => $error
        ]);
    }

    // Menghentikan tampilan supaya tidak menampilkan sesi yang tidak valid.
    http_response_code(400);
    echo '<p>' . e($error) . '</p>';
    echo '<p><a href="kerjakan_kuis.php?id=' . $id . '">'
        . 'Buka kembali kuis</a></p>';
    echo '<p><a href="kuis.php">Kembali ke daftar kuis</a></p>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kerjakan Kuis</title>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="kuis.css">

    <style>
        .timer-panel {
            position: sticky;
            top: 10px;
            z-index: 10;
            padding: 16px 20px;
            margin: 20px 0;
            border: 2px solid #1d4ed8;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e3a8a;
            box-shadow: 0 4px 12px #00000012;
        }

        .timer-angka {
            font-size: 32px;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
        }

        .timer-panel.hampir-habis {
            background: #fef2f2;
            border-color: #dc2626;
            color: #991b1b;
        }

        .status-simpan {
            margin: 8px 0 0;
            font-size: 14px;
        }

        button:disabled {
            opacity: 0.6;
            cursor: wait;
        }
    </style>
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>
<body class="rb-app rb-kuis rb-page-kerjakan_kuis">
<?php require_once __DIR__ . "/tampilan.php"; rbShell("kuis", "Ruang Latihan"); ?>
<main class="kotak rb-content" id="konten">
<section class="rb-banner"><div><span class="rb-eyebrow">FOKUS & PERCAYA DIRI</span><h2>Satu soal dalam satu waktu.</h2><p>Baca pertanyaan dengan teliti dan pilih jawaban terbaikmu.</p></div><span class="rb-banner-symbol" aria-hidden="true"><?= rbIcon("kuis") ?></span></section>
    <a href="kuis.php">← Kembali ke daftar kuis</a>

    <h1><?= e($kuis['judul']) ?></h1>
    <p><?= nl2br(e($kuis['deskripsi'])) ?></p>

    <?php if (!$sesi): ?>
        <section class="kartu-kuis">
            <h2>Siap Mengerjakan?</h2>

            <p>
                Jumlah soal:
                <strong><?= count($daftarSoal) ?></strong>
            </p>

            <p>
                Durasi:
                <strong><?= $durasiMenit ?> menit</strong>
            </p>

            <ul>
                <li>Timer dimulai setelah tombol Mulai Kuis ditekan.</li>
                <li>Refresh atau menutup halaman tidak menghentikan waktu.</li>
                <li>Jawaban kosong dihitung salah.</li>
                <li>Kuis hanya dapat diselesaikan satu kali.</li>
            </ul>

            <form method="post">
                <input type="hidden" name="csrf"
                       value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="mulai">

                <button type="submit">Mulai Kuis</button>
            </form>
        </section>
    <?php else: ?>
        <noscript>
            <p class="pesan">
                Aktifkan JavaScript untuk melihat timer dan menyimpan
                jawaban otomatis. Batas waktu di server tetap berlaku.
            </p>
        </noscript>

        <div class="timer-panel" id="timerPanel">
            <div>Sisa waktu</div>

            <div class="timer-angka" id="timerAngka">
                <?= sprintf(
                    '%02d:%02d',
                    intdiv($sisaDetik, 60),
                    $sisaDetik % 60
                ) ?>
            </div>

            <p class="status-simpan" id="statusSimpan" role="status">
                Jawaban yang tersimpan akan dipulihkan saat refresh.
            </p>
        </div>

        <p class="info">
            Pilih satu jawaban per soal.
            Tunggu tulisan “Jawaban tersimpan” sebelum refresh.
            Kerjakan kuis ini dalam satu tab.
            Saat waktu habis, jawaban yang sudah tersimpan akan dinilai.
        </p>

        <form method="post" id="formKuis">
            <input type="hidden" name="csrf"
                   value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="aksi" value="selesai">

            <?php foreach ($daftarSoal as $nomor => $soal): ?>
                <section class="kartu-kuis">
                    <h2>Soal <?= $nomor + 1 ?></h2>

                    <p class="teks-soal">
                        <?= e($soal['pertanyaan']) ?>
                    </p>

                    <?php foreach (['A', 'B', 'C', 'D'] as $huruf): ?>
                        <label class="pilihan-jawaban">
                            <input
                                type="radio"
                                name="jawaban[<?= (int) $soal['id'] ?>]"
                                value="<?= $huruf ?>"
                                <?= ($jawabanTersimpan[$soal['id']] ?? '') === $huruf
                                    ? 'checked'
                                    : '' ?>
                            >

                            <span>
                                <strong><?= $huruf ?>.</strong>
                                <?= e($soal['opsi_' . strtolower($huruf)]) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <button type="submit" id="tombolKirim">
                Selesai dan Lihat Nilai
            </button>
        </form>

        <script>
        const form = document.getElementById('formKuis');
        const timerPanel = document.getElementById('timerPanel');
        const timerAngka = document.getElementById('timerAngka');
        const statusSimpan = document.getElementById('statusSimpan');
        const tombolKirim = document.getElementById('tombolKirim');

        const alamat = 'kerjakan_kuis.php?id=<?= $id ?>';

        let batasLokal = performance.now() + <?= $sisaDetik ?> * 1000;
        let antreanSimpan = Promise.resolve();
        let mengirim = false;
        let memeriksa = false;
        let waktuHabis = false;

        function perbaruiWaktu(sisa) {
            batasLokal = performance.now() + Number(sisa) * 1000;
        }

        async function panggilServer(data) {
            data.set('ajax', '1');

            const response = await fetch(alamat, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(10000)
            });

            const hasil = await response.json();

            if (!response.ok || !hasil.ok) {
                throw new Error(
                    hasil.message || 'Permintaan gagal diproses.'
                );
            }

            if (hasil.redirect) {
                mengirim = true;
                window.location.replace(hasil.redirect);
                return hasil;
            }

            if (typeof hasil.remaining === 'number') {
                perbaruiWaktu(hasil.remaining);
            }

            return hasil;
        }

        // Simpan setiap perubahan jawaban secara berurutan.
        form.addEventListener('change', function (event) {
            if (
                event.target.type !== 'radio'
                || mengirim
                || waktuHabis
            ) {
                return;
            }

            const data = new FormData(form);
            data.set('aksi', 'simpan');

            statusSimpan.textContent = 'Menyimpan jawaban...';

            antreanSimpan = antreanSimpan
                .then(async function () {
                    if (mengirim) return;

                    await panggilServer(data);

                    if (!mengirim) {
                        statusSimpan.textContent = 'Jawaban tersimpan.';
                    }
                })
                .catch(function () {
                    statusSimpan.textContent =
                        'Penyimpanan belum berhasil. Jangan refresh. ' +
                        'Periksa koneksi atau kirim sebelum waktu habis.';
                });
        });

        // Memeriksa waktu server dan menyelesaikan kuis yang kedaluwarsa.
        async function periksaStatus() {
            if (mengirim || memeriksa) return;

            memeriksa = true;

            const data = new FormData();
            data.set('aksi', 'status');
            data.set('csrf', form.elements.csrf.value);

            try {
                await panggilServer(data);
            } catch (error) {
                statusSimpan.textContent =
                    'Belum terhubung ke server. Sistem akan mencoba kembali.';
            } finally {
                memeriksa = false;
            }
        }

        function tampilkanTimer() {
            if (mengirim) return;

            const sisa = Math.max(
                0,
                Math.ceil((batasLokal - performance.now()) / 1000)
            );

            const menit = String(Math.floor(sisa / 60)).padStart(2, '0');
            const detik = String(sisa % 60).padStart(2, '0');

            timerAngka.textContent = menit + ':' + detik;
            timerPanel.classList.toggle('hampir-habis', sisa <= 60);

            if (sisa === 0 && !waktuHabis) {
                waktuHabis = true;

                form.querySelectorAll('input[type="radio"]').forEach(
                    input => input.disabled = true
                );

                tombolKirim.disabled = true;
                statusSimpan.textContent =
                    'Waktu habis. Menyelesaikan kuis...';

                // Jawaban baru tidak dikirim setelah tenggat.
                // Server menilai jawaban yang telah tersimpan.
                periksaStatus();
            }
        }

        // Mahasiswa boleh menyelesaikan sebelum waktu habis.
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            if (mengirim) return;

            if (waktuHabis) {
                periksaStatus();
                return;
            }

            if (!confirm(
                'Selesaikan kuis sekarang? Jawaban tidak bisa diubah lagi.'
            )) {
                return;
            }

            tombolKirim.disabled = true;

            // Tunggu penyimpanan yang sedang berlangsung.
            await antreanSimpan;

            if (mengirim) return;

            // Mengirim snapshot akhir beserta pilihan yang belum tersimpan.
            // Server tetap memeriksa apakah waktu masih tersedia.
            mengirim = true;
            HTMLFormElement.prototype.submit.call(form);
        });

        setInterval(tampilkanTimer, 250);
        setInterval(periksaStatus, 5000);

        // Sinkronkan waktu saat kembali dari tab lain atau mode tidur.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                periksaStatus();
            }
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

        tampilkanTimer();
        </script>
    <?php endif; ?>
<footer class="rb-footer"><span>Ruang Belajar · PTIK B</span><span>Belajar. Berlatih. Bertumbuh.</span></footer>
</main>
</body>
</html>