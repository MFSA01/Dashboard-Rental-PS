<?php
/**
 * pages/billing.php
 * Dashboard utama billing: grid unit PlayStation, timer live, dan statistik harian.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$pageTitle = 'INVENTORY BILLING';
$pageSubtitle = 'Pantau unit PlayStation & transaksi berjalan';

$minMinutes = (int) get_setting($conn, 'open_billing_min_minutes', '30');
$minMinutes = $minMinutes > 0 ? $minMinutes : 30;
$roundingMinutes = (int) get_setting($conn, 'open_billing_rounding_minutes', '15');
$roundingMinutes = $roundingMinutes > 0 ? $roundingMinutes : 15;

$serverNow = time();

/* ------------------------------------------------------------
 * Statistik ringkas hari ini
 * ------------------------------------------------------------ */
$playingCount = 0;
$idleCount = 0;
$todayRevenue = 0;

$statusResult = mysqli_query(
    $conn,
    "SELECT
        SUM(status = 'playing') AS playing_total,
        SUM(status = 'idle') AS idle_total
     FROM stations"
);
if ($statusResult !== false) {
    $statusRow = mysqli_fetch_assoc($statusResult) ?? [];
    $playingCount = (int) ($statusRow['playing_total'] ?? 0);
    $idleCount = (int) ($statusRow['idle_total'] ?? 0);
    mysqli_free_result($statusResult);
}

$revenueResult = mysqli_query(
    $conn,
    "SELECT
        COALESCE((
            SELECT SUM(total_cost)
            FROM billing_sessions
            WHERE session_status = 'completed'
              AND DATE(actual_end_time) = CURDATE()
        ), 0)
        +
        COALESCE((
            SELECT SUM(total_amount)
            FROM fnb_orders
            WHERE session_id IS NULL
              AND payment_status = 'paid'
              AND DATE(ordered_at) = CURDATE()
        ), 0) AS revenue_today"
);
if ($revenueResult !== false) {
    $revenueRow = mysqli_fetch_assoc($revenueResult) ?? [];
    $todayRevenue = (int) ($revenueRow['revenue_today'] ?? 0);
    mysqli_free_result($revenueResult);
}

/* ------------------------------------------------------------
 * Semua unit + sesi aktifnya
 * ------------------------------------------------------------ */
$stations = [];
$stationSql = "SELECT
        s.id AS station_id,
        s.name AS station_name,
        s.console_type,
        s.hourly_rate,
        s.status AS station_status,
        b.id AS session_id,
        b.customer_name,
        b.billing_mode,
        b.hourly_rate_snapshot,
        b.duration_minutes,
        b.start_time,
        b.scheduled_end_time,
        b.ps_cost,
        b.fnb_cost,
        b.total_cost,
        b.notes
    FROM stations s
    LEFT JOIN billing_sessions b
        ON b.station_id = s.id
       AND b.session_status = 'active'
    ORDER BY s.id ASC, b.id DESC";

$stationResult = mysqli_query($conn, $stationSql);
if ($stationResult !== false) {
    while ($row = mysqli_fetch_assoc($stationResult)) {
        $stationId = (int) $row['station_id'];
        if (isset($stations[$stationId])) {
            continue; // jaga-jaga bila ada lebih dari satu sesi aktif
        }
        $stations[$stationId] = $row;
    }
    mysqli_free_result($stationResult);
}

/* ------------------------------------------------------------
 * Susun data tampilan per kartu
 * ------------------------------------------------------------ */
$cards = [];

foreach ($stations as $row) {
    $stationId = (int) $row['station_id'];
    $sessionId = $row['session_id'] !== null ? (int) $row['session_id'] : 0;
    $consoleType = (string) $row['console_type'];
    $hourlyRate = (int) $row['hourly_rate'];
    $stationStatus = (string) $row['station_status'];

    $state = 'idle';
    $mode = '';
    $remainingSeconds = 0;
    $elapsedSeconds = 0;
    $durationLabel = '';
    $psCost = 0;
    $fnbCost = 0;
    $totalCost = 0;
    $scheduledEndTs = 0;
    $startTs = 0;
    $packageMinutes = 0;
    $customerName = '';

    if ($sessionId > 0) {
        $mode = (string) ($row['billing_mode'] ?? 'package');
        $customerName = (string) ($row['customer_name'] ?? '');
        $snapshotRate = (int) ($row['hourly_rate_snapshot'] ?? $hourlyRate);
        $psCost = (int) ($row['ps_cost'] ?? 0);
        $fnbCost = (int) ($row['fnb_cost'] ?? 0);
        $totalCost = (int) ($row['total_cost'] ?? 0);
        $startTs = $row['start_time'] !== null ? (int) strtotime((string) $row['start_time']) : $serverNow;
        $elapsedSeconds = max(0, $serverNow - $startTs);

        if ($mode === 'open') {
            $estimate = calculate_open_billing_cost($elapsedSeconds, $snapshotRate, $minMinutes, $roundingMinutes);
            $psCost = $estimate['cost'];
            $totalCost = $psCost + $fnbCost;
            $durationLabel = format_duration_label($estimate['billable_minutes']);
            $state = 'playing';
        } else {
            $packageMinutes = (int) ($row['duration_minutes'] ?? 0);
            $durationLabel = format_duration_label($packageMinutes);

            if ($row['scheduled_end_time'] !== null) {
                $scheduledEndTs = (int) strtotime((string) $row['scheduled_end_time']);
            } else {
                $scheduledEndTs = $startTs + ($packageMinutes * 60);
            }

            $remainingSeconds = max(0, $scheduledEndTs - $serverNow);
            $state = $scheduledEndTs <= $serverNow ? 'timeup' : 'playing';
        }
    } elseif ($stationStatus === 'maintenance') {
        $state = 'maintenance';
    }

    $timerSeconds = $state === 'timeup' ? 0 : ($mode === 'open' ? $elapsedSeconds : $remainingSeconds);
    $hours = intdiv($timerSeconds, 3600);
    $minutesPart = intdiv($timerSeconds % 3600, 60);
    $secondsPart = $timerSeconds % 60;

    $cards[] = [
        'station_id' => $stationId,
        'station_name' => (string) $row['station_name'],
        'console_type' => $consoleType,
        'hourly_rate' => $hourlyRate,
        'session_id' => $sessionId,
        'customer_name' => $customerName,
        'mode' => $mode,
        'mode_label' => $mode === 'open' ? 'Open Billing' : 'Paket ' . ($packageMinutes > 0 ? format_duration_label($packageMinutes) : $durationLabel),
        'duration_label' => $durationLabel,
        'state' => $state,
        'remaining_seconds' => $remainingSeconds,
        'elapsed_seconds' => $elapsedSeconds,
        'start_ts' => $startTs,
        'scheduled_end_ts' => $scheduledEndTs,
        'ps_cost' => $psCost,
        'fnb_cost' => $fnbCost,
        'total_cost' => $totalCost,
        'hours' => $hours,
        'minutes' => $minutesPart,
        'seconds' => $secondsPart,
    ];
}

$totalUnits = count($cards);

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <?php require_once dirname(__DIR__) . '/includes/topbar.php'; ?>

        <main class="page-content">
            <section class="page-intro">
                <div>
                    <span class="eyebrow"><i data-lucide="monitor-play"></i>Dashboard Real-Time</span>
                    <h2 class="page-heading">Unit PlayStation</h2>
                    <p class="page-lead">Pantau ketersediaan unit, durasi bermain, dan tagihan berjalan. Timer diperbarui otomatis setiap detik.</p>
                </div>
                <span class="badge badge-muted"><i data-lucide="activity"></i><?= e((string) $totalUnits) ?> Unit Terdaftar</span>
            </section>

            <div class="stack">
                <section class="summary-strip" aria-label="Ringkasan hari ini">
                    <article class="summary-item">
                        <span class="summary-icon is-live"><i data-lucide="gamepad-2"></i></span>
                        <div class="summary-meta">
                            <span class="summary-label">Unit Aktif</span>
                            <span class="summary-value" data-summary="playing"><?= e((string) $playingCount) ?></span>
                        </div>
                    </article>
                    <article class="summary-item">
                        <span class="summary-icon"><i data-lucide="wifi"></i></span>
                        <div class="summary-meta">
                            <span class="summary-label">Unit Tersedia</span>
                            <span class="summary-value" data-summary="idle"><?= e((string) $idleCount) ?></span>
                        </div>
                    </article>
                    <article class="summary-item is-highlight">
                        <span class="summary-icon is-money"><i data-lucide="banknote"></i></span>
                        <div class="summary-meta">
                            <span class="summary-label">Pendapatan Hari Ini</span>
                            <span class="summary-value text-mono"><?= e(format_rupiah($todayRevenue)) ?></span>
                        </div>
                    </article>
                </section>

                <section class="billing-toolbar">
                    <div class="search-box">
                        <span class="search-box-icon"><i data-lucide="search"></i></span>
                        <input
                            class="input search-input"
                            type="search"
                            id="psSearch"
                            placeholder="Cari unit PS atau nama customer..."
                            autocomplete="off"
                            aria-label="Cari unit PS atau nama customer"
                        >
                    </div>

                    <div class="filter-pills" role="group" aria-label="Filter status unit">
                        <button class="filter-pill is-active" type="button" data-filter="all" aria-pressed="true">Semua</button>
                        <button class="filter-pill" type="button" data-filter="playing" aria-pressed="false">
                            <span class="pill-dot is-playing"></span>Aktif
                        </button>
                        <button class="filter-pill" type="button" data-filter="timeup" aria-pressed="false">
                            <span class="pill-dot is-timeup"></span>Waktu Habis
                        </button>
                        <button class="filter-pill" type="button" data-filter="idle" aria-pressed="false">
                            <span class="pill-dot is-idle"></span>Idle
                        </button>
                    </div>
                </section>

                <section
                    class="ps-grid"
                    id="psGrid"
                    data-server-now="<?= e((string) $serverNow) ?>"
                    data-min-minutes="<?= e((string) $minMinutes) ?>"
                    data-rounding-minutes="<?= e((string) $roundingMinutes) ?>"
                    aria-label="Grid unit PlayStation"
                >
                    <?php foreach ($cards as $card): ?>
                        <?php
                        $state = $card['state'];
                        $isSession = $card['session_id'] > 0;
                        $cardClass = 'ps-card ps-card--' . $state;
                        $searchText = mb_strtolower($card['station_name'] . ' ' . $card['customer_name'], 'UTF-8');
                        ?>
                        <article
                            class="<?= e($cardClass) ?>"
                            data-card-state="<?= e($state) ?>"
                            data-station-id="<?= e((string) $card['station_id']) ?>"
                            data-station-name="<?= e($card['station_name']) ?>"
                            data-customer-name="<?= e($card['customer_name']) ?>"
                            data-search="<?= e($searchText) ?>"
                            data-console="<?= e($card['console_type']) ?>"
                            data-hourly-rate="<?= e((string) $card['hourly_rate']) ?>"
                            data-session-id="<?= e((string) $card['session_id']) ?>"
                            data-billing-mode="<?= e($card['mode']) ?>"
                            data-duration-label="<?= e($card['duration_label']) ?>"
                            data-mode-label="<?= e($card['mode_label']) ?>"
                            data-start-time="<?= e((string) $card['start_ts']) ?>"
                            data-scheduled-end="<?= e((string) $card['scheduled_end_ts']) ?>"
                            data-ps-cost="<?= e((string) $card['ps_cost']) ?>"
                            data-fnb-cost="<?= e((string) $card['fnb_cost']) ?>"
                            data-total-cost="<?= e((string) $card['total_cost']) ?>"
                        >
                            <header class="ps-card-head">
                                <div class="ps-card-id">
                                    <h3 class="ps-card-name"><?= e($card['station_name']) ?></h3>
                                    <span class="ps-console-tag"><?= e($card['console_type']) ?></span>
                                </div>
                                <?php if ($isSession): ?>
                                    <a class="ps-detail-link" href="<?= e(base_url('pages/session_detail.php?id=' . $card['session_id'])) ?>">
                                        Details <i data-lucide="chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="ps-detail-link is-disabled" aria-disabled="true">Details</span>
                                <?php endif; ?>
                            </header>

                            <div class="ps-badges">
                                <?php if ($state === 'idle'): ?>
                                    <span class="badge badge-success">IDLE</span>
                                <?php elseif ($state === 'maintenance'): ?>
                                    <span class="badge badge-muted"><i data-lucide="ban"></i>MAINTENANCE</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">BELUM DIBAYAR</span>
                                    <span class="ps-mode-pill"><?= e($card['mode_label']) ?></span>
                                    <?php if ($card['mode'] === 'package'): ?>
                                        <span class="badge ps-badge-timeup <?= $state === 'timeup' ? '' : 'is-hidden' ?>" data-timeup-badge>
                                            <i data-lucide="alarm-clock"></i>BILLING HABIS
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="ps-rate">
                                <span class="text-mono"><?= e(format_rupiah($card['hourly_rate'])) ?></span>
                                <span class="ps-rate-suffix">/ Jam</span>
                                <?php if ($isSession && $card['mode'] === 'package'): ?>
                                    <span class="ps-rate-duration"><?= e($card['duration_label']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="ps-timer" data-timer role="timer" aria-live="off">
                                <div class="ps-timer-unit">
                                    <span class="ps-timer-value" data-timer-part="hours"><?= e(str_pad((string) $card['hours'], 2, '0', STR_PAD_LEFT)) ?></span>
                                    <span class="ps-timer-label">JJ</span>
                                </div>
                                <div class="ps-timer-unit">
                                    <span class="ps-timer-value" data-timer-part="minutes"><?= e(str_pad((string) $card['minutes'], 2, '0', STR_PAD_LEFT)) ?></span>
                                    <span class="ps-timer-label">MM</span>
                                </div>
                                <div class="ps-timer-unit">
                                    <span class="ps-timer-value" data-timer-part="seconds"><?= e(str_pad((string) $card['seconds'], 2, '0', STR_PAD_LEFT)) ?></span>
                                    <span class="ps-timer-label">DD</span>
                                </div>
                            </div>

                            <div class="ps-actions">
                                <?php if ($state === 'idle'): ?>
                                    <button
                                        class="btn btn-primary btn-block"
                                        type="button"
                                        data-action="start"
                                        data-station-id="<?= e((string) $card['station_id']) ?>"
                                        data-station-name="<?= e($card['station_name']) ?>"
                                        data-console="<?= e($card['console_type']) ?>"
                                        data-hourly-rate="<?= e((string) $card['hourly_rate']) ?>"
                                    >
                                        <i data-lucide="play"></i>Mulai Main
                                    </button>
                                <?php elseif ($state === 'maintenance'): ?>
                                    <button class="btn btn-secondary btn-block" type="button" disabled>
                                        <i data-lucide="ban"></i>Dalam Perbaikan
                                    </button>
                                <?php else: ?>
                                    <a class="btn btn-secondary btn-sm" href="<?= e(base_url('pages/cafe.php?session_id=' . $card['session_id'])) ?>">
                                        <i data-lucide="coffee"></i>+ F&amp;B
                                    </a>
                                    <?php if ($card['mode'] === 'package'): ?>
                                        <button
                                            class="btn btn-secondary btn-sm"
                                            type="button"
                                            data-action="extend"
                                            data-session-id="<?= e((string) $card['session_id']) ?>"
                                            data-station-name="<?= e($card['station_name']) ?>"
                                            data-hourly-rate="<?= e((string) $card['hourly_rate']) ?>"
                                        >
                                            <i data-lucide="clock"></i>+ Waktu
                                        </button>
                                    <?php endif; ?>
                                    <button
                                        class="btn btn-danger btn-icon"
                                        type="button"
                                        data-action="pay"
                                        data-session-id="<?= e((string) $card['session_id']) ?>"
                                        title="Bayar &amp; Selesaikan Sesi"
                                        aria-label="Bayar dan selesaikan sesi <?= e($card['station_name']) ?>"
                                    >
                                        <i data-lucide="power"></i>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <footer class="ps-card-foot">
                                <span class="ps-foot-item">
                                    <span class="ps-foot-label">Customer</span>
                                    <strong class="ps-foot-value"><?= e($isSession && $card['customer_name'] !== '' ? $card['customer_name'] : '-') ?></strong>
                                </span>
                                <span class="ps-foot-item ps-foot-item--total">
                                    <span class="ps-foot-label">Total</span>
                                    <strong class="ps-foot-value text-mono" data-cost="total"><?= e(format_rupiah($isSession ? $card['total_cost'] : 0)) ?></strong>
                                </span>
                            </footer>
                        </article>
                    <?php endforeach; ?>

                    <?php if ($cards === []): ?>
                        <p class="ps-empty">Belum ada unit PlayStation yang terdaftar.</p>
                    <?php endif; ?>
                </section>

                <p class="ps-empty-filter" id="psEmptyFilter" hidden>Unit tidak ditemukan. Coba ubah kata kunci atau filter status.</p>
            </div>
        </main>
    </div>
</div>

<!-- ============================================================
     Modal Mulai Main
     ============================================================ -->
<div class="modal" id="modalStart" role="dialog" aria-modal="true" aria-labelledby="modalStartTitle">
    <div class="modal-dialog">
        <form class="modal-form" method="post" action="<?= e(base_url('actions/billing_start.php')) ?>" id="startForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalStartTitle">Mulai Main</h3>
                    <span class="modal-subtitle" id="startStationMeta">-</span>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body stack">
                <input type="hidden" name="station_id" id="startStationId" value="">
                <input type="hidden" name="duration_minutes" id="startDurationMinutes" value="60">

                <div class="form-group">
                    <label class="form-label" for="startCustomer">
                        <i data-lucide="user"></i>Nama Customer
                    </label>
                    <input
                        class="input"
                        type="text"
                        id="startCustomer"
                        name="customer_name"
                        placeholder="cth. Affan"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="form-group">
                    <span class="form-label"><i data-lucide="timer"></i>Mode Billing</span>
                    <div class="choice-grid choice-grid--2">
                        <label class="choice">
                            <input type="radio" name="billing_mode" value="package" checked data-mode-radio>
                            <span class="choice-body">
                                <span class="choice-title">Paket (Countdown)</span>
                                <span class="choice-note">Durasi tetap, timer menghitung mundur</span>
                            </span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="billing_mode" value="open" data-mode-radio>
                            <span class="choice-body">
                                <span class="choice-title">Open Billing (Bebas)</span>
                                <span class="choice-note">Timer menghitung naik, bayar sesuai durasi</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="startPackageFields">
                    <span class="form-label"><i data-lucide="hourglass"></i>Durasi Paket</span>
                    <div class="choice-grid choice-grid--4" id="startDurationPresets">
                        <label class="choice choice--compact">
                            <input type="radio" name="duration_preset" value="60" checked>
                            <span class="choice-body"><span class="choice-title">1 Jam</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="duration_preset" value="120">
                            <span class="choice-body"><span class="choice-title">2 Jam</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="duration_preset" value="180">
                            <span class="choice-body"><span class="choice-title">3 Jam</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="duration_preset" value="300">
                            <span class="choice-body"><span class="choice-title">5 Jam</span></span>
                        </label>
                    </div>

                    <div class="form-group form-group--tight">
                        <label class="form-label" for="startCustomHours">Jam Kustom</label>
                        <div class="input-suffix">
                            <input
                                class="input"
                                type="number"
                                id="startCustomHours"
                                min="0.5"
                                max="24"
                                step="0.5"
                                placeholder="cth. 1.5"
                                inputmode="decimal"
                            >
                            <span class="input-suffix-text">Jam</span>
                        </div>
                        <span class="form-hint">Minimal 30 menit. Isi untuk menimpa pilihan cepat.</span>
                    </div>
                </div>

                <div class="preview-box">
                    <div class="preview-row">
                        <span id="startPreviewLabel">Estimasi biaya paket</span>
                        <strong class="text-mono" id="startPreviewCost">Rp 0</strong>
                    </div>
                    <div class="preview-row preview-row--muted">
                        <span>Tarif per jam</span>
                        <span class="text-mono" id="startPreviewRate">Rp 0</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="startNotes">
                        <i data-lucide="notebook-pen"></i>Catatan <span class="form-label-optional">(opsional)</span>
                    </label>
                    <textarea class="textarea" id="startNotes" name="notes" maxlength="255" placeholder="cth. Paket 2 Jam, bayar di akhir"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit"><i data-lucide="play"></i>Mulai Sesi</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     Modal Tambah Waktu
     ============================================================ -->
<div class="modal" id="modalExtend" role="dialog" aria-modal="true" aria-labelledby="modalExtendTitle">
    <div class="modal-dialog">
        <form class="modal-form" method="post" action="<?= e(base_url('actions/billing_extend.php')) ?>" id="extendForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalExtendTitle">Tambah Waktu</h3>
                    <span class="modal-subtitle" id="extendStationMeta">-</span>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body stack">
                <input type="hidden" name="session_id" id="extendSessionId" value="">
                <input type="hidden" name="add_minutes" id="extendMinutes" value="60">
                <input type="hidden" name="context" value="billing">

                <div class="form-group">
                    <span class="form-label"><i data-lucide="clock"></i>Tambahan Durasi</span>
                    <div class="choice-grid choice-grid--4" id="extendPresets">
                        <label class="choice choice--compact">
                            <input type="radio" name="extend_preset" value="30">
                            <span class="choice-body"><span class="choice-title">30 Menit</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="extend_preset" value="60" checked>
                            <span class="choice-body"><span class="choice-title">1 Jam</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="extend_preset" value="120">
                            <span class="choice-body"><span class="choice-title">2 Jam</span></span>
                        </label>
                        <label class="choice choice--compact">
                            <input type="radio" name="extend_preset" value="180">
                            <span class="choice-body"><span class="choice-title">3 Jam</span></span>
                        </label>
                    </div>
                </div>

                <div class="preview-box">
                    <div class="preview-row">
                        <span>Tambahan biaya</span>
                        <strong class="text-mono" id="extendPreviewCost">Rp 0</strong>
                    </div>
                    <div class="preview-row preview-row--muted">
                        <span>Tarif per jam</span>
                        <span class="text-mono" id="extendPreviewRate">Rp 0</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit"><i data-lucide="clock"></i>Tambah Waktu</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     Modal Bayar & Selesaikan Sesi
     ============================================================ -->
<div class="modal" id="modalPay" role="dialog" aria-modal="true" aria-labelledby="modalPayTitle">
    <div class="modal-dialog">
        <form class="modal-form" method="post" action="<?= e(base_url('actions/billing_pay.php')) ?>" id="payForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalPayTitle">Bayar &amp; Selesaikan Sesi</h3>
                    <span class="modal-subtitle" id="payStationMeta">-</span>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body stack">
                <input type="hidden" name="session_id" id="paySessionId" value="">

                <div class="summary-card">
                    <div class="summary-card-row">
                        <span>Customer</span>
                        <strong id="payCustomer">-</strong>
                    </div>
                    <div class="summary-card-row">
                        <span>Mode</span>
                        <strong id="payMode">-</strong>
                    </div>
                    <div class="summary-card-row">
                        <span>Durasi</span>
                        <strong id="payDuration">-</strong>
                    </div>
                </div>

                <div class="bill-list">
                    <div class="bill-row">
                        <span>Biaya Rental PS</span>
                        <span class="text-mono" id="payPsCost">Rp 0</span>
                    </div>
                    <div class="bill-row">
                        <span>Total Cafe / F&amp;B</span>
                        <span class="text-mono" id="payFnbCost">Rp 0</span>
                    </div>
                    <div class="bill-row bill-row--total">
                        <span>Grand Total</span>
                        <span class="text-mono" id="payGrandTotal">Rp 0</span>
                    </div>
                </div>

                <p class="form-hint" id="payNote">Total Open Billing dihitung otomatis dari durasi berjalan saat ini.</p>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                <button class="btn btn-danger" type="submit"><i data-lucide="power"></i>Bayar &amp; Selesaikan</button>
            </div>
        </form>
    </div>
</div>
<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
