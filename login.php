<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(home_url());
}

$error = '';
$username = '';

/* Batas percobaan masuk: 5 kali gagal dalam 5 menit. */
$LOGIN_MAX_ATTEMPTS = 5;
$LOGIN_WINDOW       = 300;
$attempts = $_SESSION['login_attempts'] ?? [];
$attempts = array_values(array_filter($attempts, static function ($t) use ($LOGIN_WINDOW) {
    return $t > time() - $LOGIN_WINDOW;
}));
$blocked  = count($attempts) >= $LOGIN_MAX_ATTEMPTS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_valid()) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif ($blocked) {
        $error = 'Terlalu banyak percobaan masuk. Silakan coba lagi beberapa menit lagi.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.*, b.nama AS branch_nama, b.kode AS branch_kode
             FROM users u LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $attempts[] = time();
            $_SESSION['login_attempts'] = $attempts;
            $sisa = $LOGIN_MAX_ATTEMPTS - count($attempts);
            $error = 'Username atau password salah.' . ($sisa > 0 ? ' Sisa percobaan: ' . $sisa . '.' : '');
        } elseif ((int) $user['aktif'] !== 1) {
            $error = 'Akun Anda tidak aktif. Hubungi administrator.';
        } else {
            unset($_SESSION['login_attempts']);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'          => (int) $user['id'],
                'nama'        => $user['nama'],
                'username'    => $user['username'],
                'role'        => $user['role'],
                'branch_id'   => $user['branch_id'] !== null ? (int) $user['branch_id'] : null,
                'branch_nama' => $user['branch_nama'],
                'branch_kode' => $user['branch_kode'],
            ];
            flash_set('success', 'Selamat datang, ' . $user['nama'] . '.');
            redirect(home_url());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk &middot; <?= e(app_name()) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo-container">
            <img src="<?= e(app_logo()) ?>" alt="Logo" class="login-logo-img">
        </div>
        <h1><?= e(app_name()) ?></h1>
        <p class="sub">Dashboard Monitoring Penjualan Vape Store</p>

        <?php if ($error): ?>
            <div class="login-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="username">Username</label>
                <input class="form-control" type="text" id="username" name="username" value="<?= e($username) ?>" placeholder="mis. admin" required autofocus>
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;">Masuk</button>
        </form>

        <?php if (APP_ENV === 'development'): ?>
        <div class="demo-box">
            <strong>Akun demo (mode pengembang):</strong><br>
            Superadmin (Toko Induk) — <code>admin</code> / <code>admin123</code><br>
            Cabang — <code>pgk</code>, <code>sglt</code>, <code>tbl</code>, <code>tjp</code>, <code>mgr</code> / <code>cabang123</code>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
