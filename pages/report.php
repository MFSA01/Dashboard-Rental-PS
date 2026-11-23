<?php
/**
 * pages/report.php
 * Laporan finansial: ringkasan omzet, grafik pendapatan harian,
 * riwayat sesi PS selesai, dan transaksi cafe walk-in.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$pageTitle = 'FINANCIAL REPORT';
$pageSubtitle = 'Laporan omzet & riwayat transaksi';
$loadChartJs = true;

/* ------------------------------------------------------------
 * Filter Rentang Tanggal
 * ------------------------------------------------------------ */
$preset = trim((string) ($_GET['preset'] ?? '7days'));
$rawStartDate = trim((string) ($_GET['start_date'] ?? ''));
$rawEndDate = trim((string) ($_GET['end_date'] ?? ''));

$today = date('Y-m-d');

if (!function_exists('isValidDate')) {
    function isValidDate(string $date): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
}

if ($preset === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($preset === 'month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($preset === 'custom' && isValidDate($rawStartDate) && isValidDate($rawEndDate)) {
    $startDate = min($rawStartDate, $rawEndDate);
    $endDate = max($rawStartDate, $rawEndDate);
} elseif (isValidDate($rawStartDate) && isValidDate($rawEndDate)) {
    $preset = 'custom';
    $startDate = min($rawStartDate, $rawEndDate);
    $endDate = max($rawStartDate, $rawEndDate);
} else {
    $preset = '7days';
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = $today;
}

// Batasi rentang maksimal 366 hari agar query + grafik harian tetap ringan.
$maxRangeDays = 366;
$rangeStartTs = strtotime($startDate);
$rangeEndTs = strtotime($endDate);
if ($rangeStartTs === false || $rangeEndTs === false) {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate = $today;
} elseif (($rangeEndTs - $rangeStartTs) > ($maxRangeDays - 1) * 86400) {
    $startDate = date('Y-m-d', $rangeEndTs - ($maxRangeDays - 1) * 86400);
    $preset = 'custom';
}

/* ------------------------------------------------------------
 * 1. Query Sesi Rental PS Selesai & Lunas
 * ------------------------------------------------------------ */
$psSessions = [];
$psSessionSql = 'SELECT
        b.id AS session_id,
        b.station_id,
        b.customer_name,
        b.billing_mode,
        b.duration_minutes,
        b.start_time,
        b.actual_end_time,
        b.ps_cost,
        b.fnb_cost,
        b.total_cost,
        s.name AS station_name,
        s.console_type
    FROM billing_sessions b
    INNER JOIN stations s ON s.id = b.station_id
    WHERE b.session_status = \'completed\'
      AND b.payment_status = \'paid\'
      AND DATE(b.actual_end_time) BETWEEN ? AND ?
    ORDER BY b.actual_end_time DESC, b.id DESC';

$psStmt = mysqli_prepare($conn, $psSessionSql);
if ($psStmt !== false) {
    mysqli_stmt_bind_param($psStmt, 'ss', $startDate, $endDate);
    mysqli_stmt_execute($psStmt);
    $psResult = mysqli_stmt_get_result($psStmt);
    if ($psResult !== false) {
        while ($row = mysqli_fetch_assoc($psResult)) {
            $psSessions[] = $row;
        }
        mysqli_free_result($psResult);
    }
    mysqli_stmt_close($psStmt);
}

/* ------------------------------------------------------------
 * 2. Query Transaksi Walk-in Cafe Lunas
 * ------------------------------------------------------------ */
$walkinOrders = [];
$walkinSql = 'SELECT
        o.id,
        o.customer_name,
        o.total_amount,
        o.ordered_at,
        GROUP_CONCAT(CONCAT(i.quantity, \'x \', m.name) ORDER BY i.id SEPARATOR \', \') AS item_summary
    FROM fnb_orders o
    LEFT JOIN fnb_order_items i ON i.order_id = o.id
    LEFT JOIN fnb_menus m ON m.id = i.menu_id
    WHERE o.session_id IS NULL
      AND o.payment_status = \'paid\'
      AND DATE(o.ordered_at) BETWEEN ? AND ?
    GROUP BY o.id, o.customer_name, o.total_amount, o.ordered_at
    ORDER BY o.ordered_at DESC, o.id DESC';

$walkinStmt = mysqli_prepare($conn, $walkinSql);
if ($walkinStmt !== false) {
    mysqli_stmt_bind_param($walkinStmt, 'ss', $startDate, $endDate);
    mysqli_stmt_execute($walkinStmt);
    $walkinResult = mysqli_stmt_get_result($walkinStmt);
    if ($walkinResult !== false) {
        while ($row = mysqli_fetch_assoc($walkinResult)) {
            $walkinOrders[] = $row;
        }
        mysqli_free_result($walkinResult);
    }
    mysqli_stmt_close($walkinStmt);
}

/* ------------------------------------------------------------
 * 3. Kalkulasi 4 Kartu Ringkasan
 * ------------------------------------------------------------ */
$totalPsCount = count($psSessions);
$totalWalkinCount = count($walkinOrders);
$totalTransactions = $totalPsCount + $totalWalkinCount;

$totalPsRevenue = 0;
$totalFnbSessionRevenue = 0;
foreach ($psSessions as $sess) {
    $totalPsRevenue += (int) $sess['ps_cost'];
    $totalFnbSessionRevenue += (int) $sess['fnb_cost'];
}

$totalFnbWalkinRevenue = 0;
foreach ($walkinOrders as $order) {
    $totalFnbWalkinRevenue += (int) $order['total_amount'];
}

$totalFnbRevenue = $totalFnbSessionRevenue + $totalFnbWalkinRevenue;
$grandTotalRevenue = $totalPsRevenue + $totalFnbRevenue;

/* ------------------------------------------------------------
 * 4. Agregasi Harian untuk Chart.js
 * ------------------------------------------------------------ */
$chartMap = [];
$currTs = strtotime($startDate);
$endTs = strtotime($endDate);

while ($currTs <= $endTs) {
    $dKey = date('Y-m-d', $currTs);
    $chartMap[$dKey] = [
        'label' => date('d M', $currTs),
        'ps' => 0,
        'fnb' => 0,
    ];
    $currTs = strtotime('+1 day', $currTs);
}

foreach ($psSessions as $sess) {
    $dateKey = date('Y-m-d', strtotime((string) $sess['actual_end_time']));
    if (isset($chartMap[$dateKey])) {
        $chartMap[$dateKey]['ps'] += (int) $sess['ps_cost'];
        $chartMap[$dateKey]['fnb'] += (int) $sess['fnb_cost'];
    }
}

foreach ($walkinOrders as $order) {
    $dateKey = date('Y-m-d', strtotime((string) $order['ordered_at']));
    if (isset($chartMap[$dateKey])) {
        $chartMap[$dateKey]['fnb'] += (int) $order['total_amount'];
    }
}

$chartLabels = [];
$chartPsData = [];
$chartFnbData = [];
foreach ($chartMap as $day) {
    $chartLabels[] = $day['label'];
    $chartPsData[] = $day['ps'];
    $chartFnbData[] = $day['fnb'];
}

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <?php require_once dirname(__DIR__) . '/includes/topbar.php'; ?>

        <main class="page-content">
            <section class="page-intro">
                <div>
                    <span class="eyebrow"><i data-lucide="bar-chart-3"></i>Laporan Keuangan</span>
                    <h2 class="page-heading">Pendapatan &amp; Riwayat Transaksi</h2>
                    <p class="page-lead">
                        Periode: <strong><?= e(date('d M Y', strtotime($startDate))) ?></strong>
                        s.d. <strong><?= e(date('d M Y', strtotime($endDate))) ?></strong>
                    </p>
                </div>
                <div class="row no-print">
                    <button class="btn btn-secondary" type="button" onclick="window.print()">
                        <i data-lucide="printer"></i>Cetak Laporan
                    </button>
                </div>
            </section>

            <!-- Cetak Print Header (Hanya muncul saat print) -->
            <div class="print-only print-header">
                <h2><?= e($siteName) ?> &mdash; LAPORAN PENDAPATAN</h2>
                <p>Periode: <?= e(date('d M Y', strtotime($startDate))) ?> &ndash; <?= e(date('d M Y', strtotime($endDate))) ?> | Dicetak: <?= e(date('d M Y H:i')) ?> WIB</p>
            </div>

            <!-- Filter Toolbar -->
            <section class="card filter-card no-print">
                <form class="report-filter-form" method="get" action="<?= e(base_url('pages/report.php')) ?>">
                    <div class="filter-pills" role="group" aria-label="Preset rentang tanggal">
                        <a
                            class="filter-pill <?= $preset === 'today' ? 'is-active' : '' ?>"
                            href="<?= e(base_url('pages/report.php?preset=today')) ?>"
                        >Hari Ini</a>
                        <a
                            class="filter-pill <?= $preset === '7days' ? 'is-active' : '' ?>"
                            href="<?= e(base_url('pages/report.php?preset=7days')) ?>"
                        >7 Hari Terakhir</a>
                        <a
                            class="filter-pill <?= $preset === 'month' ? 'is-active' : '' ?>"
                            href="<?= e(base_url('pages/report.php?preset=month')) ?>"
                        >Bulan Ini</a>
                    </div>

                    <div class="report-date-inputs">
                        <input type="hidden" name="preset" value="custom">
                        <div class="date-field">
                            <label for="startDate">Dari</label>
                            <input
                                class="input input-sm text-mono"
                                type="date"
                                id="startDate"
                                name="start_date"
                                value="<?= e($startDate) ?>"
                                required
                            >
                        </div>
                        <div class="date-field">
                            <label for="endDate">Sampai</label>
                            <input
                                class="input input-sm text-mono"
                                type="date"
                                id="endDate"
                                name="end_date"
                                value="<?= e($endDate) ?>"
                                required
                            >
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i data-lucide="filter"></i>Terapkan
                        </button>
                    </div>
                </form>
            </section>

            <!-- 4 Kartu Ringkasan Omzet -->
            <section class="stat-grid report-stat-grid" aria-label="Ringkasan Pendapatan">
                <article class="stat-tile">
                    <span class="stat-icon"><i data-lucide="receipt"></i></span>
                    <div class="stat-meta">
                        <span class="stat-label">Total Transaksi</span>
                        <strong class="stat-value text-mono"><?= e((string) $totalTransactions) ?></strong>
                        <span class="form-hint"><?= e((string) $totalPsCount) ?> sesi PS &middot; <?= e((string) $totalWalkinCount) ?> walk-in</span>
                    </div>
                </article>

                <article class="stat-tile">
                    <span class="stat-icon is-ps"><i data-lucide="gamepad-2"></i></span>
                    <div class="stat-meta">
                        <span class="stat-label">Pendapatan Rental PS</span>
                        <strong class="stat-value text-mono"><?= e(format_rupiah($totalPsRevenue)) ?></strong>
                        <span class="form-hint"><?= e((string) $totalPsCount) ?> sesi rental selesai</span>
                    </div>
                </article>

                <article class="stat-tile">
                    <span class="stat-icon is-fnb"><i data-lucide="coffee"></i></span>
                    <div class="stat-meta">
                        <span class="stat-label">Pendapatan Cafe / F&amp;B</span>
                        <strong class="stat-value text-mono"><?= e(format_rupiah($totalFnbRevenue)) ?></strong>
                        <span class="form-hint">Sesi PS + walk-in</span>
                    </div>
                </article>

                <article class="stat-tile is-highlight">
                    <span class="stat-icon is-omzet"><i data-lucide="banknote"></i></span>
                    <div class="stat-meta">
                        <span class="stat-label">Total Omzet Gabungan</span>
                        <strong class="stat-value text-mono stat-value--large"><?= e(format_rupiah($grandTotalRevenue)) ?></strong>
                        <span class="form-hint">Rental PS + Cafe F&amp;B</span>
                    </div>
                </article>
            </section>

            <!-- Grafik Pendapatan Harian Chart.js -->
            <section class="card chart-card no-print">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="line-chart"></i>Grafik Pendapatan Harian</h3>
                    <div class="chart-legend-custom">
                        <span class="legend-dot is-ps"></span>Rental PS
                        <span class="legend-dot is-fnb"></span>Cafe / F&amp;B
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="reportChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- Tabel Riwayat Rental PS -->
            <section class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="gamepad-2"></i>Riwayat Rental PlayStation</h3>
                    <span class="badge badge-muted"><?= e((string) $totalPsCount) ?> Sesi Selesai</span>
                </div>
                <div class="card-body">
                    <?php if ($psSessions === []): ?>
                        <p class="ps-empty-filter">Tidak ada sesi rental PS selesai pada rentang tanggal ini.</p>
                    <?php else: ?>
                        <div class="table-wrap table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Waktu Selesai</th>
                                        <th scope="col">Unit PS</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col">Mode &amp; Durasi</th>
                                        <th scope="col" class="text-right">Biaya PS</th>
                                        <th scope="col" class="text-right">Biaya F&amp;B</th>
                                        <th scope="col" class="text-right">Grand Total</th>
                                        <th scope="col" class="text-center no-print">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($psSessions as $sess): ?>
                                        <?php
                                        $duration = format_duration_label((int) ($sess['duration_minutes'] ?? 0));
                                        $modeLabel = (string) $sess['billing_mode'] === 'open' ? 'Open' : 'Paket';
                                        ?>
                                        <tr>
                                            <td class="cell-muted text-mono">
                                                <?= e(date('d M Y H:i', (int) strtotime((string) $sess['actual_end_time']))) ?>
                                            </td>
                                            <td>
                                                <strong><?= e((string) $sess['station_name']) ?></strong>
                                                <span class="badge badge-muted badge--inline"><?= e((string) $sess['console_type']) ?></span>
                                            </td>
                                            <td><?= e((string) $sess['customer_name']) ?></td>
                                            <td>
                                                <span class="badge badge-info badge--inline"><?= e($modeLabel) ?></span>
                                                <span class="text-mono"><?= e($duration) ?></span>
                                            </td>
                                            <td class="text-right cell-mono"><?= e(format_rupiah((int) $sess['ps_cost'])) ?></td>
                                            <td class="text-right cell-mono"><?= e(format_rupiah((int) $sess['fnb_cost'])) ?></td>
                                            <td class="text-right cell-mono text-primary font-bold">
                                                <?= e(format_rupiah((int) $sess['total_cost'])) ?>
                                            </td>
                                            <td class="text-center no-print">
                                                <a
                                                    class="btn btn-secondary btn-sm"
                                                    href="<?= e(base_url('pages/session_detail.php?id=' . $sess['session_id'])) ?>"
                                                >
                                                    Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Tabel Riwayat Walk-in Cafe -->
            <section class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="coffee"></i>Riwayat Pesanan Cafe (Walk-in)</h3>
                    <span class="badge badge-muted"><?= e((string) $totalWalkinCount) ?> Pesanan Lunas</span>
                </div>
                <div class="card-body">
                    <?php if ($walkinOrders === []): ?>
                        <p class="ps-empty-filter">Tidak ada transaksi walk-in cafe pada rentang tanggal ini.</p>
                    <?php else: ?>
                        <div class="table-wrap table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Waktu Pesan</th>
                                        <th scope="col">Pembeli</th>
                                        <th scope="col">Daftar Item</th>
                                        <th scope="col" class="text-right">Total</th>
                                        <th scope="col" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($walkinOrders as $wOrder): ?>
                                        <tr>
                                            <td class="cell-muted text-mono">
                                                <?= e(date('d M Y H:i', (int) strtotime((string) $wOrder['ordered_at']))) ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info badge--inline">Walk-in</span>
                                                <strong><?= e((string) ($wOrder['customer_name'] ?: 'Walk-in Customer')) ?></strong>
                                            </td>
                                            <td class="cell-muted"><?= e((string) ($wOrder['item_summary'] ?: '-')) ?></td>
                                            <td class="text-right cell-mono font-bold text-primary">
                                                <?= e(format_rupiah((int) $wOrder['total_amount'])) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-success">LUNAS</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<?php
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$chartLabelsJson = json_encode($chartLabels, $jsonFlags);
$chartPsJson = json_encode($chartPsData, $jsonFlags | JSON_NUMERIC_CHECK);
$chartFnbJson = json_encode($chartFnbData, $jsonFlags | JSON_NUMERIC_CHECK);
?>
<script>
window.DGS_REPORT_CHART = {
    labels: <?= $chartLabelsJson !== false ? $chartLabelsJson : '[]' ?>,
    psData: <?= $chartPsJson !== false ? $chartPsJson : '[]' ?>,
    fnbData: <?= $chartFnbJson !== false ? $chartFnbJson : '[]' ?>
};
</script>

<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
