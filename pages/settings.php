<?php
/**
 * pages/settings.php
 * Pengaturan aplikasi: konfigurasi rental & billing, serta profil akun administrator.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$pageTitle = 'SETTINGS';
$pageSubtitle = 'Konfigurasi rental & keamanan akun';

/* ------------------------------------------------------------
 * 1. Ambil Pengaturan dari Database
 * ------------------------------------------------------------ */
$rentalName = get_setting($conn, 'rental_name', "Davin's Gaming Space");
$minMinutes = (int) get_setting($conn, 'open_billing_min_minutes', '30');
$roundingMinutes = (int) get_setting($conn, 'open_billing_rounding_minutes', '15');

/* ------------------------------------------------------------
 * 2. Ambil Profil Admin yang Sedang Login
 * ------------------------------------------------------------ */
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$admin = null;

if ($adminId > 0) {
    $adminSql = 'SELECT id, username, full_name, created_at FROM admins WHERE id = ? LIMIT 1';
    $adminStmt = mysqli_prepare($conn, $adminSql);
    if ($adminStmt !== false) {
        mysqli_stmt_bind_param($adminStmt, 'i', $adminId);
        mysqli_stmt_execute($adminStmt);
        $res = mysqli_stmt_get_result($adminStmt);
        if ($res !== false) {
            $admin = mysqli_fetch_assoc($res) ?: null;
            mysqli_free_result($res);
        }
        mysqli_stmt_close($adminStmt);
    }
}

$adminUsername = (string) ($admin['username'] ?? ($_SESSION['admin_username'] ?? 'admin'));
$adminFullName = (string) ($admin['full_name'] ?? ($_SESSION['admin_name'] ?? 'Administrator'));

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <?php require_once dirname(__DIR__) . '/includes/topbar.php'; ?>

        <main class="page-content">
            <section class="page-intro">
                <div>
                    <span class="eyebrow"><i data-lucide="settings"></i>Pengaturan Sistem</span>
                    <h2 class="page-heading">Konfigurasi &amp; Keamanan</h2>
                    <p class="page-lead">Sesuaikan nama usaha rental, kebijakan tarif Open Billing, serta kelola kredensial akun administrator.</p>
                </div>
                <div class="row">
                    <span class="badge badge-success"><i data-lucide="shield-check"></i>Sistem Terproteksi</span>
                </div>
            </section>

            <div class="settings-layout">
                <!-- ========================================================
                     KARTU 1: PENGATURAN RENTAL & BILLING
                     ======================================================== -->
                <section class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i data-lucide="sliders"></i>Pengaturan Rental &amp; Billing</h3>
                            <span class="card-subtitle">Nama usaha dan parameter perhitungan tarif waktu fleksibel (Open Billing)</span>
                        </div>
                    </div>
                    <form method="post" action="<?= e(base_url('actions/settings_save.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="general">

                        <div class="card-body stack">
                            <div class="form-group">
                                <label class="form-label" for="rentalName">
                                    <i data-lucide="store"></i>Nama Rental PlayStation
                                </label>
                                <input
                                    class="input"
                                    type="text"
                                    id="rentalName"
                                    name="rental_name"
                                    value="<?= e($rentalName) ?>"
                                    maxlength="100"
                                    required
                                >
                                <span class="form-hint">Nama ini ditampilkan pada judul browser, sidebar, dan header cetak laporan.</span>
                            </div>

                            <div class="form-row form-row--2">
                                <div class="form-group">
                                    <label class="form-label" for="minMinutes">
                                        <i data-lucide="clock"></i>Durasi Minimal Open Billing (Menit)
                                    </label>
                                    <input
                                        class="input text-mono"
                                        type="number"
                                        id="minMinutes"
                                        name="open_billing_min_minutes"
                                        value="<?= e((string) $minMinutes) ?>"
                                        min="5"
                                        max="120"
                                        step="5"
                                        required
                                    >
                                    <span class="form-hint">Batas durasi minimal tagihan (default 30 menit). Main di bawah ini tetap dikenakan tarif minimal.</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="roundingMinutes">
                                        <i data-lucide="timer"></i>Kelipatan Pembulatan (Menit)
                                    </label>
                                    <input
                                        class="input text-mono"
                                        type="number"
                                        id="roundingMinutes"
                                        name="open_billing_rounding_minutes"
                                        value="<?= e((string) $roundingMinutes) ?>"
                                        min="1"
                                        max="60"
                                        step="1"
                                        required
                                    >
                                    <span class="form-hint">Waktu setelah minimal dibulatkan ke atas per kelipatan ini (default 15 menit).</span>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i data-lucide="info"></i>
                                <div>
                                    <strong>Formula Open Billing:</strong>
                                    Contoh jika bermain 34 menit dengan minimal 30 menit &amp; pembulatan 15 menit: durasi tagihan dibulatkan ke atas menjadi 45 menit (<code>ceil(34 / 15) * 15</code>).
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <button class="btn btn-primary" type="submit">
                                <i data-lucide="save"></i>Simpan Konfigurasi Rental
                            </button>
                        </div>
                    </form>
                </section>

                <!-- ========================================================
                     KARTU 2: PROFIL & KEAMANAN ADMIN
                     ======================================================== -->
                <section class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i data-lucide="user-cog"></i>Profil &amp; Keamanan Admin</h3>
                            <span class="card-subtitle">Ubah nama tampilan administrator atau perbarui password akun</span>
                        </div>
                    </div>
                    <form method="post" action="<?= e(base_url('actions/settings_save.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="security">

                        <div class="card-body stack">
                            <div class="form-row form-row--2">
                                <div class="form-group">
                                    <label class="form-label" for="adminUsername">
                                        <i data-lucide="at-sign"></i>Username
                                    </label>
                                    <input
                                        class="input text-mono"
                                        type="text"
                                        id="adminUsername"
                                        value="<?= e($adminUsername) ?>"
                                        disabled
                                        readonly
                                    >
                                    <span class="form-hint">Username login bersifat permanen.</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="fullName">
                                        <i data-lucide="user"></i>Nama Lengkap
                                    </label>
                                    <input
                                        class="input"
                                        type="text"
                                        id="fullName"
                                        name="full_name"
                                        value="<?= e($adminFullName) ?>"
                                        maxlength="100"
                                        required
                                    >
                                    <span class="form-hint">Nama yang tampil pada profil topbar.</span>
                                </div>
                            </div>

                            <div class="divider-line">
                                <span>Perbarui Password (Opsional)</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="currentPassword">
                                    <i data-lucide="key-round"></i>Password Saat Ini
                                </label>
                                <input
                                    class="input"
                                    type="password"
                                    id="currentPassword"
                                    name="current_password"
                                    placeholder="Masukkan password saat ini jika ingin mengubah password"
                                    autocomplete="current-password"
                                >
                            </div>

                            <div class="form-row form-row--2">
                                <div class="form-group">
                                    <label class="form-label" for="newPassword">
                                        <i data-lucide="lock"></i>Password Baru
                                    </label>
                                    <input
                                        class="input"
                                        type="password"
                                        id="newPassword"
                                        name="new_password"
                                        placeholder="Minimal 6 karakter"
                                        minlength="6"
                                        autocomplete="new-password"
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="confirmPassword">
                                        <i data-lucide="check-check"></i>Konfirmasi Password Baru
                                    </label>
                                    <input
                                        class="input"
                                        type="password"
                                        id="confirmPassword"
                                        name="confirm_password"
                                        placeholder="Ulangi password baru"
                                        minlength="6"
                                        autocomplete="new-password"
                                    >
                                </div>
                            </div>

                            <span class="form-hint">Biarkan kolom password kosong jika hanya ingin memperbarui nama lengkap admin.</span>
                        </div>

                        <div class="card-footer">
                            <button class="btn btn-secondary" type="submit">
                                <i data-lucide="shield-alert"></i>Perbarui Akun Admin
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
</div>
<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
