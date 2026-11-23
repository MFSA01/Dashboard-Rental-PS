<?php
/**
 * pages/master.php
 * Master Data: Manajemen Unit PlayStation dan Menu Cafe F&B (CRUD via Modal).
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$pageTitle = 'MASTER DATA';
$pageSubtitle = 'Kelola unit konsol & katalog menu';

$activeTab = trim((string) ($_GET['tab'] ?? 'stations'));
if (!in_array($activeTab, ['stations', 'menus'], true)) {
    $activeTab = 'stations';
}

/* ------------------------------------------------------------
 * 1. Data Unit PlayStation (stations)
 * ------------------------------------------------------------ */
$stations = [];
$stationSql = 'SELECT id, name, console_type, hourly_rate, status, updated_at FROM stations ORDER BY id ASC';
$stationResult = mysqli_query($conn, $stationSql);
if ($stationResult !== false) {
    while ($row = mysqli_fetch_assoc($stationResult)) {
        $stations[] = $row;
    }
    mysqli_free_result($stationResult);
}

/* ------------------------------------------------------------
 * 2. Data Menu Cafe (fnb_menus)
 * ------------------------------------------------------------ */
$menus = [];
$menuSql = 'SELECT id, name, category, price, stock, is_available, updated_at FROM fnb_menus ORDER BY category ASC, name ASC';
$menuResult = mysqli_query($conn, $menuSql);
if ($menuResult !== false) {
    while ($row = mysqli_fetch_assoc($menuResult)) {
        $menus[] = $row;
    }
    mysqli_free_result($menuResult);
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
                    <span class="eyebrow"><i data-lucide="database"></i>Master Data</span>
                    <h2 class="page-heading">Konfigurasi Unit &amp; Menu</h2>
                    <p class="page-lead">Kelola ketersediaan unit PlayStation, tarif per jam, serta katalog dan stok makanan &amp; minuman cafe.</p>
                </div>
                <div class="row">
                    <span class="badge badge-muted"><i data-lucide="gamepad-2"></i><?= e((string) count($stations)) ?> Unit PS</span>
                    <span class="badge badge-muted"><i data-lucide="utensils-crossed"></i><?= e((string) count($menus)) ?> Menu F&amp;B</span>
                </div>
            </section>

            <!-- Tab Navigasi -->
            <nav class="master-tabs" aria-label="Navigasi master data">
                <a
                    class="master-tab <?= $activeTab === 'stations' ? 'is-active' : '' ?>"
                    href="<?= e(base_url('pages/master.php?tab=stations')) ?>"
                >
                    <i data-lucide="gamepad-2"></i>
                    <span>Unit PlayStation</span>
                    <span class="tab-badge"><?= e((string) count($stations)) ?></span>
                </a>
                <a
                    class="master-tab <?= $activeTab === 'menus' ? 'is-active' : '' ?>"
                    href="<?= e(base_url('pages/master.php?tab=menus')) ?>"
                >
                    <i data-lucide="coffee"></i>
                    <span>Menu Cafe / F&amp;B</span>
                    <span class="tab-badge"><?= e((string) count($menus)) ?></span>
                </a>
            </nav>

            <?php if ($activeTab === 'stations'): ?>
                <!-- ========================================================
                     TAB 1: UNIT PLAYSTATION
                     ======================================================== -->
                <section class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i data-lucide="tv"></i>Daftar Unit PlayStation</h3>
                            <span class="card-subtitle">Pengaturan konsol, tarif sewa per jam, dan status operasional</span>
                        </div>
                        <button class="btn btn-primary" type="button" id="btnAddStation">
                            <i data-lucide="plus"></i>Tambah Unit PS
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if ($stations === []): ?>
                            <p class="ps-empty-filter">Belum ada unit PlayStation yang terdaftar.</p>
                        <?php else: ?>
                            <div class="table-wrap table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 60px;">ID</th>
                                            <th scope="col">Nama Unit</th>
                                            <th scope="col">Konsol</th>
                                            <th scope="col" class="text-right">Tarif / Jam</th>
                                            <th scope="col" class="text-center">Status</th>
                                            <th scope="col" class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stations as $st): ?>
                                            <?php
                                            $stStatus = (string) $st['status'];
                                            $badgeClass = 'badge-success';
                                            $statusLabel = 'IDLE';
                                            if ($stStatus === 'playing') {
                                                $badgeClass = 'badge-warning';
                                                $statusLabel = 'PLAYING';
                                            } elseif ($stStatus === 'maintenance') {
                                                $badgeClass = 'badge-muted';
                                                $statusLabel = 'MAINTENANCE';
                                            }
                                            ?>
                                            <tr>
                                                <td class="cell-mono text-muted">#<?= e((string) $st['id']) ?></td>
                                                <td>
                                                    <strong><?= e((string) $st['name']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-muted badge--inline"><?= e((string) $st['console_type']) ?></span>
                                                </td>
                                                <td class="text-right cell-mono font-bold">
                                                    <?= e(format_rupiah((int) $st['hourly_rate'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= e($badgeClass) ?>">
                                                        <?= e($statusLabel) ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <div class="table-actions">
                                                        <button
                                                            class="btn btn-secondary btn-sm btnEditStation"
                                                            type="button"
                                                            data-id="<?= e((string) $st['id']) ?>"
                                                            data-name="<?= e((string) $st['name']) ?>"
                                                            data-console="<?= e((string) $st['console_type']) ?>"
                                                            data-rate="<?= e((string) $st['hourly_rate']) ?>"
                                                            data-status="<?= e($stStatus) ?>"
                                                        >
                                                            <i data-lucide="edit-3"></i>Edit
                                                        </button>
                                                        <form
                                                            class="inline-form formDeleteMaster"
                                                            method="post"
                                                            action="<?= e(base_url('actions/master_station_save.php')) ?>"
                                                            data-name="<?= e((string) $st['name']) ?>"
                                                            data-type="Unit PS"
                                                        >
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="form_action" value="delete">
                                                            <input type="hidden" name="id" value="<?= e((string) $st['id']) ?>">
                                                            <button
                                                                class="btn btn-danger btn-sm"
                                                                type="submit"
                                                                <?= $stStatus === 'playing' ? 'disabled title="Unit sedang bermain tidak dapat dihapus"' : '' ?>
                                                            >
                                                                <i data-lucide="trash-2"></i>Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Modal Form Unit PS (Tambah / Edit) -->
                <div class="modal" id="modalStation" role="dialog" aria-modal="true" aria-labelledby="modalStationTitle">
                    <div class="modal-dialog">
                        <form class="modal-form" method="post" action="<?= e(base_url('actions/master_station_save.php')) ?>" id="formStation">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" id="stationFormAction" value="create">
                            <input type="hidden" name="id" id="stationId" value="">

                            <div class="modal-header">
                                <div>
                                    <h3 class="modal-title" id="modalStationTitle">Tambah Unit PS</h3>
                                    <span class="modal-subtitle" id="modalStationSubtitle">Daftarkan unit konsol baru</span>
                                </div>
                                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>

                            <div class="modal-body stack">
                                <div class="form-group">
                                    <label class="form-label" for="stationName"><i data-lucide="tv"></i>Nama Unit</label>
                                    <input
                                        class="input"
                                        type="text"
                                        id="stationName"
                                        name="name"
                                        placeholder="Contoh: PS 11"
                                        maxlength="50"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="stationConsole"><i data-lucide="gamepad"></i>Tipe Konsol</label>
                                    <select class="select" id="stationConsole" name="console_type" required>
                                        <option value="PS4">PlayStation 4 (PS4)</option>
                                        <option value="PS5">PlayStation 5 (PS5)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="stationRate"><i data-lucide="banknote"></i>Tarif Sewa per Jam</label>
                                    <input
                                        class="input text-mono"
                                        type="number"
                                        id="stationRate"
                                        name="hourly_rate"
                                        placeholder="Contoh: 10000"
                                        min="1000"
                                        step="500"
                                        required
                                    >
                                    <span class="form-hint">Nominal dalam Rupiah. Berlaku untuk sesi billing baru.</span>
                                </div>

                                <div class="form-group" id="groupStationStatus">
                                    <label class="form-label" for="stationStatus"><i data-lucide="activity"></i>Status Unit</label>
                                    <select class="select" id="stationStatus" name="status">
                                        <option value="idle">IDLE (Tersedia / Siap Pakai)</option>
                                        <option value="maintenance">MAINTENANCE (Dalam Perawatan)</option>
                                    </select>
                                </div>

                                <div class="alert alert-warning is-hidden" id="alertStationPlaying">
                                    <i data-lucide="info"></i>
                                    <span>Unit ini sedang digunakan bermain (<strong>PLAYING</strong>). Status unit tidak dapat diubah paksa dari sini agar sesi billing tetap sinkron.</span>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                                <button class="btn btn-primary" type="submit" id="btnStationSubmit">
                                    <i data-lucide="save"></i>Simpan Unit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <!-- ========================================================
                     TAB 2: MENU CAFE / F&B
                     ======================================================== -->
                <section class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i data-lucide="utensils-crossed"></i>Katalog Menu Cafe</h3>
                            <span class="card-subtitle">Kelola makanan, snack, minuman, harga satuan, dan ketersediaan stok</span>
                        </div>
                        <div class="row">
                            <button class="btn btn-primary" type="button" id="btnAddMenu">
                                <i data-lucide="plus"></i>Tambah Menu
                            </button>
                        </div>
                    </div>

                    <div class="cafe-toolbar" style="border-radius: 0; border-left: 0; border-right: 0;">
                        <div class="search-box">
                            <span class="search-box-icon"><i data-lucide="search"></i></span>
                            <input
                                class="input search-input"
                                type="search"
                                id="masterMenuSearch"
                                placeholder="Cari nama menu cafe..."
                                autocomplete="off"
                            >
                        </div>

                        <div class="filter-pills" role="group" aria-label="Filter kategori menu master">
                            <button class="filter-pill is-active" type="button" data-master-cat="all">Semua</button>
                            <button class="filter-pill" type="button" data-master-cat="makanan">Makanan</button>
                            <button class="filter-pill" type="button" data-master-cat="snack">Snack</button>
                            <button class="filter-pill" type="button" data-master-cat="minuman">Minuman</button>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if ($menus === []): ?>
                            <p class="ps-empty-filter">Belum ada menu cafe yang terdaftar.</p>
                        <?php else: ?>
                            <div class="table-wrap table-responsive">
                                <table class="table" id="tableMasterMenu">
                                    <thead>
                                        <tr>
                                            <th scope="col">Nama Menu</th>
                                            <th scope="col">Kategori</th>
                                            <th scope="col" class="text-right">Harga</th>
                                            <th scope="col" class="text-center">Stok</th>
                                            <th scope="col" class="text-center">Ketersediaan</th>
                                            <th scope="col" class="text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($menus as $m): ?>
                                            <?php
                                            $mStock = (int) $m['stock'];
                                            $mCat = (string) $m['category'];
                                            $mAvail = (int) $m['is_available'] === 1;
                                            $stockTagClass = $mStock <= 0 ? 'stock-tag--empty' : ($mStock <= 5 ? 'stock-tag--low' : 'stock-tag--ok');
                                            ?>
                                            <tr class="master-menu-row" data-cat="<?= e($mCat) ?>" data-search="<?= e(mb_strtolower((string) $m['name'])) ?>">
                                                <td>
                                                    <strong><?= e((string) $m['name']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-muted badge--inline"><?= e(ucfirst($mCat)) ?></span>
                                                </td>
                                                <td class="text-right cell-mono font-bold">
                                                    <?= e(format_rupiah((int) $m['price'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="stock-tag <?= e($stockTagClass) ?>">
                                                        <?= $mStock <= 0 ? 'Habis (0)' : e((string) $mStock) . ' pcs' ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $mAvail ? 'badge-success' : 'badge-danger' ?>">
                                                        <?= $mAvail ? 'Tersedia' : 'Nonaktif' ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <div class="table-actions">
                                                        <button
                                                            class="btn btn-secondary btn-sm btnEditMenu"
                                                            type="button"
                                                            data-id="<?= e((string) $m['id']) ?>"
                                                            data-name="<?= e((string) $m['name']) ?>"
                                                            data-category="<?= e($mCat) ?>"
                                                            data-price="<?= e((string) $m['price']) ?>"
                                                            data-stock="<?= e((string) $mStock) ?>"
                                                            data-available="<?= $mAvail ? '1' : '0' ?>"
                                                        >
                                                            <i data-lucide="edit-3"></i>Edit
                                                        </button>
                                                        <form
                                                            class="inline-form formDeleteMaster"
                                                            method="post"
                                                            action="<?= e(base_url('actions/master_menu_save.php')) ?>"
                                                            data-name="<?= e((string) $m['name']) ?>"
                                                            data-type="Menu"
                                                        >
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="form_action" value="delete">
                                                            <input type="hidden" name="id" value="<?= e((string) $m['id']) ?>">
                                                            <button class="btn btn-danger btn-sm" type="submit">
                                                                <i data-lucide="trash-2"></i>Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="ps-empty-filter is-hidden" id="masterMenuEmptyFilter">Tidak ada menu yang cocok dengan pencarian atau filter kategori.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Modal Form Menu Cafe (Tambah / Edit) -->
                <div class="modal" id="modalMenu" role="dialog" aria-modal="true" aria-labelledby="modalMenuTitle">
                    <div class="modal-dialog">
                        <form class="modal-form" method="post" action="<?= e(base_url('actions/master_menu_save.php')) ?>" id="formMenu">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_action" id="menuFormAction" value="create">
                            <input type="hidden" name="id" id="menuId" value="">

                            <div class="modal-header">
                                <div>
                                    <h3 class="modal-title" id="modalMenuTitle">Tambah Menu Cafe</h3>
                                    <span class="modal-subtitle" id="modalMenuSubtitle">Daftarkan item makanan/minuman baru</span>
                                </div>
                                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>

                            <div class="modal-body stack">
                                <div class="form-group">
                                    <label class="form-label" for="menuName"><i data-lucide="utensils"></i>Nama Menu</label>
                                    <input
                                        class="input"
                                        type="text"
                                        id="menuName"
                                        name="name"
                                        placeholder="Contoh: Roti Bakar Coklat"
                                        maxlength="100"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="menuCategory"><i data-lucide="tag"></i>Kategori</label>
                                    <select class="select" id="menuCategory" name="category" required>
                                        <option value="makanan">Makanan</option>
                                        <option value="snack">Snack</option>
                                        <option value="minuman">Minuman</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="menuPrice"><i data-lucide="banknote"></i>Harga Satuan (Rp)</label>
                                    <input
                                        class="input text-mono"
                                        type="number"
                                        id="menuPrice"
                                        name="price"
                                        placeholder="Contoh: 12000"
                                        min="500"
                                        step="500"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="menuStock"><i data-lucide="package"></i>Jumlah Stok</label>
                                    <input
                                        class="input text-mono"
                                        type="number"
                                        id="menuStock"
                                        name="stock"
                                        placeholder="Contoh: 25"
                                        min="0"
                                        step="1"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="menuAvailable"><i data-lucide="check-circle"></i>Status Ketersediaan</label>
                                    <select class="select" id="menuAvailable" name="is_available">
                                        <option value="1">Tersedia (Muncul di POS Cafe)</option>
                                        <option value="0">Nonaktif (Disembunyikan dari POS)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button class="btn btn-ghost" type="button" data-modal-close>Batal</button>
                                <button class="btn btn-primary" type="submit" id="btnMenuSubmit">
                                    <i data-lucide="save"></i>Simpan Menu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php
mysqli_close($conn);

require_once dirname(__DIR__) . '/includes/footer.php';
