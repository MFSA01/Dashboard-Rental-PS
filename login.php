<?php
/**
 * login.php — Halaman login admin Davin's Gaming Space.
 */

require_once __DIR__ . '/config/helpers.php';

if (is_admin_logged_in()) {
    redirect('pages/billing.php');
}

$pageTitle = 'Login Admin';
$bodyClass = 'auth-body';

require_once __DIR__ . '/includes/header.php';
?>
<main class="auth-shell">
    <section class="auth-card" aria-labelledby="loginHeading">
        <div class="auth-brand">
            <span class="auth-brand-mark" aria-hidden="true">DGS</span>
            <span class="auth-brand-text">
                <span class="auth-brand-title">Davin's Gaming Space</span>
                <span class="auth-brand-sub">PlayStation Rental &amp; Cafe</span>
            </span>
        </div>

        <h1 class="auth-heading" id="loginHeading">Masuk ke Dashboard</h1>
        <p class="auth-subheading">Gunakan akun admin untuk mengelola billing unit PS, pesanan cafe, dan laporan harian.</p>

        <form class="auth-form" action="<?= e(base_url('actions/auth_login.php')) ?>" method="post" autocomplete="on">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="username">
                    <i data-lucide="user"></i>
                    Username
                </label>
                <div class="input-wrap">
                    <span class="input-icon"><i data-lucide="user"></i></span>
                    <input
                        class="input"
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Masukkan username admin"
                        maxlength="50"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <i data-lucide="lock"></i>
                    Password
                </label>
                <div class="input-wrap has-suffix">
                    <span class="input-icon"><i data-lucide="lock"></i></span>
                    <input
                        class="input"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        class="input-suffix-btn"
                        type="button"
                        id="togglePassword"
                        aria-label="Tampilkan password"
                        aria-pressed="false"
                    >
                        <i data-lucide="eye"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn-primary btn-lg btn-block" type="submit">
                <i data-lucide="log-in"></i>
                Masuk Dashboard
            </button>
        </form>

        <div class="auth-footer">
            <span>Admin Area</span>
            <span class="auth-secure"><i data-lucide="shield-check"></i>Sesi Terenkripsi</span>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
