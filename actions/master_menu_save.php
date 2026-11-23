<?php
/**
 * actions/master_menu_save.php
 * Menangani tambah (create), perbarui (update), dan hapus (delete) master menu F&B (fnb_menus).
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

$redirectUrl = 'pages/master.php?tab=menus';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Aksi menu cafe harus melalui formulir.');
    redirect($redirectUrl);
}

verify_csrf_token($redirectUrl);

$action = trim((string) ($_POST['form_action'] ?? ''));

if ($action === 'create') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $category = strtolower(trim((string) ($_POST['category'] ?? 'makanan')));
    $price = (int) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $isAvailable = isset($_POST['is_available']) && (string) ($_POST['is_available'] ?? '') === '1' ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Data Tidak Lengkap', 'Nama menu F&B wajib diisi.');
        redirect($redirectUrl);
    }

    if (mb_strlen($name) > 100) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama menu maksimal 100 karakter.');
        redirect($redirectUrl);
    }

    if (!in_array($category, ['makanan', 'snack', 'minuman'], true)) {
        set_flash('error', 'Kategori Tidak Valid', 'Kategori harus makanan, snack, atau minuman.');
        redirect($redirectUrl);
    }

    if ($price < 500) {
        set_flash('error', 'Harga Tidak Valid', 'Harga menu minimal Rp 500.');
        redirect($redirectUrl);
    }

    if ($stock < 0) {
        set_flash('error', 'Stok Tidak Valid', 'Stok menu tidak boleh bernilai negatif.');
        redirect($redirectUrl);
    }

    $insertSql = 'INSERT INTO fnb_menus (name, category, price, stock, is_available) VALUES (?, ?, ?, ?, ?)';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if ($insertStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan penyimpanan menu: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($insertStmt, 'ssiii', $name, $category, $price, $stock, $isAvailable);
    $success = mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    if ($success) {
        set_flash('success', 'Menu Ditambahkan', 'Menu "' . $name . '" (' . ucfirst($category) . ') berhasil disimpan.');
    } else {
        set_flash('error', 'Gagal Menyimpan', 'Terjadi kesalahan saat menambahkan menu.');
    }

    redirect($redirectUrl);
}

if ($action === 'update') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string) ($_POST['name'] ?? ''));
    $category = strtolower(trim((string) ($_POST['category'] ?? 'makanan')));
    $price = (int) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $isAvailable = isset($_POST['is_available']) && (string) ($_POST['is_available'] ?? '') === '1' ? 1 : 0;

    if ($id === false || $id === null || $name === '') {
        set_flash('error', 'Data Tidak Lengkap', 'ID dan nama menu wajib diisi.');
        redirect($redirectUrl);
    }

    if (mb_strlen($name) > 100) {
        set_flash('error', 'Nama Terlalu Panjang', 'Nama menu maksimal 100 karakter.');
        redirect($redirectUrl);
    }

    if (!in_array($category, ['makanan', 'snack', 'minuman'], true)) {
        set_flash('error', 'Kategori Tidak Valid', 'Kategori harus makanan, snack, atau minuman.');
        redirect($redirectUrl);
    }

    if ($price < 500) {
        set_flash('error', 'Harga Tidak Valid', 'Harga menu minimal Rp 500.');
        redirect($redirectUrl);
    }

    if ($stock < 0) {
        set_flash('error', 'Stok Tidak Valid', 'Stok menu tidak boleh bernilai negatif.');
        redirect($redirectUrl);
    }

    // Cek keberadaan menu
    $fetchSql = 'SELECT id FROM fnb_menus WHERE id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    if ($fetchStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal memuat data menu.');
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($fetchStmt, 'i', $id);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $menu = $fetchRes !== false ? mysqli_fetch_assoc($fetchRes) : null;
    if ($fetchRes !== false) {
        mysqli_free_result($fetchRes);
    }
    mysqli_stmt_close($fetchStmt);

    if ($menu === null) {
        set_flash('error', 'Menu Tidak Ditemukan', 'Data menu tidak ditemukan.');
        redirect($redirectUrl);
    }

    $updateSql = 'UPDATE fnb_menus SET name = ?, category = ?, price = ?, stock = ?, is_available = ? WHERE id = ?';
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan pembaruan menu: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($updateStmt, 'ssiiii', $name, $category, $price, $stock, $isAvailable, $id);
    $success = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    if ($success) {
        set_flash('success', 'Menu Diperbarui', 'Perubahan pada menu "' . $name . '" berhasil disimpan.');
    } else {
        set_flash('error', 'Gagal Memperbarui', 'Terjadi kesalahan saat menyimpan perubahan menu.');
    }

    redirect($redirectUrl);
}

if ($action === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false || $id === null) {
        set_flash('error', 'Data Tidak Valid', 'ID menu tidak valid.');
        redirect($redirectUrl);
    }

    // Cek menu
    $fetchSql = 'SELECT id, name FROM fnb_menus WHERE id = ? LIMIT 1';
    $fetchStmt = mysqli_prepare($conn, $fetchSql);
    if ($fetchStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal memuat data menu.');
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($fetchStmt, 'i', $id);
    mysqli_stmt_execute($fetchStmt);
    $fetchRes = mysqli_stmt_get_result($fetchStmt);
    $menu = $fetchRes !== false ? mysqli_fetch_assoc($fetchRes) : null;
    if ($fetchRes !== false) {
        mysqli_free_result($fetchRes);
    }
    mysqli_stmt_close($fetchStmt);

    if ($menu === null) {
        set_flash('error', 'Menu Tidak Ditemukan', 'Data menu tidak ditemukan.');
        redirect($redirectUrl);
    }

    // Cek apakah menu sudah pernah dipesan (fnb_order_items)
    $orderItemCountSql = 'SELECT COUNT(*) AS total FROM fnb_order_items WHERE menu_id = ?';
    $orderItemCountStmt = mysqli_prepare($conn, $orderItemCountSql);
    $hasOrders = false;
    if ($orderItemCountStmt !== false) {
        mysqli_stmt_bind_param($orderItemCountStmt, 'i', $id);
        mysqli_stmt_execute($orderItemCountStmt);
        $oicRes = mysqli_stmt_get_result($orderItemCountStmt);
        $oicRow = $oicRes !== false ? mysqli_fetch_assoc($oicRes) : null;
        if ($oicRes !== false) {
            mysqli_free_result($oicRes);
        }
        mysqli_stmt_close($orderItemCountStmt);
        if ((int) ($oicRow['total'] ?? 0) > 0) {
            $hasOrders = true;
        }
    }

    if ($hasOrders) {
        set_flash(
            'warning',
            'Menu Tidak Dapat Dihapus',
            'Menu sudah tercatat di riwayat pesanan dan tidak dapat dihapus. Ubah status menjadi Nonaktif saja.'
        );
        redirect($redirectUrl);
    }

    $deleteSql = 'DELETE FROM fnb_menus WHERE id = ?';
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if ($deleteStmt === false) {
        set_flash('error', 'Terjadi Kesalahan', 'Gagal menyiapkan penghapusan menu: ' . mysqli_error($conn));
        redirect($redirectUrl);
    }

    mysqli_stmt_bind_param($deleteStmt, 'i', $id);
    $success = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($success) {
        set_flash('success', 'Menu Dihapus', 'Menu "' . $menu['name'] . '" berhasil dihapus dari master data.');
    } else {
        set_flash('error', 'Gagal Menghapus', 'Menu F&B gagal dihapus.');
    }

    redirect($redirectUrl);
}

set_flash('error', 'Aksi Tidak Dikenal', 'Permintaan formulir tidak dikenali.');
redirect($redirectUrl);
