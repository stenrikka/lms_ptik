<?php
require __DIR__ . '/config.php';

header('Cache-Control: no-store');

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cekToken();

    $inputUsername = $_POST['username'] ?? '';
    $inputPassword = $_POST['password'] ?? '';

    $username = is_string($inputUsername)
        ? trim($inputUsername)
        : '';

    $password = is_string($inputPassword)
        ? $inputPassword
        : '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $query = $pdo->prepare(
            'SELECT id, password FROM users WHERE username = ?'
        );
        $query->execute([$username]);

        $user = $query->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Username atau password salah.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Masuk — Ruang Belajar PTIK B</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 32px;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #18243b;
            background:
                radial-gradient(
                    circle at 10% 10%,
                    #e5ebff,
                    transparent 40%
                ),
                #f5f7fb;
        }

        button,
        input {
            font: inherit;
        }

        .login-layout {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            width: 100%;
            max-width: 1080px;
            min-height: 640px;
            background: white;
            border: 1px solid #e5ebf5;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 25px 75px #263c6510;
            animation: masuk 0.65s ease-out both;
        }

        .visual-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 42px;
            overflow: hidden;
            background: linear-gradient(
                145deg,
                #243fa8,
                #3459e6 60%,
                #6b85f4
            );
            color: white;
        }

        .brand {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            display: grid;
            place-items: center;
            width: 46px;
            height: 46px;
            border: 1px solid #ffffff40;
            border-radius: 14px;
            background: #ffffff14;
        }

        .brand-icon svg {
            width: 25px;
            height: 25px;
        }

        .brand strong {
            display: block;
            font-size: 20px;
            letter-spacing: -0.5px;
        }

        .brand small {
            display: block;
            margin-top: 3px;
            color: #dce5ff;
            font-size: 11px;
            letter-spacing: 1.5px;
        }

        .visual-content {
            position: relative;
            z-index: 2;
            margin: 60px 0;
            max-width: 370px;
        }

        .eyebrow {
            display: inline-block;
            padding: 7px 12px;
            border: 1px solid #ffffff30;
            border-radius: 20px;
            background: #ffffff10;
            font-size: 10px;
            letter-spacing: 1.4px;
        }

        .visual-content h1 {
            margin: 22px 0 18px;
            font-size: clamp(34px, 4vw, 46px);
            line-height: 1.15;
            letter-spacing: -1.8px;
        }

        .visual-content p {
            max-width: 325px;
            color: #e0e8ff;
            font-size: 14px;
            line-height: 1.85;
        }

        .features {
            position: relative;
            z-index: 2;
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
        }

        .features span {
            padding: 8px 12px;
            border: 1px solid #ffffff2b;
            border-radius: 10px;
            background: #ffffff0f;
            color: #edf2ff;
            font-size: 11px;
        }

        .circle {
            position: absolute;
            width: 380px;
            height: 380px;
            right: -230px;
            bottom: 40px;
            border: 55px solid #ffffff0b;
            border-radius: 50%;
            pointer-events: none;
        }

        .square {
            position: absolute;
            right: 38px;
            top: 130px;
            width: 70px;
            height: 70px;
            border: 1px solid #ffffff2b;
            border-radius: 18px;
            transform: rotate(20deg);
            pointer-events: none;
            animation: bentukMasuk 1s ease-out both;
        }

        .form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 56px;
        }

        .welcome-tag {
            margin: 0 0 10px;
            color: #3459e6;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.8px;
        }

        .form-panel h2 {
            margin: 0 0 12px;
            font-size: 31px;
            line-height: 1.25;
            letter-spacing: -1px;
        }

        .description {
            margin: 0 0 30px;
            color: #69758b;
            font-size: 14px;
            line-height: 1.8;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            color: #36445d;
            font-size: 13px;
            font-weight: 650;
        }

        input:not([type="hidden"]) {
            width: 100%;
            min-height: 52px;
            padding: 14px 16px;
            border: 1px solid #d9e2ef;
            border-radius: 12px;
            background: #f9fbff;
            color: #18243b;
            font-size: 14px;
            transition:
                background 0.2s,
                border-color 0.2s,
                box-shadow 0.2s;
        }

        input::placeholder {
            color: #8793a7;
        }

        input:not([type="hidden"]):focus {
            outline: none;
            border-color: #7893f1;
            background: white;
            box-shadow: 0 0 0 4px #3459e612;
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 86px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            padding: 7px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #3459e6;
            font-size: 12px;
            font-weight: 650;
            cursor: pointer;
        }

        .toggle-password:hover {
            background: #edf2ff;
        }

        .submit-button {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-top: 28px;
            padding: 15px 19px;
            border: 0;
            border-radius: 12px;
            background: #3459e6;
            color: white;
            font-size: 14px;
            font-weight: 650;
            cursor: pointer;
            transition:
                background 0.2s,
                transform 0.2s,
                box-shadow 0.2s;
        }

        .submit-button:hover {
            background: #2948c7;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px #3459e62b;
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .submit-button svg {
            width: 20px;
            height: 20px;
            transition: transform 0.2s;
        }

        .submit-button:hover svg {
            transform: translateX(4px);
        }

        button:focus-visible {
            outline: 3px solid #f2b84b;
            outline-offset: 4px;
        }

        .error {
            margin-bottom: 18px;
            padding: 13px 16px;
            border: 1px solid #f4d5db;
            border-radius: 12px;
            background: #fff1f2;
            color: #a33143;
            font-size: 13px;
            line-height: 1.6;
        }

        .account-note {
            margin: 22px 0 0;
            color: #78849a;
            font-size: 12px;
            line-height: 1.8;
        }

        .footer {
            margin-top: 38px;
            padding-top: 20px;
            border-top: 1px solid #edf1f7;
            color: #8791a3;
            font-size: 11px;
        }

        @keyframes masuk {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bentukMasuk {
            from {
                opacity: 0;
                transform: translateY(20px) rotate(0);
            }
            to {
                opacity: 1;
                transform: translateY(0) rotate(20deg);
            }
        }

        @media (max-width: 850px) {
            .visual-panel {
                padding: 32px;
            }

            .form-panel {
                padding: 42px 32px;
            }

            .visual-content h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 640px) {
            body {
                display: block;
                padding: 18px 12px;
            }

            .login-layout {
                grid-template-columns: 1fr;
                min-height: auto;
                border-radius: 22px;
            }

            .visual-panel {
                padding: 27px;
            }

            .visual-content {
                margin: 28px 0 0;
            }

            .visual-content h1 {
                margin: 16px 0 12px;
                font-size: 30px;
            }

            .visual-content p,
            .features,
            .square {
                display: none;
            }

            .form-panel {
                padding: 32px 27px;
            }

            .form-panel h2 {
                font-size: 27px;
            }

            .footer {
                margin-top: 25px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
<link rel="stylesheet" href="ruang.css?v=20261117-2">
<script src="ruang.js?v=20261117-2" defer></script>
</head>

<body class="rb-login">
<main class="login-layout">
    <section class="visual-panel" aria-label="Ruang Belajar PTIK B">
        <div class="circle" aria-hidden="true"></div>
        <div class="square" aria-hidden="true"></div>

        <div class="brand">
            <span class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1
                             3-2 6-2 9-1V4c-3-1-6-1-9 1Z"/>
                    <path d="M12 5v15"/>
                </svg>
            </span>

            <div>
                <strong>Ruang Belajar</strong>
                <small>LMS · PTIK B</small>
            </div>
        </div>

        <div class="visual-content">
            <span class="eyebrow">BELAJAR · BERLATIH · BERTUMBUH</span>

            <h1>Langkah kecil.<br>Pengetahuan baru.</h1>

            <p>
                Temukan materi, uji pemahaman, dan bertukar
                pemikiran bersama kelas dalam satu ruang.
            </p>
        </div>

        <div class="features">
            <span>Materi pembelajaran</span>
            <span>Kuis interaktif</span>
            <span>Forum diskusi</span>
        </div>
    </section>

    <section class="form-panel" aria-labelledby="judulLogin">
        <p class="welcome-tag">SELAMAT DATANG KEMBALI</p>

        <h2 id="judulLogin">Masuk ke ruang belajarmu.</h2>

        <p class="description">
            Gunakan akun pengajar atau mahasiswa untuk
            melanjutkan aktivitas kelas.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <label for="username">Username</label>
            <input
                id="username"
                type="text"
                name="username"
                value="<?= e($username) ?>"
                placeholder="Masukkan username"
                maxlength="50"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>

            <div class="password-wrap">
                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    aria-controls="password"
                    aria-pressed="false"
                    aria-label="Tampilkan password"
                >
                    Lihat
                </button>
            </div>

            <button type="submit" class="submit-button">
                <span>Masuk ke Dashboard</span>

                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6"/>
                </svg>
            </button>
        </form>

        <p class="account-note">
            Belum memiliki akun? Hubungi pengajar atau
            pengelola kelas untuk mendapatkan akses.
        </p>

        <div class="footer">
            LMS PTIK B · Ruang Belajar
        </div>
    </section>
</main>

<script>
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function () {
    const tampilkan = passwordInput.type === 'password';

    passwordInput.type = tampilkan ? 'text' : 'password';

    togglePassword.textContent = tampilkan ? 'Tutup' : 'Lihat';
    togglePassword.setAttribute('aria-pressed', String(tampilkan));
    togglePassword.setAttribute(
        'aria-label',
        tampilkan ? 'Sembunyikan password' : 'Tampilkan password'
    );
});
</script>
</body>
</html>