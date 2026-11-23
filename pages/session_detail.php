<?php
/**
 * pages/session_detail.php
 * Detail sesi billing: info sesi, rincian F&B, ringkasan tagihan.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$sessionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($sessionId === false || $sessionId === null) {
    set_flash('error', 'Sesi Tidak Valid', 'Parameter ID sesi tidak valid.');
    redirect('pages/billing.php');
}

$sessionSql = "SELECT
        b.id AS session_id,
        b.station_id,
        b.customer_name,
        b.billing_mode,
        b.hourly_rate_snapshot,
        b.duration_minutes,
        b.start_time,
        b.scheduled_end_time,
        b.actual_end_time,
        b.ps_cost,
        b.fnb_cost,
        b.total_cost,
        b.payment_status,
        b.session_status,
        b.notes,
        s.name AS station_name,
        s.console_type,
        s.status AS station_status
    FROM billing_sessions b
    INNER JOIN stations s ON s.id = b.station_id
    WHERE b.id = ?
    LIMIT 1";

$sessionStmt = mysqli_prepare($conn, $sessionSql);
$session = null;

if ($sessionStmt !== false) {
    mysqli_stmt_bind_param($sessionStmt, 'i', $sessionId);
    mysqli_stmt_execute($sessionStmt);
    $sessionResult = mysqli_stmt_get_result($sessionStmt);
    if ($sessionResult !== false) {
        $session = mysqli_fetch_assoc($sessionResult) ?: null;
        mysqli_free_result($sessionResult);
    }
    mysqli_stmt_close($sessionStmt);
}

if ($session === null) {
    set_flash('error', 'Sesi Tidak Ditemukan', 'Data sesi billing tidak tersedia atau sudah dihapus.');
    redirect('pages/billing.php');
}

$pageTitle = 'DETAIL SESI';
$pageSubtitle = 'Unit ' . $session['station_name'] . ' · ' . $session['customer_name'];

$minMinutes = (int) get_setting($conn, 'open_billing_min_minutes', '30');
$minMinutes = $minMinutes > 0 ? $minMinutes : 30;
$roundingMinutes = (int) get_setting($conn, 'open_billing_rounding_minutes', '15');
$roundingMinutes = $roundingMinutes > 0 ? $roundingMinutes : 15;

$serverNow = time();
$isActive = (string) $session['session_status'] === 'active';
$mode = (string) $session['billing_mode'];
$hourlyRate = (int) $session['hourly_rate_snapshot'];
$startTs = (int) strtotime((string) $session['start_time']);
$elapsedSeconds = max(0, $serverNow - $startTs);
$psCost = (int) $session['ps_cost'];
$fnbCost = (int) $session['fnb_cost'];
$totalCost = (int) $session['total_cost'];
$durationLabel = format_duration_label((int) ($session['duration_minutes'] ?? 0));
$remainingSeconds = 0;
$scheduledEndTs = 0;
$isTimeUp = false;

/* Sinkronkan total F&B aktual dari pesanan sesi ini (lebih akurat daripada kolom cache). */
$actualFnbSql = "SELECT COALESCE(SUM(total_amount), 0) AS fnb_total FROM fnb_orders WHERE session_id = ?";
$actualFnbStmt = mysqli_prepare($conn, $actualFnbSql);
if ($actualFnbStmt !== false) {
    mysqli_stmt_bind_param($actualFnbStmt, 'i', $sessionId);
    mysqli_stmt_execute($actualFnbStmt);
    $actualFnbResult = mysqli_stmt_get_result($actualFnbStmt);
    if ($actualFnbResult !== false) {
        $actualFnbRow = mysqli_fetch_assoc($actualFnbResult);
        if ($actualFnbRow !== null) {
            $fnbCost = (int) ($actualFnbRow['fnb_total'] ?? 0);
        }
        mysqli_free_result($actualFnbResult);
    }
    mysqli_stmt_close($actualFnbStmt);
}

if ($mode === 'open') {
    if ($isActive) {
        $estimate = calculate_open_billing_cost($elapsedSeconds, $hourlyRate, $minMinutes, $roundingMinutes);
        $psCost = $estimate['cost'];
        $totalCost = $psCost + $fnbCost;
        $durationLabel = format_duration_label($estimate['billable_minutes']);
    } else {
        $totalCost = $psCost + $fnbCost;
    }
} elseif ($session['scheduled_end_time'] !== null) {
    $scheduledEndTs = (int) strtotime((string) $session['scheduled_end_time']);
    $remainingSeconds = max(0, $scheduledEndTs - $serverNow);
    $isTimeUp = $isActive && $scheduledEndTs <= $serverNow;
    $totalCost = $psCost + $fnbCost;
} else {
    $totalCost = $psCost + $fnbCost;
}

$timerSeconds = $mode === 'open' ? $elapsedSeconds : $remainingSeconds;

/* ------------------------------------------------------------
 * Rincian pesanan F&B pada sesi ini
 * ------------------------------------------------------------ */
$items = [];
$itemsSql = "SELECT
        o.id AS order_id,
        o.customer_name AS order_customer,
        o.total_amount AS order_total,
        o.payment_status AS order_payment,
        o.ordered_at,
        i.id AS item_id,
        i.quantity,
        i.unit_price,
        i.subtotal,
        m.name AS menu_name,
        m.category
    FROM fnb_orders o
    INNER JOIN fnb_order_items i ON i.order_id = o.id
    INNER JOIN fnb_menus m ON m.id = i.menu_id
    WHERE o.session_id = ?
    ORDER BY o.ordered_at ASC, i.id ASC";

$itemsStmt = mysqli_prepare($conn, $itemsSql);
if ($itemsStmt !== false) {
    mysqli_stmt_bind_param($itemsStmt, 'i', $sessionId);
    mysqli_stmt_execute($itemsStmt);
    $itemsResult = mysqli_stmt_get_result($itemsStmt);
    if ($itemsResult !== false) {
        while ($row = mysqli_fetch_assoc($itemsResult)) {
            $items[] = $row;
        }
        mysqli_free_result($itemsResult);
    }
    mysqli_stmt_close($itemsStmt);
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
                    <a class="back-link" href="<?= e(base_url('pages/billing.php')) ?>">
                        <i data-lucide="arrow-left"></i>Kembali ke Billing
                    </a>
                    <h2 class="page-heading"><?= e($session['station_name']) ?> — <?= e($session['customer_name']) ?></h2>
                    <p class="page-lead">
                        Tipe konsol <?= e($session['console_type']) ?> &middot; Sesi #<?= e((string) $sessionId) ?> &middot;
                        <?= e(date('d M Y H:i', $startTs)) ?> WIB
                    </p>
                </div>
                <div class="row">
                    <?php if ($isTimeUp): ?>
                        <span class="badge ps-badge-timeup"><i data-lucide="alarm-clock"></i>BILLING HABIS</span>
                    <?php endif; ?>
                    <?php if ($isActive): ?>
                        <span class="badge badge-warning">BELUM DIBAYAR</span>
                        <span class="badge badge-success"><i data-lucide="activity"></i>SESI AKTIF</span>
                    <?php else: ?>
                        <span class="badge badge-muted"><?= e(strtoupper((string) $session['session_status'])) ?></span>
                        <span class="badge <?= (string) $session['payment_status'] === 'paid' ? 'badge-success' : 'badge-danger' ?>">
                            <?= (string) $session['payment_status'] === 'paid' ? 'LUNAS' : 'BELUM DIBAYAR' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </section>

            <div class="detail-layout">
                <div class="stack">
                    <section class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i data-lucide="receipt-text"></i>Informasi Sesi</h3>
                            <span class="badge badge-muted"><?= e($mode === 'open' ? 'Open Billing' : 'Paket') ?></span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Unit</span>
                                    <span class="info-value"><?= e($session['station_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Konsol</span>
                                    <span class="info-value"><?= e($session['console_type']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Customer</span>
                                    <span class="info-value"><?= e($session['customer_name']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Mode</span>
                                    <span class="info-value"><?= e($mode === 'open' ? 'Open Billing' : 'Paket') ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tarif / Jam</span>
                                    <span class="info-value text-mono"><?= e(format_rupiah($hourlyRate)) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Waktu Mulai</span>
                                    <span class="info-value text-mono"><?= e(date('d M Y H:i', $startTs)) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><?= $mode === 'open' ? 'Durasi Berjalan' : 'Target Selesai' ?></span>
                                    <span class="info-value text-mono">
                                        <?php if ($mode === 'open'): ?>
                                            <span data-detail-timer><?= e(gmdate('H:i:s', $timerSeconds)) ?></span>
                                        <?php elseif ($scheduledEndTs > 0): ?>
                                            <?= e(date('d M Y H:i', $scheduledEndTs)) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($isActive && $mode === 'package'): ?>
                                    <div class="info-item">
                                        <span class="info-label">Sisa Waktu</span>
                                        <span class="info-value text-mono" data-detail-countdown>
                                            <?= e(gmdate('H:i:s', $remainingSeconds)) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <span class="info-label">Durasi Tagihan</span>
                                    <span class="info-value" data-detail-duration><?= e($durationLabel) ?></span>
                                </div>
                            </div>

                            <?php if ((string) ($session['notes'] ?? '') !== ''): ?>
                                <div class="alert alert--spaced">
                                    <i data-lucide="notebook-pen"></i>
                                    <span><?= e((string) $session['notes']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i data-lucide="utensils-crossed"></i>Pesanan Cafe / F&amp;B</h3>
                            <span class="badge badge-muted"><?= e((string) count($items)) ?> Item</span>
                        </div>
                        <div class="card-body">
                            <?php if ($items === []): ?>
                                <p class="ps-empty-filter">Belum ada pesanan makanan atau minuman pada sesi ini.</p>
                            <?php else: ?>
                                <div class="table-wrap table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Nama Menu</th>
                                                <th scope="col">Qty</th>
                                                <th scope="col" class="text-right">Harga Satuan</th>
                                                <th scope="col" class="text-right">Subtotal</th>
                                                <th scope="col">Waktu Pesan</th>
                                                <?php if ($isActive): ?>
                                                    <th scope="col" class="cell-actions">Aksi</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <?= e((string) $item['menu_name']) ?>
                                                        <span class="badge badge-muted badge--inline"><?= e(ucfirst((string) $item['category'])) ?></span>
                                                    </td>
                                                    <td class="cell-mono"><?= e((string) $item['quantity']) ?></td>
                                                    <td class="text-right cell-mono"><?= e(format_rupiah((int) $item['unit_price'])) ?></td>
                                                    <td class="text-right cell-mono"><?= e(format_rupiah((int) $item['subtotal'])) ?></td>
                                                    <td class="cell-muted text-mono"><?= e(date('d M H:i', (int) strtotime((string) $item['ordered_at']))) ?></td>
                                                    <?php if ($isActive): ?>
                                                        <td class="cell-actions">
                                                            <form
                                                                class="fnb-delete-form"
                                                                method="post"
                                                                action="<?= e(base_url('actions/cafe_order_delete_item.php')) ?>"
                                                                data-item-name="<?= e((string) $item['menu_name']) ?>"
                                                            >
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="item_id" value="<?= e((string) $item['item_id']) ?>">
                                                                <input type="hidden" name="session_id" value="<?= e((string) $sessionId) ?>">
                                                                <button
                                                                    class="btn btn-danger btn-icon btn-icon-sm"
                                                                    type="submit"
                                                                    aria-label="Hapus <?= e((string) $item['menu_name']) ?>"
                                                                    title="Hapus item pesanan"
                                                                >
                                                                    <i data-lucide="trash-2"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActive): ?>
                            <div class="card-footer">
                                <a class="btn btn-secondary" href="<?= e(base_url('pages/cafe.php?session_id=' . $sessionId)) ?>">
                                    <i data-lucide="plus"></i>Pesan Makan / Minum
                                </a>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="detail-sidebar">
                    <section
                        class="card bill-card"
                        data-detail-billing
                        data-server-now="<?= e((string) $serverNow) ?>"
                        data-billing-mode="<?= e($mode) ?>"
                        data-hourly-rate="<?= e((string) $hourlyRate) ?>"
                        data-start-time="<?= e((string) $startTs) ?>"
                        data-scheduled-end="<?= e((string) $scheduledEndTs) ?>"
                        data-min-minutes="<?= e((string) $minMinutes) ?>"
                        data-rounding-minutes="<?= e((string) $roundingMinutes) ?>"
                        data-fnb-cost="<?= e((string) $fnbCost) ?>"
                        data-ps-cost="<?= e((string) $psCost) ?>"
                        data-total-cost="<?= e((string) $totalCost) ?>"
                        data-is-active="<?= $isActive ? '1' : '0' ?>"
                    >
                        <div class="card-header">
                            <h3 class="card-title"><i data-lucide="wallet"></i>Ringkasan Tagihan</h3>
                        </div>
                        <div class="card-body stack">
                            <div class="bill-list">
                                <div class="bill-row">
                                    <span>Biaya Rental PS</span>
                                    <span class="text-mono" data-bill-ps><?= e(format_rupiah($psCost)) ?></span>
                                </div>
                                <div class="bill-row">
                                    <span>Total Cafe / F&amp;B</span>
                                    <span class="text-mono" data-bill-fnb><?= e(format_rupiah($fnbCost)) ?></span>
                                </div>
                            </div>

                            <div class="grand-total">
                                <span class="grand-total-label">Grand Total</span>
                                <strong class="grand-total-value text-mono" data-bill-total><?= e(format_rupiah($totalCost)) ?></strong>
                            </div>

                            <?php if ($isActive): ?>
                                <div class="stack stack--tight">
                                    <?php if ($mode === 'package'): ?>
                                        <button class="btn btn-secondary btn-block" type="button" data-detail-extend>
                                            <i data-lucide="clock"></i>Tambah Waktu
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-danger btn-block" type="button" data-detail-pay>
                                        <i data-lucide="power"></i>Bayar &amp; Selesaikan Sesi
                                    </button>
                                </div>
                            <?php endif; ?>

                            <a class="btn btn-ghost btn-block" href="<?= e(base_url('pages/billing.php')) ?>">
                                <i data-lucide="arrow-left"></i>Kembali ke Billing
                            </a>
                        </div>
                    </section>

                    <div class="alert">
                        <i data-lucide="shield-check"></i>
                        <span>Nominal Open Billing diperbarui otomatis mengikuti durasi berjalan hingga sesi dibayar.</span>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</div>

<?php if ($isActive): ?>
<div class="modal" id="modalPay" role="dialog" aria-modal="true" aria-labelledby="modalPayTitle">
    <div class="modal-dialog">
        <form class="modal-form" method="post" action="<?= e(base_url('actions/billing_pay.php')) ?>" id="payForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalPayTitle">Bayar &amp; Selesaikan Sesi</h3>
                    <span class="modal-subtitle"><?= e($session['station_name']) ?> · <?= e($session['customer_name']) ?></span>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body stack">
                <input type="hidden" name="session_id" value="<?= e((string) $sessionId) ?>">
                <input type="hidden" name="context" value="detail">

                <div class="summary-card">
                    <div class="summary-card-row">
                        <span>Customer</span>
                        <strong><?= e($session['customer_name']) ?></strong>
                    </div>
                    <div class="summary-card-row">
                        <span>Mode</span>
                        <strong data-pay-mode><?= e($mode === 'open' ? 'Open Billing' : 'Paket') ?></strong>
                    </div>
                    <div class="summary-card-row">
                        <span>Durasi</span>
                        <strong data-pay-duration><?= e($durationLabel) ?></strong>
                    </div>
                </div>

                <div class="bill-list">
                    <div class="bill-row">
                        <span>Biaya Rental PS</span>
                        <span class="text-mono" data-pay-ps><?= e(format_rupiah($psCost)) ?></span>
                    </div>
                    <div class="bill-row">
                        <span>Total Cafe / F&amp;B</span>
                        <span class="text-mono" data-pay-fnb><?= e(format_rupiah($fnbCost)) ?></span>
                    </div>
                    <div class="bill-row bill-row--total">
                        <span>Grand Total</span>
                        <span class="text-mono" data-pay-total><?= e(format_rupiah($totalCost)) ?></span>
                    </div>
                </div>

                <p class="form-hint">Pastikan nominal sudah sesuai sebelum menyelesaikan sesi. Unit PS akan kembali IDLE setelah pembayaran.</p>
            </div>

            <div class="modal-footer">
                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                <button class="btn btn-danger" type="submit"><i data-lucide="power"></i>Bayar &amp; Selesaikan</button>
            </div>
        </form>
    </div>
</div>

<?php if ($mode === 'package'): ?>
<div class="modal" id="modalExtend" role="dialog" aria-modal="true" aria-labelledby="modalExtendTitle">
    <div class="modal-dialog">
        <form class="modal-form" method="post" action="<?= e(base_url('actions/billing_extend.php')) ?>" id="extendForm">
            <?= csrf_field() ?>
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalExtendTitle">Tambah Waktu</h3>
                    <span class="modal-subtitle"><?= e($session['station_name']) ?> · <?= e($session['customer_name']) ?></span>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="modal-body stack">
                <input type="hidden" name="session_id" value="<?= e((string) $sessionId) ?>">
                <input type="hidden" name="add_minutes" id="extendMinutes" value="60">
                <input type="hidden" name="context" value="detail">

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
                        <span class="text-mono"><?= e(format_rupiah($hourlyRate)) ?></span>
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
<?php endif; ?>
<?php endif; ?>
<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
