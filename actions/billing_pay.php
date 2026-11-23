<?php
/**
 * actions/billing_pay.php
 * Menyelesaikan sesi billing: hitung ulang total, tandai lunas, kosongkan unit PS.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Formulir pembayaran harus dikirim melalui tombol Bayar.');
    redirect('pages/billing.php');
}

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$context = (string) ($_POST['context'] ?? 'billing');

$redirectPath = $context === 'detail' && $sessionId
    ? 'pages/session_detail.php?id=' . (int) $sessionId
    : 'pages/billing.php';

verify_csrf_token($redirectPath);

if ($sessionId === false || $sessionId === null) {
    set_flash('error', 'Sesi Tidak Valid', 'Sesi billing yang dipilih tidak dikenali.');
    redirect('pages/billing.php');
}

$minMinutes = (int) get_setting($conn, 'open_billing_min_minutes', '30');
$minMinutes = $minMinutes > 0 ? $minMinutes : 30;
$roundingMinutes = (int) get_setting($conn, 'open_billing_rounding_minutes', '15');
$roundingMinutes = $roundingMinutes > 0 ? $roundingMinutes : 15;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
    }

    $sessionSql = "SELECT id, station_id, customer_name, billing_mode, hourly_rate_snapshot,
                          duration_minutes, ps_cost, session_status,
                          TIMESTAMPDIFF(SECOND, start_time, NOW()) AS elapsed_seconds
                   FROM billing_sessions
                   WHERE id = ?
                   FOR UPDATE";
    $sessionStmt = mysqli_prepare($conn, $sessionSql);
    if ($sessionStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query sesi: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($sessionStmt, 'i', $sessionId);
    mysqli_stmt_execute($sessionStmt);
    $sessionResult = mysqli_stmt_get_result($sessionStmt);
    $session = $sessionResult !== false ? mysqli_fetch_assoc($sessionResult) : null;
    if ($sessionResult !== false) {
        mysqli_free_result($sessionResult);
    }
    mysqli_stmt_close($sessionStmt);

    if ($session === null) {
        throw new DomainException('Sesi billing tidak ditemukan.');
    }

    if ((string) $session['session_status'] !== 'active') {
        throw new DomainException('Sesi ini sudah diselesaikan sebelumnya.');
    }

    $stationId = (int) $session['station_id'];
    $mode = (string) $session['billing_mode'];
    $snapshotRate = (int) $session['hourly_rate_snapshot'];

    // Sinkronkan total F&B aktual dari pesanan pada sesi ini.
    $fnbSql = "SELECT COALESCE(SUM(total_amount), 0) AS fnb_total, COUNT(*) AS order_count
               FROM fnb_orders
               WHERE session_id = ?";
    $fnbStmt = mysqli_prepare($conn, $fnbSql);
    if ($fnbStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query F&B: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($fnbStmt, 'i', $sessionId);
    mysqli_stmt_execute($fnbStmt);
    $fnbResult = mysqli_stmt_get_result($fnbStmt);
    $fnbRow = $fnbResult !== false ? mysqli_fetch_assoc($fnbResult) : null;
    if ($fnbResult !== false) {
        mysqli_free_result($fnbResult);
    }
    mysqli_stmt_close($fnbStmt);

    $fnbCost = (int) ($fnbRow['fnb_total'] ?? 0);

    if ($mode === 'open') {
        $elapsedSeconds = (int) ($session['elapsed_seconds'] ?? 0);
        $estimate = calculate_open_billing_cost($elapsedSeconds, $snapshotRate, $minMinutes, $roundingMinutes);
        $psCost = (int) $estimate['cost'];
        $finalDuration = (int) $estimate['billable_minutes'];
    } else {
        $psCost = (int) $session['ps_cost'];
        $finalDuration = (int) ($session['duration_minutes'] ?? 0);
    }

    $totalCost = $psCost + $fnbCost;

    $updateSessionSql = "UPDATE billing_sessions
                         SET actual_end_time = NOW(),
                             duration_minutes = ?,
                             ps_cost = ?,
                             fnb_cost = ?,
                             total_cost = ?,
                             payment_status = 'paid',
                             session_status = 'completed',
                             updated_at = NOW()
                         WHERE id = ? AND session_status = 'active'";
    $updateSessionStmt = mysqli_prepare($conn, $updateSessionSql);
    if ($updateSessionStmt === false) {
        throw new RuntimeException('Gagal menyiapkan update sesi: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateSessionStmt, 'iiiii', $finalDuration, $psCost, $fnbCost, $totalCost, $sessionId);
    if (!mysqli_stmt_execute($updateSessionStmt)) {
        throw new RuntimeException('Gagal menyelesaikan sesi: ' . mysqli_stmt_error($updateSessionStmt));
    }

    if (mysqli_stmt_affected_rows($updateSessionStmt) !== 1) {
        mysqli_stmt_close($updateSessionStmt);
        throw new DomainException('Sesi berubah saat diproses. Silakan muat ulang halaman.');
    }
    mysqli_stmt_close($updateSessionStmt);

    $updateOrderSql = "UPDATE fnb_orders SET payment_status = 'paid' WHERE session_id = ? AND payment_status = 'unpaid'";
    $updateOrderStmt = mysqli_prepare($conn, $updateOrderSql);
    if ($updateOrderStmt === false) {
        throw new RuntimeException('Gagal menyiapkan update pesanan: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateOrderStmt, 'i', $sessionId);
    if (!mysqli_stmt_execute($updateOrderStmt)) {
        throw new RuntimeException('Gagal memperbarui pesanan F&B: ' . mysqli_stmt_error($updateOrderStmt));
    }
    mysqli_stmt_close($updateOrderStmt);

    // Unit kembali idle hanya bila tidak ada sesi aktif lain (defensif).
    $updateStationSql = "UPDATE stations s
                         SET s.status = 'idle', s.updated_at = NOW()
                         WHERE s.id = ?
                           AND NOT EXISTS (
                               SELECT 1 FROM billing_sessions b
                               WHERE b.station_id = s.id AND b.session_status = 'active'
                           )";
    $updateStationStmt = mysqli_prepare($conn, $updateStationSql);
    if ($updateStationStmt === false) {
        throw new RuntimeException('Gagal menyiapkan update unit: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateStationStmt, 'i', $stationId);
    if (!mysqli_stmt_execute($updateStationStmt)) {
        throw new RuntimeException('Gagal mengosongkan unit: ' . mysqli_stmt_error($updateStationStmt));
    }
    mysqli_stmt_close($updateStationStmt);

    mysqli_commit($conn);

    $modeText = $mode === 'open' ? 'Open Billing' : 'Paket ' . format_duration_label($finalDuration);
    set_flash(
        'success',
        'Pembayaran Berhasil',
        'Sesi ' . (string) $session['customer_name'] . ' (' . $modeText . ') lunas dengan total '
        . format_rupiah($totalCost) . '. Unit PS kembali tersedia.'
    );
} catch (DomainException $exception) {
    mysqli_rollback($conn);
    set_flash('error', 'Tidak Bisa Menyelesaikan Sesi', $exception->getMessage());
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('[DGS] billing_pay gagal: ' . $exception->getMessage());
    set_flash('error', 'Terjadi Kesalahan', 'Pembayaran gagal diproses karena masalah sistem.');
}

redirect($redirectPath);
