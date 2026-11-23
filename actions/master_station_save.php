<?php
/**
 * actions/master_station_save.php
 * Menangani tambah (create), perbarui (update), dan hapus (delete) data unit PS (stations).
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$redirectUrl = 'pages/master.php?tab=stations';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Aksi unit PS harus melalui formulir.');
    redirect($redirectUrl);
}

verify_csrf_token($redirectUrl);

$action = trim((string) ($_POST['form_action'] ?? ''));

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $consoleType = strtoupper(trim((string) ($_POST['console_type'] ?? 'PS4')));
    $hourlyRate = (int) ($_POST['hourly_rate'] ?? 0);
    $status = strtolower(trim((string) ($_POST['status'] ?? 'idle')));

    if ($name === '') {
        set_flash('error', 'Data Tidak Lengkap', 'Nama unit PS wajib diisi.');
        redirect($redirectUrl);
    }

    if (mb_strlen($name) > 50) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama unit maksimal 50 karakter.');
        redirect($redirectUrl);
    }

    if (!in_array($consoleType, ['PS4', 'PS5'], true)) {
        set_flash('error', 'Tipe Konsol Tidak Valid', 'Tipe konsol harus PS4 atau PS5.');
        redirect($redirectUrl);
    }

    if ($hourlyRate < 1000) {
        set_flash('error', 'Tarif Tidak Valid', 'Tarif per jam minimal Rp 1.000.');
        redirect($redirectUrl);
    }

    if (!in_array($status, ['idle', 'maintenance'], true)) {
        $status = 'idle';
    }

    // Cek duplikasi nama
    $checkSql = 'SELECT id FROM stations WHERE name = ? LIMIT 1';
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if ($checkStmt !== false) {
        mysqli_stmt_bind_param($checkStmt, 's', $name);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        $exists = $checkRes !== false && mysqli_num_rows($checkRes) > 0;
        if ($checkRes !== false) {
            mysqli_free_result($checkRes);
        }
        mysqli_stmt_close($checkStmt);

        if ($exists) {
            set_flash('error', 'Nama Sudah Digunakan', 'Nama unit "' . $name . '" sudah terdaftar.');
            redirect($redirectUrl);
        }
    }

    $insertSql = 'INSERT INTO stations (name, console_type, hourly_rate, status) VALUES (?, ?, ?, ?)';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if ($insertStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan penyimpanan unit: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($insertStmt, 'ssis', $name, $consoleType, $hourlyRate, $status);
    $success = mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    if ($success) {
        set_flash('success', 'Unit Berhasil Ditambahkan', 'Unit ' . $name . ' (' . $consoleType . ') berhasil disimpan.');
    } else {
        set_flash('error', 'Gagal Menyimpan', 'Terjadi kesalahan saat menambahkan unit PS.');
    }

    redirect($redirectUrl);
}

if ($action === 'update') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string) ($_POST['name'] ?? ''));
    $consoleType = strtoupper(trim((string) ($_POST['console_type'] ?? 'PS4')));
    $hourlyRate = (int) ($_POST['hourly_rate'] ?? 0);
    $requestedStatus = strtolower(trim((string) ($_POST['status'] ?? 'idle')));

    if ($id === false || $id === null || $name === '') {
        set_flash('error', 'Data Tidak Lengkap', 'ID dan nama unit PS wajib diisi.');
        redirect($redirectUrl);
    }

    if (mb_strlen($name) > 50) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama unit maksimal 50 karakter.');
        redirect($redirectUrl);
    }

    if (!in_array($consoleType, ['PS4', 'PS5'], true)) {
        set_flash('error', 'Tipe Konsol Tidak Valid', 'Tipe konsol harus PS4 atau PS5.');
        redirect($redirectUrl);
    }

    if ($hourlyRate < 1000) {
        set_flash('error', 'Tarif Tidak Valid', 'Tarif per jam minimal Rp 1.000.');
        redirect($redirectUrl);
    }

    // Ambil data unit saat ini
    $fetchSql = 'SELECT id, status FROM stations WHERE id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    if ($fetchStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal memuat data unit.');
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($fetchStmt, 'i', $id);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $station = $fetchRes !== false ? mysqli_fetch_assoc($fetchRes) : null;
    if ($fetchRes !== false) {
        mysqli_free_result($fetchRes);
    }
    mysqli_stmt_close($fetchStmt);

    if ($station === null) {
        set_flash('error', 'Unit Tidak Ditemukan', 'Data unit PS tidak ditemukan.');
        redirect($redirectUrl);
    }

    // Cek duplikasi nama pada unit lain
    $checkSql = 'SELECT id FROM stations WHERE name = ? AND id != ? LIMIT 1';
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if ($checkStmt !== false) {
        mysqli_stmt_bind_param($checkStmt, 'si', $name, $id);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        $exists = $checkRes !== false && mysqli_num_rows($checkRes) > 0;
        if ($checkRes !== false) {
            mysqli_free_result($checkRes);
        }
        mysqli_stmt_close($checkStmt);

        if ($exists) {
            set_flash('error', 'Nama Sudah Digunakan', 'Nama unit "' . $name . '" sudah digunakan unit lain.');
            redirect($redirectUrl);
        }
    }

    // Jika unit sedang PLAYING, status tidak boleh diubah
    $finalStatus = (string) $station['status'];
    if ($finalStatus !== 'playing') {
        $finalStatus = in_array($requestedStatus, ['idle', 'maintenance'], true) ? $requestedStatus : 'idle';
    }

    $updateSql = 'UPDATE stations SET name = ?, console_type = ?, hourly_rate = ?, status = ? WHERE id = ?';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan pembaruan unit: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($updateStmt, 'ssisi', $name, $consoleType, $hourlyRate, $finalStatus, $id);
    $success = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    if ($success) {
        set_flash('success', 'Unit Berhasil Diperbarui', 'Perubahan pada unit ' . $name . ' berhasil disimpan.');
    } else {
        set_flash('error', 'Gagal Memperbarui', 'Terjadi kesalahan saat menyimpan perubahan unit PS.');
    }

    redirect($redirectUrl);
}

if ($action === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false || $id === null) {
        set_flash('error', 'Data Tidak Valid', 'ID unit PS tidak valid.');
        redirect($redirectUrl);
    }

    // Cek status unit saat ini
    $fetchSql = 'SELECT id, name, status FROM stations WHERE id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    if ($fetchStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal memuat data unit.');
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($fetchStmt, 'i', $id);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $station = $fetchRes !== false ? mysqli_fetch_assoc($fetchRes) : null;
    if ($fetchRes !== false) {
        mysqli_free_result($fetchRes);
    }
    mysqli_stmt_close($fetchStmt);

    if ($station === null) {
        set_flash('error', 'Unit Tidak Ditemukan', 'Data unit PS tidak ditemukan.');
        redirect($redirectUrl);
    }

    // Cek apakah unit sedang bermain
    if ((string) $station['status'] === 'playing') {
        set_flash(
            'warning',
            'Unit Tidak Dapat Dihapus',
            'Unit tidak dapat dihapus karena sedang aktif atau memiliki riwayat transaksi. Ubah status ke Maintenance jika ingin menonaktifkan.'
        );
        redirect($redirectUrl);
    }

    // Cek apakah unit memiliki riwayat sesi
    $sessionCountSql = 'SELECT COUNT(*) AS total FROM billing_sessions WHERE station_id = ?';
    $sessionCountStmt = mysqli_prepare($conn, $sessionCountSql);
    $hasSessions = false;
    if ($sessionCountStmt !== false) {
        mysqli_stmt_bind_param($sessionCountStmt, 'i', $id);
        mysqli_stmt_execute($sessionCountStmt);
        $scRes = mysqli_stmt_get_result($sessionCountStmt);
        $scRow = $scRes !== false ? mysqli_fetch_assoc($scRes) : null;
        if ($scRes !== false) {
            mysqli_free_result($scRes);
        }
        mysqli_stmt_close($sessionCountStmt);
        if ((int) ($scRow['total'] ?? 0) > 0) {
            $hasSessions = true;
        }
    }

    if ($hasSessions) {
        set_flash(
            'warning',
            'Unit Tidak Dapat Dihapus',
            'Unit tidak dapat dihapus karena sedang aktif atau memiliki riwayat transaksi. Ubah status ke Maintenance jika ingin menonaktifkan.'
        );
        redirect($redirectUrl);
    }

    $deleteSql = 'DELETE FROM stations WHERE id = ?';
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if ($deleteStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan penghapusan unit: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($deleteStmt, 'i', $id);
    $success = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($success) {
        set_flash('success', 'Unit Dihapus', 'Unit ' . $station['name'] . ' berhasil dihapus dari sistem.');
    } else {
        set_flash('error', 'Gagal Menghapus', 'Unit PS gagal dihapus.');
    }

    redirect($redirectUrl);
}

set_flash('error', 'Aksi Tidak Dikenal', 'Permintaan formulir tidak dikenali.');
redirect($redirectUrl);
