<?php
/**
 * actions/billing_extend.php
 * Menambah durasi sesi Paket (countdown) beserta biaya rental PS.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Formulir tambah waktu harus dikirim melalui tombol + Waktu.');
    redirect('pages/billing.php');
}

$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$addMinutes = (int) ($_POST['add_minutes'] ?? 0);
$context = (string) ($_POST['context'] ?? 'billing');

$redirectPath = $context === 'detail' && $sessionId
    ? 'pages/session_detail.php?id=' . (int) $sessionId
    : 'pages/billing.php';

verify_csrf_token($redirectPath);

if ($sessionId === false || $sessionId === null) {
    set_flash('error', 'Sesi Tidak Valid', 'Sesi billing yang dipilih tidak dikenali.');
    redirect('pages/billing.php');
}

if ($addMinutes < 15) {
    set_flash('error', 'Durasi Tidak Valid', 'Tambahan waktu minimal 15 menit.');
    redirect($redirectPath);
}

if ($addMinutes > 24 * 60) {
    set_flash('error', 'Durasi Terlalu Panjang', 'Tambahan waktu maksimal 24 jam per aksi.');
    redirect($redirectPath);
}

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
    }

    $sessionSql = "SELECT id, station_id, billing_mode, hourly_rate_snapshot, duration_minutes,
                          scheduled_end_time, ps_cost, fnb_cost, total_cost, session_status
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
        throw new DomainException('Sesi ini sudah selesai dan tidak dapat diperpanjang.');
    }

    if ((string) $session['billing_mode'] !== 'package') {
        throw new DomainException('Tambah waktu hanya berlaku untuk mode Paket (countdown).');
    }

    $nowTs = time();
    $currentEndTs = $session['scheduled_end_time'] !== null
        ? (int) strtotime((string) $session['scheduled_end_time'])
        : $nowTs;

    $baseTs = $currentEndTs > $nowTs ? $currentEndTs : $nowTs;
    $newEndTs = $baseTs + ($addMinutes * 60);
    $newEnd = date('Y-m-d H:i:s', $newEndTs);

    $snapshotRate = (int) $session['hourly_rate_snapshot'];
    $additionalCost = (int) round(($addMinutes / 60) * $snapshotRate);

    $currentDuration = (int) ($session['duration_minutes'] ?? 0);
    $newDuration = $currentDuration + $addMinutes;
    $newPsCost = (int) $session['ps_cost'] + $additionalCost;
    $fnbCost = (int) $session['fnb_cost'];
    $newTotal = $newPsCost + $fnbCost;

    $updateSql = "UPDATE billing_sessions
                  SET scheduled_end_time = ?, duration_minutes = ?, ps_cost = ?, total_cost = ?, updated_at = NOW()
                  WHERE id = ? AND session_status = 'active'";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt === false) {
        throw new RuntimeException('Gagal menyiapkan update sesi: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateStmt, 'siiii', $newEnd, $newDuration, $newPsCost, $newTotal, $sessionId);
    if (!mysqli_stmt_execute($updateStmt)) {
        throw new RuntimeException('Gagal memperbarui sesi: ' . mysqli_stmt_error($updateStmt));
    }

    if (mysqli_stmt_affected_rows($updateStmt) !== 1) {
        mysqli_stmt_close($updateStmt);
        throw new DomainException('Sesi berubah saat diproses. Silakan muat ulang halaman.');
    }
    mysqli_stmt_close($updateStmt);

    mysqli_commit($conn);

    $wasTimeUp = $currentEndTs <= $nowTs && (int) ($session['duration_minutes'] ?? 0) > 0;
    set_flash(
        'success',
        'Waktu Ditambahkan',
        '+' . format_duration_label($addMinutes) . ' berhasil ditambahkan'
        . ($wasTimeUp ? ' dan timer dimulai dari waktu sekarang' : '')
        . '. Tambahan biaya ' . format_rupiah($additionalCost) . '.'
    );
} catch (DomainException $exception) {
    mysqli_rollback($conn);
    set_flash('error', 'Tidak Bisa Menambah Waktu', $exception->getMessage());
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('[DGS] billing_extend gagal: ' . $exception->getMessage());
    set_flash('error', 'Terjadi Kesalahan', 'Tambahan waktu gagal diproses karena masalah sistem.');
}

redirect($redirectPath);
