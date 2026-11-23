<?php
/**
 * actions/billing_start.php
 * Memulai sesi billing baru (Paket / Open Billing) untuk unit PS yang idle.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Formulir mulai main harus dikirim melalui tombol Mulai Main.');
    redirect('pages/billing.php');
}

verify_csrf_token('pages/billing.php');

$stationId = filter_input(INPUT_POST, 'station_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$customerName = trim((string) ($_POST['customer_name'] ?? ''));
$billingMode = (string) ($_POST['billing_mode'] ?? '');
$durationRaw = (string) ($_POST['duration_minutes'] ?? '0');
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($stationId === false || $stationId === null) {
    set_flash('error', 'Unit Tidak Valid', 'Unit PlayStation yang dipilih tidak dikenali.');
    redirect('pages/billing.php');
}

if ($customerName === '') {
    set_flash('error', 'Nama Customer Kosong', 'Nama customer wajib diisi sebelum memulai sesi.');
    redirect('pages/billing.php');
}

if (mb_strlen($customerName) > 100) {
    $customerName = mb_substr($customerName, 0, 100);
}

if (!in_array($billingMode, ['package', 'open'], true)) {
    set_flash('error', 'Mode Billing Tidak Valid', 'Pilih mode Paket atau Open Billing.');
    redirect('pages/billing.php');
}

if (mb_strlen($notes) > 255) {
    $notes = mb_substr($notes, 0, 255);
}

$durationMinutes = (int) $durationRaw;
if ($billingMode === 'package' && $durationMinutes < 30) {
    set_flash('error', 'Durasi Terlalu Singkat', 'Durasi paket minimal 30 menit.');
    redirect('pages/billing.php');
}
if ($billingMode === 'package' && $durationMinutes > 24 * 60) {
    set_flash('error', 'Durasi Terlalu Panjang', 'Durasi paket maksimal 24 jam per sesi.');
    redirect('pages/billing.php');
}

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
    }

    $stationSql = "SELECT id, name, status, hourly_rate FROM stations WHERE id = ? FOR UPDATE";
    $stationStmt = mysqli_prepare($conn, $stationSql);
    if ($stationStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query unit: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stationStmt, 'i', $stationId);
    mysqli_stmt_execute($stationStmt);
    $stationResult = mysqli_stmt_get_result($stationStmt);
    $station = $stationResult !== false ? mysqli_fetch_assoc($stationResult) : null;
    if ($stationResult !== false) {
        mysqli_free_result($stationResult);
    }
    mysqli_stmt_close($stationStmt);

    if ($station === null) {
        throw new DomainException('Unit PlayStation tidak ditemukan.');
    }

    if ((string) $station['status'] !== 'idle') {
        throw new DomainException('Unit ' . (string) $station['name'] . ' sedang tidak tersedia (status: ' . (string) $station['status'] . ').');
    }

    // Cegah dua sesi aktif pada unit yang sama (mis. status unit tidak sinkron).
    $activeSql = "SELECT id FROM billing_sessions WHERE station_id = ? AND session_status = 'active' LIMIT 1";
    $activeStmt = mysqli_prepare($conn, $activeSql);
    if ($activeStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query sesi aktif: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($activeStmt, 'i', $stationId);
    mysqli_stmt_execute($activeStmt);
    $activeResult = mysqli_stmt_get_result($activeStmt);
    $activeSession = $activeResult !== false ? mysqli_fetch_assoc($activeResult) : null;
    if ($activeResult !== false) {
        mysqli_free_result($activeResult);
    }
    mysqli_stmt_close($activeStmt);

    if ($activeSession !== null) {
        throw new DomainException('Unit ' . (string) $station['name'] . ' masih memiliki sesi aktif yang belum dibayar.');
    }

    $hourlyRate = (int) $station['hourly_rate'];
    $startTime = date('Y-m-d H:i:s');

    if ($billingMode === 'package') {
        $scheduledEnd = date('Y-m-d H:i:s', time() + ($durationMinutes * 60));
        $psCost = (int) round(($durationMinutes / 60) * $hourlyRate);
        $totalCost = $psCost;
        $durationForDb = $durationMinutes;
    } else {
        // Mode open: durasi & target selesai kosong, biaya dihitung saat pembayaran.
        $scheduledEnd = null;
        $psCost = 0;
        $totalCost = 0;
        $durationForDb = null;
    }

    $insertSql = "INSERT INTO billing_sessions
            (station_id, customer_name, billing_mode, hourly_rate_snapshot, duration_minutes,
             start_time, scheduled_end_time, actual_end_time, ps_cost, fnb_cost, total_cost,
             payment_status, session_status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, 0, ?, 'unpaid', 'active', ?)";

    $insertStmt = mysqli_prepare($conn, $insertSql);
    if ($insertStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query sesi: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'issiissiis',
        $stationId,
        $customerName,
        $billingMode,
        $hourlyRate,
        $durationForDb,
        $startTime,
        $scheduledEnd,
        $psCost,
        $totalCost,
        $notes
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        throw new RuntimeException('Gagal menyimpan sesi billing: ' . mysqli_stmt_error($insertStmt));
    }
    mysqli_stmt_close($insertStmt);

    $updateSql = "UPDATE stations SET status = 'playing', updated_at = NOW() WHERE id = ? AND status = 'idle'";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query unit: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateStmt, 'i', $stationId);
    if (!mysqli_stmt_execute($updateStmt)) {
        throw new RuntimeException('Gagal memperbarui status unit: ' . mysqli_stmt_error($updateStmt));
    }

    if (mysqli_stmt_affected_rows($updateStmt) !== 1) {
        mysqli_stmt_close($updateStmt);
        throw new DomainException('Unit ' . (string) $station['name'] . ' sudah dipakai sesi lain.');
    }
    mysqli_stmt_close($updateStmt);

    mysqli_commit($conn);

    $modeText = $billingMode === 'package'
        ? 'Paket ' . format_duration_label($durationMinutes)
        : 'Open Billing';
    set_flash(
        'success',
        'Sesi Dimulai',
        (string) $station['name'] . ' sekarang dipakai oleh ' . $customerName . ' (' . $modeText . ').'
    );
} catch (DomainException $exception) {
    mysqli_rollback($conn);
    set_flash('error', 'Tidak Bisa Memulai Sesi', $exception->getMessage());
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('[DGS] billing_start gagal: ' . $exception->getMessage());
    set_flash('error', 'Terjadi Kesalahan', 'Sesi gagal dimulai karena masalah sistem. Silakan coba lagi.');
}

redirect('pages/billing.php');
