<?php
/**
 * includes/sidebar.php
 * Sidebar navigasi utama (ramping, ikon di atas label).
 */

if (!function_exists('base_url')) {
    require_once dirname(__DIR__) . '/config/helpers.php';
}

$sidebarMenu = [
    [
        'label' => 'Billing',
        'icon' => 'gamepad-2',
        'href' => 'pages/billing.php',
        'page' => 'billing.php',
    ],
    [
        'label' => 'Cafe',
        'icon' => 'coffee',
        'href' => 'pages/cafe.php',
        'page' => 'cafe.php',
    ],
    [
        'label' => 'Report',
        'icon' => 'bar-chart-3',
        'href' => 'pages/report.php',
        'page' => 'report.php',
    ],
    [
        'label' => 'Master Data',
        'icon' => 'database',
        'href' => 'pages/master.php',
        'page' => 'master.php',
    ],
    [
        'label' => 'Settings',
        'icon' => 'settings',
        'href' => 'pages/settings.php',
        'page' => 'settings.php',
    ],
];
?>
<aside class="sidebar" id="appSidebar" aria-label="Navigasi utama">
    <a class="sidebar-brand" href="<?= e(base_url('pages/billing.php')) ?>" title="Davin's Gaming Space">
        <span class="brand-mark">DGS</span>
        <span class="brand-caption">DAVIN'S</span>
    </a>

    <nav class="sidebar-nav" aria-label="Menu dashboard">
        <?php foreach ($sidebarMenu as $item): ?>
            <a
                class="nav-link <?= e(is_active_page($item['page'])) ?>"
                href="<?= e(base_url($item['href'])) ?>"
                <?= is_active_page($item['page']) === 'active' ? 'aria-current="page"' : '' ?>
            >
                <span class="nav-icon"><i data-lucide="<?= e($item['icon']) ?>"></i></span>
                <span class="nav-label"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <form class="sidebar-logout-form" method="post" action="<?= e(base_url('actions/auth_logout.php')) ?>">
            <?= csrf_field() ?>
            <button class="nav-link nav-link-logout" type="submit" title="Keluar dari dashboard">
                <span class="nav-icon"><i data-lucide="log-out"></i></span>
                <span class="nav-label">Logout</span>
            </button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
