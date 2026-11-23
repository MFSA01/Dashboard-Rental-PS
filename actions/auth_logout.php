<?php
/**
 * actions/auth_logout.php
 * Mengakhiri session admin dengan bersih, lalu kembali ke halaman login.
 * Wajib POST + token CSRF agar tidak bisa dipicu lewat tautan/iframe.
 */

require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Logout harus dilakukan melalui tombol keluar di sidebar.');
    redirect('pages/billing.php');
}

verify_csrf_token('pages/billing.php');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'] !== '' ? $cookieParams['path'] : '/',
            'domain' => (string) ($cookieParams['domain'] ?? ''),
            'secure' => (bool) ($cookieParams['secure'] ?? false),
            'httponly' => (bool) ($cookieParams['httponly'] ?? true),
            'samesite' => (string) ($cookieParams['samesite'] ?? 'Lax'),
        ]
    );
}

session_destroy();

// Session baru (ID baru, cookie baru) khusus untuk membawa flash message setelah logout.
session_start();
session_regenerate_id(true);
set_flash('info', 'Sampai Jumpa', 'Anda telah berhasil keluar dari dashboard.');

redirect('login.php');
