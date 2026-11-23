<?php
/**
 * includes/topbar.php
 * Topbar: tombol toggle sidebar (mobile), judul halaman, badge admin.
 *
 * Variabel opsional sebelum include:
 * - $pageTitle (string)     Judul halaman aktif.
 * - $pageSubtitle (string)  Subjudul pendek di bawah judul.
 */

if (!function_exists('base_url')) {
    require_once dirname(__DIR__) . '/config/helpers.php';
}

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : 'Dashboard';
$pageSubtitle = isset($pageSubtitle) ? (string) $pageSubtitle : '';

$adminName = trim((string) ($_SESSION['admin_name'] ?? 'Administrator'));
if ($adminName === '') {
    $adminName = (string) ($_SESSION['admin_username'] ?? 'Admin');
}

$words = array_values(array_filter(preg_split('/\s+/', $adminName) ?: [], static fn ($word) => $word !== ''));
$initials = '';
foreach (array_slice($words, 0, 2) as $word) {
    $initials .= mb_substr($word, 0, 1);
}
$initials = mb_strtoupper($initials !== '' ? $initials : 'AD');
?>
<header class="topbar">
    <button
        class="icon-btn topbar-toggle"
        type="button"
        id="sidebarToggle"
        aria-label="Buka menu navigasi"
        aria-controls="appSidebar"
        aria-expanded="false"
    >
        <i data-lucide="menu"></i>
    </button>

    <div class="topbar-heading">
        <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
        <?php if ($pageSubtitle !== ''): ?>
            <p class="topbar-subtitle"><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="topbar-actions">
        <span class="topbar-status"><span class="status-dot" aria-hidden="true"></span>Online</span>
        <div class="admin-badge" title="<?= e($adminName) ?>" role="img" aria-label="Admin: <?= e($adminName) ?>">
            <?= e($initials) ?>
        </div>
    </div>
</header>
