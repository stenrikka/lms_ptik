<?php
require_once __DIR__ . '/config.php';

header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$query = $pdo->prepare(
    'SELECT id, nama, username, role FROM users WHERE id = ?'
);
$query->execute([$_SESSION['user_id']]);

$userAktif = $query->fetch();

if (!$userAktif) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// Dipakai nanti untuk melindungi halaman khusus pengajar.
function wajibPengajar()
{
    global $userAktif;

    if ($userAktif['role'] !== 'pengajar') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus pengajar.');
    }
}