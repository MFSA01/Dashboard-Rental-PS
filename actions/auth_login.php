<?php
/**
 * actions/auth_login.php
 * Memproses login admin (POST) menggunakan prepared statement.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Silakan gunakan formulir login.');
    redirect('login.php');
}

verify_csrf_token('login.php');

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    set_flash('error', 'Data Belum Lengkap', 'Username dan password wajib diisi.');
    redirect('login.php');
}

/* ------------------------------------------------------------
 * Throttle login sederhana: maksimal 5 percobaan gagal per 60 detik
 * (disimpan di session, cukup untuk mencegah brute force dasar).
 * ------------------------------------------------------------ */
$attemptWindow = 60;
$maxAttempts = 5;
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first_at' => 0];
if (!is_array($attempts)) {
    $attempts = ['count' => 0, 'first_at' => 0];
}
$attemptCount = (int) ($attempts['count'] ?? 0);
$attemptFirstAt = (int) ($attempts['first_at'] ?? 0);

if ($attemptCount >= $maxAttempts && (time() - $attemptFirstAt) < $attemptWindow) {
    $waitSeconds = max(1, $attemptWindow - (time() - $attemptFirstAt));
    set_flash('error', 'Terlalu Banyak Percobaan', 'Tunggu ' . $waitSeconds . ' detik sebelum mencoba login lagi.');
    redirect('login.php');
}

if ((time() - $attemptFirstAt) >= $attemptWindow) {
    $attemptCount = 0;
    $attemptFirstAt = time();
}

$sql = 'SELECT id, username, password_hash, full_name FROM admins WHERE username = ? LIMIT 1';
$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    error_log('[DGS] Gagal menyiapkan query login: ' . mysqli_error($conn));
    set_flash('error', 'Terjadi Kesalahan', 'Sistem tidak dapat memproses login saat ini. Coba lagi.');
    redirect('login.php');
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = $result !== false ? mysqli_fetch_assoc($result) : null;

if ($result !== false) {
    mysqli_free_result($result);
}
mysqli_stmt_close($stmt);

if ($admin === null || !password_verify($password, (string) $admin['password_hash'])) {
    $_SESSION['login_attempts'] = [
        'count' => $attemptCount + 1,
        'first_at' => $attemptFirstAt > 0 ? $attemptFirstAt : time(),
    ];
    $remaining = max(0, $maxAttempts - ($attemptCount + 1));
    $suffix = $remaining > 0 ? ' Sisa ' . $remaining . ' percobaan.' : ' Percobaan berikutnya akan ditunda sementara.';
    set_flash('error', 'Login Gagal', 'Username atau password salah.' . $suffix);
    redirect('login.php');
}

// Login berhasil: reset penghitung percobaan.
unset($_SESSION['login_attempts']);

session_regenerate_id(true);

// Token CSRF lama dibuang agar sesi login memakai token baru.
unset($_SESSION['csrf_token']);

$_SESSION['admin_id'] = (int) $admin['id'];
$_SESSION['admin_username'] = (string) $admin['username'];
$_SESSION['admin_name'] = (string) $admin['full_name'];
$_SESSION['admin_login_at'] = date('Y-m-d H:i:s');

mysqli_close($conn);

set_flash('success', 'Selamat Datang, ' . $admin['full_name'], 'Login berhasil. Selamat bekerja di Davin\'s Gaming Space.');
redirect('pages/billing.php');
