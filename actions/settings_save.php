<?php
/**
 * actions/settings_save.php
 * Menyimpan pengaturan umum rental (general) dan profil/keamanan admin (security).
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$redirectUrl = 'pages/settings.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Pengaturan hanya dapat disimpan melalui formulir.');
    redirect($redirectUrl);
}

verify_csrf_token($redirectUrl);

$action = trim((string) ($_POST['form_action'] ?? ''));

if ($action === 'general') {
    $rentalName = trim((string) ($_POST['rental_name'] ?? ''));
    $minMinutes = (int) ($_POST['open_billing_min_minutes'] ?? 0);
    $roundingMinutes = (int) ($_POST['open_billing_rounding_minutes'] ?? 0);

    if ($rentalName === '') {
        set_flash('error', 'Nama Rental Wajib Diisi', 'Nama rental tidak boleh kosong.');
        redirect($redirectUrl);
    }

    if (mb_strlen($rentalName) > 100) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama rental maksimal 100 karakter.');
        redirect($redirectUrl);
    }

    if ($minMinutes < 5 || $minMinutes > 120) {
        set_flash('error', 'Durasi Minimal Tidak Valid', 'Durasi minimal Open Billing harus antara 5 sampai 120 menit.');
        redirect($redirectUrl);
    }

    if ($roundingMinutes < 1 || $roundingMinutes > 60) {
        set_flash('error', 'Kelipatan Pembulatan Tidak Valid', 'Kelipatan pembulatan harus antara 1 sampai 60 menit.');
        redirect($redirectUrl);
    }

    $settingsToSave = [
        'rental_name' => $rentalName,
        'open_billing_min_minutes' => (string) $minMinutes,
        'open_billing_rounding_minutes' => (string) $roundingMinutes,
    ];

    try {
        if (!mysqli_begin_transaction($conn)) {
            throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
        }

        $upsertSql = 'INSERT INTO settings (setting_key, setting_value)
                      VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
        $stmt = mysqli_prepare($conn, $upsertSql);

        if ($stmt === false) {
            throw new RuntimeException('Gagal menyiapkan query pengaturan: ' . mysqli_error($conn));
        }

        foreach ($settingsToSave as $key => $val) {
            mysqli_stmt_bind_param($stmt, 'ss', $key, $val);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Gagal menyimpan key ' . $key . ': ' . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        set_flash('success', 'Pengaturan Disimpan', 'Konfigurasi rental dan billing berhasil diperbarui.');
    } catch (Throwable $exception) {
        mysqli_rollback($conn);
        error_log('[DGS] Gagal simpan settings: ' . $exception->getMessage());
        set_flash('error', 'Gagal Menyimpan', 'Terjadi masalah sistem saat menyimpan pengaturan.');
    }

    redirect($redirectUrl);
}

if ($action === 'security') {
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($adminId <= 0) {
        set_flash('error', 'Sesi Tidak Valid', 'Silakan masuk kembali.');
        redirect('login.php');
    }

    if ($fullName === '') {
        set_flash('error', 'Nama Admin Wajib Diisi', 'Nama lengkap administrator tidak boleh kosong.');
        redirect($redirectUrl);
    }

    if (mb_strlen($fullName) > 100) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama lengkap maksimal 100 karakter.');
        redirect($redirectUrl);
    }

    // Ambil data admin saat ini
    $fetchSql = 'SELECT id, password_hash FROM admins WHERE id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    if ($fetchStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal memuat data akun admin.');
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($fetchStmt, 'i', $adminId);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $adminRow = $fetchRes !== false ? mysqli_fetch_assoc($fetchRes) : null;
    if ($fetchRes !== false) {
        mysqli_free_result($fetchRes);
    }
    mysqli_stmt_close($fetchStmt);

    if ($adminRow === null) {
        set_flash('error', 'Akun Tidak Ditemukan', 'Data administrator tidak ditemukan.');
        redirect('login.php');
    }

    $wantsPasswordChange = $newPassword !== '';

    if ($wantsPasswordChange) {
        if ($currentPassword === '') {
            set_flash('error', 'Password Saat Ini Diperlukan', 'Masukkan password saat ini untuk memverifikasi perubahan password.');
            redirect($redirectUrl);
        }

        if (!password_verify($currentPassword, (string) $adminRow['password_hash'])) {
            set_flash('error', 'Password Saat Ini Salah', 'Verifikasi password saat ini gagal. Password tidak diubah.');
            redirect($redirectUrl);
        }

        if (mb_strlen($newPassword) < 6) {
            set_flash('error', 'Password Terlalu Pendek', 'Password baru minimal 6 karakter.');
            redirect($redirectUrl);
        }

        if ($newPassword !== $confirmPassword) {
            set_flash('error', 'Konfirmasi Password Tidak Cocok', 'Password baru dan konfirmasi password tidak sama.');
            redirect($redirectUrl);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateSql = 'UPDATE admins SET full_name = ?, password_hash = ? WHERE id = ?';
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if ($updateStmt === false) {
            set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan pembaruan akun: ' . mysqli_error($conn));
            redirect($redirectUrl);
        }

        mysqli_stmt_bind_param($updateStmt, 'ssi', $fullName, $newHash, $adminId);
        $success = mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    } else {
        $updateSql = 'UPDATE admins SET full_name = ? WHERE id = ?';
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if ($updateStmt === false) {
            set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan pembaruan nama: ' . mysqli_error($conn));
            redirect($redirectUrl);
        }

        mysqli_stmt_bind_param($updateStmt, 'si', $fullName, $adminId);
        $success = mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }

    if ($success) {
        $_SESSION['admin_name'] = $fullName;
        if ($wantsPasswordChange) {
            // Cegah session fixation setelah kredensial berubah.
            session_regenerate_id(true);
        }
        set_flash(
            'success',
            'Profil Diperbarui',
            $wantsPasswordChange
                ? 'Nama lengkap dan password akun administrator berhasil diperbarui.'
                : 'Nama lengkap administrator berhasil diperbarui.'
        );
    } else {
        set_flash('error', 'Gagal Memperbarui', 'Terjadi kesalahan saat memperbarui akun administrator.');
    }

    redirect($redirectUrl);
}

set_flash('error', 'Aksi Tidak Dikenal', 'Permintaan formulir tidak dikenali.');
redirect($redirectUrl);
