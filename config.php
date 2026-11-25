<?php
// Konfigurasi untuk XAMPP lokal.
date_default_timezone_set('Asia/Jakarta');

ini_set('session.use_strict_mode', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'path' => '/',
    ]);

    session_start();
}

// Sesuaikan hanya jika pengaturan MySQL kamu berbeda.
$dbHost = '127.0.0.1';
$dbName = 'lms_ptik';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log($exception->getMessage());

    http_response_code(500);

    exit(
        'Koneksi database gagal. Pastikan MySQL di XAMPP aktif '
        . 'dan nama database pada config.php sudah benar.'
    );
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Mengamankan teks sebelum ditampilkan pada halaman.
function e($teks)
{
    return htmlspecialchars(
        (string) $teks,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Memeriksa token formulir.
function cekToken()
{
    $token = $_POST['csrf'] ?? '';

    if (
        !is_string($token)
        || !isset($_SESSION['csrf'])
        || !hash_equals($_SESSION['csrf'], $token)
    ) {
        http_response_code(403);

        exit(
            'Sesi formulir tidak valid. Muat ulang halaman, '
            . 'kemudian coba kembali.'
        );
    }
}