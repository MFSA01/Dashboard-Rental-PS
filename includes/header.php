<?php
/**
 * includes/header.php
 * Head HTML global: meta, Google Fonts, CSS tema, Lucide, SweetAlert2.
 *
 * Variabel opsional sebelum include:
 * - $pageTitle (string)  Judul halaman / tab browser.
 * - $bodyClass (string)  Class tambahan untuk <body>.
 */

if (!function_exists('base_url')) {
    require_once dirname(__DIR__) . '/config/helpers.php';
}

$pageTitle = isset($pageTitle) && $pageTitle !== '' ? (string) $pageTitle : 'Dashboard';
$bodyClass = isset($bodyClass) && $bodyClass !== '' ? (string) $bodyClass : 'app-body';
$siteName = isset($conn) && $conn instanceof mysqli
    ? get_setting($conn, 'rental_name', "Davin's Gaming Space")
    : "Davin's Gaming Space";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B0F0D">
    <meta name="description" content="Dashboard operasional rental PlayStation &amp; cafe Davin's Gaming Space.">
    <title><?= e($pageTitle) ?> &middot; <?= e($siteName) ?></title>

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%2314532D'/%3E%3Ctext x='32' y='42' font-family='Arial,sans-serif' font-size='24' font-weight='700' text-anchor='middle' fill='%234ADE80'%3EDGS%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@600;700&family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e(base_url('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/components.css')) ?>">

    <script src="https://unpkg.com/lucide@1.77.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <?php if (!empty($loadChartJs)): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
