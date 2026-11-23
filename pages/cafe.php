<?php
/**
 * pages/cafe.php
 * POS Cafe / F&B: katalog menu, keranjang pesanan, dan log pesanan hari ini.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

/* ------------------------------------------------------------
 * Target sesi (opsional) dari halaman detail sesi
 * ------------------------------------------------------------ */
$preselectSessionId = 0;
$preselectRaw = $_GET['session_id'] ?? null;
if (is_string($preselectRaw) || is_int($preselectRaw)) {
    $preselectSessionId = (int) $preselectRaw;
}
if ($preselectSessionId < 0) {
    $preselectSessionId = 0;
}

$activeSessions = [];
$sessionListSql = "SELECT
        b.id AS session_id,
        b.customer_name,
        b.billing_mode,
        s.id AS station_id,
        s.name AS station_name,
        s.console_type
    FROM billing_sessions b
    INNER JOIN stations s ON s.id = b.station_id
    WHERE b.session_status = 'active'
    ORDER BY s.id ASC";

$sessionListResult = mysqli_query($conn, $sessionListSql);
if ($sessionListResult !== false) {
    while ($row = mysqli_fetch_assoc($sessionListResult)) {
        $activeSessions[] = $row;
    }
    mysqli_free_result($sessionListResult);
}

$hasSelectedSession = false;
foreach ($activeSessions as $activeSession) {
    if ((int) $activeSession['session_id'] === $preselectSessionId) {
        $hasSelectedSession = true;
        break;
    }
}

/* ------------------------------------------------------------
 * Katalog menu tersedia
 * ------------------------------------------------------------ */
$menus = [];
$menuListSql = "SELECT id, name, category, price, stock
    FROM fnb_menus
    WHERE is_available = 1
    ORDER BY category ASC, name ASC";

$menuListResult = mysqli_query($conn, $menuListSql);
if ($menuListResult !== false) {
    while ($row = mysqli_fetch_assoc($menuListResult)) {
        $menus[] = $row;
    }
    mysqli_free_result($menuListResult);
}

$availableMenuCount = 0;
foreach ($menus as $menu) {
    if ((int) $menu['stock'] > 0) {
        $availableMenuCount++;
    }
}

/* ------------------------------------------------------------
 * 10 pesanan F&B terbaru hari ini
 * ------------------------------------------------------------ */
$todayOrders = [];
$todayOrdersSql = "SELECT
        o.id,
        o.session_id,
        o.customer_name,
        o.total_amount,
        o.payment_status,
        o.ordered_at,
        s.name AS station_name,
        GROUP_CONCAT(CONCAT(i.quantity, 'x ', m.name) ORDER BY i.id SEPARATOR ', ') AS item_summary
    FROM fnb_orders o
    LEFT JOIN stations s ON s.id = o.station_id
    LEFT JOIN fnb_order_items i ON i.order_id = o.id
    LEFT JOIN fnb_menus m ON m.id = i.menu_id
    WHERE DATE(o.ordered_at) = CURDATE()
    GROUP BY o.id, o.session_id, o.customer_name, o.total_amount, o.payment_status, o.ordered_at, s.name
    ORDER BY o.ordered_at DESC, o.id DESC
    LIMIT 10";

$todayOrdersResult = mysqli_query($conn, $todayOrdersSql);
if ($todayOrdersResult !== false) {
    while ($row = mysqli_fetch_assoc($todayOrdersResult)) {
        $todayOrders[] = $row;
    }
    mysqli_free_result($todayOrdersResult);
}

$pageTitle = 'CAFE & F&B ORDER';
$pageSubtitle = 'POS makanan & minuman';

$initialWalkin = $activeSessions === [];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="app-shell">
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <?php require_once dirname(__DIR__) . '/includes/topbar.php'; ?>

        <main class="page-content">
            <section class="page-intro">
                <div>
                    <span class="eyebrow"><i data-lucide="coffee"></i>Point of Sale</span>
                    <h2 class="page-heading">Katalog &amp; Keranjang F&amp;B</h2>
                    <p class="page-lead">Pilih menu, atur jumlah, lalu simpan pesanan ke unit PS aktif atau sebagai transaksi walk-in.</p>
                </div>
                <div class="row">
                    <span class="badge badge-muted"><i data-lucide="utensils-crossed"></i><?= e((string) $availableMenuCount) ?> Menu Tersedia</span>
                    <span class="badge badge-success"><i data-lucide="gamepad-2"></i><?= e((string) count($activeSessions)) ?> Unit Aktif</span>
                </div>
            </section>

            <div class="cafe-layout">
                <section class="cafe-catalog">
                    <div class="cafe-toolbar">
                        <div class="search-box">
                            <span class="search-box-icon"><i data-lucide="search"></i></span>
                            <input
                                class="input search-input"
                                type="search"
                                id="cafeSearch"
                                placeholder="Cari menu makanan/minuman..."
                                autocomplete="off"
                                aria-label="Cari menu makanan atau minuman"
                            >
                        </div>

                        <div class="filter-pills" role="group" aria-label="Filter kategori menu">
                            <button class="filter-pill is-active" type="button" data-cafe-filter="all" aria-pressed="true">Semua</button>
                            <button class="filter-pill" type="button" data-cafe-filter="makanan" aria-pressed="false">Makanan</button>
                            <button class="filter-pill" type="button" data-cafe-filter="snack" aria-pressed="false">Snack</button>
                            <button class="filter-pill" type="button" data-cafe-filter="minuman" aria-pressed="false">Minuman</button>
                        </div>
                    </div>

                    <?php if ($menus === []): ?>
                        <p class="ps-empty-filter">Belum ada menu F&amp;B yang tersedia. Tambahkan data master menu terlebih dahulu.</p>
                    <?php else: ?>
                        <div class="menu-grid" id="menuGrid">
                            <?php foreach ($menus as $menu): ?>
                                <?php
                                $menuStock = (int) $menu['stock'];
                                $menuCategory = (string) $menu['category'];
                                $stockClass = $menuStock <= 0 ? 'stock-tag--empty' : ($menuStock <= 5 ? 'stock-tag--low' : 'stock-tag--ok');
                                ?>
                                <article
                                    class="menu-card"
                                    data-menu-id="<?= e((string) $menu['id']) ?>"
                                    data-menu-name="<?= e((string) $menu['name']) ?>"
                                    data-menu-category="<?= e($menuCategory) ?>"
                                    data-menu-price="<?= e((string) $menu['price']) ?>"
                                    data-menu-stock="<?= e((string) $menuStock) ?>"
                                    data-search="<?= e(mb_strtolower((string) $menu['name'] . ' ' . $menuCategory)) ?>"
                                >
                                    <div class="menu-card-head">
                                        <h3 class="menu-card-name"><?= e((string) $menu['name']) ?></h3>
                                        <span class="badge badge-muted badge--inline"><?= e(ucfirst($menuCategory)) ?></span>
                                    </div>

                                    <div class="menu-card-meta">
                                        <span class="menu-card-price text-mono"><?= e(format_rupiah((int) $menu['price'])) ?></span>
                                        <span class="stock-tag <?= e($stockClass) ?>">
                                            <?= $menuStock <= 0 ? 'Habis' : 'Stok: ' . e((string) $menuStock) ?>
                                        </span>
                                    </div>

                                    <button
                                        class="btn btn-secondary btn-sm menu-add"
                                        type="button"
                                        data-add-menu
                                        <?= $menuStock <= 0 ? 'disabled' : '' ?>
                                    >
                                        <i data-lucide="plus"></i>Tambah
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="ps-empty-filter is-hidden" id="cafeEmptyFilter">Tidak ada menu yang cocok dengan pencarian atau filter.</p>
                    <?php endif; ?>
                </section>

                <aside class="cafe-cart">
                    <form
                        class="card cart-card"
                        id="cafeOrderForm"
                        method="post"
                        action="<?= e(base_url('actions/cafe_order_create.php')) ?>"
                    >
                        <?= csrf_field() ?>
                        <div class="card-header">
                            <h3 class="card-title"><i data-lucide="shopping-cart"></i>Keranjang Pesanan</h3>
                            <span class="badge badge-muted" id="cartCount">0 Item</span>
                        </div>

                        <div class="card-body stack">
                            <div class="form-group">
                                <label class="form-label" for="targetSession">
                                    <i data-lucide="gamepad-2"></i>Target Pesanan
                                </label>
                                <select class="select" name="target_session_id" id="targetSession">
                                    <?php if ($activeSessions !== []): ?>
                                        <optgroup label="Unit PS Sedang Aktif">
                                            <?php foreach ($activeSessions as $activeSession): ?>
                                                <?php $isSelected = $hasSelectedSession && (int) $activeSession['session_id'] === $preselectSessionId; ?>
                                                <option value="<?= e((string) $activeSession['session_id']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                                    <?= e((string) $activeSession['station_name']) ?> (<?= e((string) $activeSession['console_type']) ?>) &mdash; <?= e((string) $activeSession['customer_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <option value="walkin" <?= $activeSessions === [] ? 'selected' : '' ?>>Walk-in / Tanpa PS (Langsung Lunas)</option>
                                </select>
                            </div>

                            <div class="form-group <?= $initialWalkin ? '' : 'is-hidden' ?>" id="walkinNameGroup">
                                <label class="form-label" for="walkinName">
                                    <i data-lucide="user"></i>Nama Pembeli
                                </label>
                                <input
                                    class="input"
                                    type="text"
                                    name="customer_name"
                                    id="walkinName"
                                    placeholder="Nama pembeli (opsional)"
                                    maxlength="100"
                                    autocomplete="off"
                                    <?= $initialWalkin ? '' : 'disabled' ?>
                                >
                            </div>

                            <div class="cart-note <?= $initialWalkin ? 'is-hidden' : '' ?>" id="psTargetInfo">
                                <i data-lucide="info"></i>
                                <span>Tagihan otomatis digabung ke billing unit PS dan dibayar saat sesi selesai.</span>
                            </div>

                            <div class="cart-empty" id="cartEmpty">
                                <i data-lucide="shopping-basket"></i>
                                <span>Keranjang masih kosong. Pilih menu dari katalog.</span>
                            </div>

                            <div class="cart-list" id="cartItems"></div>
                            <div id="cartHiddenInputs"></div>
                        </div>

                        <div class="card-footer cart-footer">
                            <div class="cart-total-row">
                                <span class="cart-total-label">Total Pesanan</span>
                                <strong class="cart-total-value text-mono" id="cartTotal">Rp 0</strong>
                            </div>

                            <div class="cart-actions">
                                <button class="btn btn-ghost" type="button" id="cartReset" disabled>Reset</button>
                                <button class="btn btn-primary" type="submit" id="cartSubmit" disabled>
                                    <i data-lucide="save"></i>Simpan Pesanan
                                </button>
                            </div>

                            <p class="form-hint" id="cartHint">Pilih minimal satu menu untuk mengaktifkan tombol simpan.</p>
                        </div>
                    </form>
                </aside>
            </div>

            <section class="card">
                <div class="card-header">
                    <h3 class="card-title"><i data-lucide="receipt-text"></i>Pesanan Cafe Hari Ini</h3>
                    <span class="badge badge-muted"><?= e((string) count($todayOrders)) ?> Terbaru</span>
                </div>
                <div class="card-body">
                    <?php if ($todayOrders === []): ?>
                        <p class="ps-empty-filter">Belum ada pesanan F&amp;B hari ini.</p>
                    <?php else: ?>
                        <div class="table-wrap table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">Waktu</th>
                                        <th scope="col">Target</th>
                                        <th scope="col">Daftar Item</th>
                                        <th scope="col" class="text-right">Total</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todayOrders as $order): ?>
                                        <?php
                                        $orderSessionId = $order['session_id'] !== null ? (int) $order['session_id'] : 0;
                                        $orderTotal = (int) $order['total_amount'];
                                        $isPaid = (string) $order['payment_status'] === 'paid';
                                        $itemSummary = (string) ($order['item_summary'] ?? '');
                                        ?>
                                        <tr>
                                            <td class="cell-muted text-mono">
                                                <?= e(date('H:i', (int) strtotime((string) $order['ordered_at']))) ?>
                                            </td>
                                            <td>
                                                <?php if ($orderSessionId > 0): ?>
                                                    <?= e((string) ($order['station_name'] ?? '-')) ?> &mdash; <?= e((string) $order['customer_name']) ?>
                                                <?php else: ?>
                                                    <span class="badge badge-info badge--inline">Walk-in</span>
                                                    <?= e((string) $order['customer_name']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cell-muted"><?= e($itemSummary !== '' ? $itemSummary : '-') ?></td>
                                            <td class="text-right cell-mono"><?= e(format_rupiah($orderTotal)) ?></td>
                                            <td>
                                                <span class="badge <?= $isPaid ? 'badge-success' : 'badge-warning' ?>">
                                                    <?= $isPaid ? 'LUNAS' : 'BELUM DIBAYAR' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a class="btn btn-ghost" href="<?= e(base_url('pages/billing.php')) ?>">
                        <i data-lucide="arrow-left"></i>Kembali ke Billing
                    </a>
                </div>
            </section>
        </main>
    </div>
</div>
<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
