<?php
/**
 * config/helpers.php
 * Helper global: session, URL, escaping, format rupiah, flash message, auth guard.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttpsRequest = (!empty($_SERVER['HTTPS']) && strtolower((string) ($_SERVER['HTTPS'] ?? '')) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

    // Tolak session ID yang tidak pernah dibuat server (anti session fixation).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttpsRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('DGS_SESSIONID');
    session_start();
}

/**
 * URL dasar aplikasi. Otomatis mendeteksi virtual host Laragon
 * (mis. http://davins-gaming-space.test) atau subfolder
 * (mis. http://localhost/davins-gaming-space).
 */
function base_url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) ($_SERVER['HTTPS'] ?? '')) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $scheme = $isHttps ? 'https' : 'http';

        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = (string) preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host);
        if ($host === '') {
            $host = 'localhost';
        }

        $appRoot = str_replace('\\', '/', (string) (realpath(dirname(__DIR__)) ?: dirname(__DIR__)));
        $docRootRaw = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        $docRoot = $docRootRaw !== ''
            ? str_replace('\\', '/', (string) (realpath($docRootRaw) ?: $docRootRaw))
            : '';
        $docRoot = rtrim($docRoot, '/');

        $basePath = '';
        if ($docRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($docRoot))) {
            $basePath = substr($appRoot, strlen($docRoot));
        }

        $basePath = trim(str_replace('\\', '/', $basePath), '/');
        $base = $scheme . '://' . $host . ($basePath === '' ? '' : '/' . $basePath);
    }

    $path = ltrim($path, '/');

    return $path === '' ? $base : $base . '/' . $path;
}

/** Escape output HTML (anti-XSS). */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format nominal rupiah, contoh: 15000 -> "Rp 15.000". */
function format_rupiah(int|float $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/** Simpan flash message untuk SweetAlert2 pada request berikutnya. */
function set_flash(string $type, string $title, string $message): void
{
    $_SESSION['flash'] = [
        'type' => in_array($type, ['success', 'error', 'warning', 'info', 'question'], true) ? $type : 'info',
        'title' => $title,
        'message' => $message,
    ];
}

/** Ambil sekaligus hapus flash message. */
function get_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return [
        'type' => in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info', 'question'], true)
            ? $flash['type']
            : 'info',
        'title' => (string) ($flash['title'] ?? 'Informasi'),
        'message' => (string) ($flash['message'] ?? ''),
    ];
}

/**
 * Token CSRF per-session (dibuat sekali, dipakai ulang).
 */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Hidden input CSRF untuk disisipkan ke setiap form POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validasi token CSRF dari request POST saat ini.
 * Jika tidak valid: set flash error + redirect ke halaman asal (aman).
 *
 * @param string|null $redirectPath Path internal fallback bila referer tidak bisa dipakai.
 */
function verify_csrf_token(?string $redirectPath = null): void
{
    $posted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (
        is_string($posted)
        && $posted !== ''
        && is_string($expected)
        && $expected !== ''
        && hash_equals($expected, $posted)
    ) {
        return;
    }

    $target = is_string($redirectPath) ? trim($redirectPath) : '';

    if ($target === '') {
        $base = base_url('');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer !== '' && str_starts_with($referer, $base . '/')) {
            $path = ltrim(substr($referer, strlen($base)), '/');
            // Buang query string agar redirect tetap bersih.
            $questionPos = strpos($path, '?');
            if ($questionPos !== false) {
                $path = substr($path, 0, $questionPos);
            }
            // Tolak path mencurigakan (traversal / absolute) agar tidak jadi open redirect.
            if ($path !== '' && !str_contains($path, '..') && !str_contains($path, '//') && !str_contains($path, ':')) {
                $target = $path;
            }
        }
    }

    if ($target === '' || str_contains($target, '//') || preg_match('#^https?://#i', $target) === 1) {
        $target = is_admin_logged_in() ? 'pages/billing.php' : 'login.php';
    }

    set_flash('error', 'Sesi Formulir Kedaluwarsa', 'Token keamanan formulir tidak valid. Silakan ulangi aksi dari halaman sebelumnya.');
    redirect($target);
}

/** Redirect ke path internal (atau URL absolut) lalu hentikan eksekusi. */
function redirect(string $path): never
{
    if (!headers_sent()) {
        if (preg_match('#^https?://#i', $path) === 1) {
            header('Location: ' . $path);
        } else {
            header('Location: ' . base_url($path));
        }
    }

    exit;
}

/** Cek apakah admin sudah login. */
function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Guard wajib untuk semua halaman dashboard. */
function require_admin_auth(): void
{
    if (is_admin_logged_in()) {
        return;
    }

    set_flash('warning', 'Akses Ditolak', 'Silakan masuk terlebih dahulu untuk membuka dashboard.');
    redirect('login.php');
}

/** Ambil nilai setting dari tabel settings (default jika belum ada). */
function get_setting(mysqli $conn, string $key, string $default = ''): string
{
    $sql = 'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        return $default;
    }

    mysqli_stmt_bind_param($stmt, 's', $key);

    $value = $default;
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result !== false) {
            $row = mysqli_fetch_assoc($result);
            if ($row !== null && isset($row['setting_value'])) {
                $value = (string) $row['setting_value'];
            }
            mysqli_free_result($result);
        }
    }

    mysqli_stmt_close($stmt);

    return $value;
}

/**
 * Hitung biaya Open Billing berdasarkan durasi berjalan (detik).
 * Minimal ditagih $minMinutes, lalu dibulatkan ke atas per $roundingMinutes.
 *
 * @return array{billable_minutes: int, cost: int}
 */
function calculate_open_billing_cost(
    int $elapsedSeconds,
    int $hourlyRate,
    int $minMinutes = 30,
    int $roundingMinutes = 15
): array {
    $minMinutes = max(1, $minMinutes);
    $roundingMinutes = max(1, $roundingMinutes);

    $elapsedMinutes = max(1, (int) ceil(max(0, $elapsedSeconds) / 60));

    if ($elapsedMinutes <= $minMinutes) {
        $billableMinutes = $minMinutes;
    } else {
        $billableMinutes = (int) (ceil($elapsedMinutes / $roundingMinutes) * $roundingMinutes);
    }

    $cost = (int) round(($billableMinutes / 60) * max(0, $hourlyRate));

    return [
        'billable_minutes' => $billableMinutes,
        'cost' => $cost,
    ];
}

/** Label durasi ramah dibaca, contoh: 90 -> "1 Jam 30 Menit". */
function format_duration_label(int $minutes): string
{
    $minutes = max(0, $minutes);

    if ($minutes === 0) {
        return '0 Menit';
    }

    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;

    if ($hours > 0 && $rest > 0) {
        return $hours . ' Jam ' . $rest . ' Menit';
    }

    if ($hours > 0) {
        return $hours . ' Jam';
    }

    return $rest . ' Menit';
}

/** Kembalikan "active" bila halaman saat ini cocok dengan nama file menu. */
function is_active_page(string $pageName): string
{
    $target = trim($pageName);
    if ($target === '') {
        return '';
    }
    if (!str_ends_with($target, '.php')) {
        $target .= '.php';
    }

    $current = basename((string) ($_SERVER['PHP_SELF'] ?? ''));

    // Halaman detail sesi masih bagian dari dashboard billing.
    if ($current === 'session_detail.php' && $target === 'billing.php') {
        return 'active';
    }

    return $current === $target ? 'active' : '';
}
