<?php
/**
 * actions/cafe_order_delete_item.php
 * Menghapus satu item pesanan F&B dari sesi PS aktif, mengembalikan stok menu,
 * lalu menyinkronkan ulang nominal tagihan sesi.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Item pesanan hanya dapat dihapus melalui tombol hapus di detail sesi.');
    redirect('pages/billing.php');
}

$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

$csrfFallback = ($sessionId !== false && $sessionId !== null)
    ? 'pages/session_detail.php?id=' . (int) $sessionId
    : 'pages/billing.php';

verify_csrf_token($csrfFallback);

if ($itemId === false || $itemId === null || $sessionId === false || $sessionId === null) {
    set_flash('error', 'Data Tidak Valid', 'Item atau sesi yang ingin dihapus tidak valid.');
    redirect('pages/billing.php');
}

$backUrl = 'pages/session_detail.php?id=' . $sessionId;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
    }

    $sessionSql = "SELECT id, ps_cost, session_status
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
    $sessionRow = $sessionResult !== false ? mysqli_fetch_assoc($sessionResult) : null;
    if ($sessionResult !== false) {
        mysqli_free_result($sessionResult);
    }
    mysqli_stmt_close($sessionStmt);

    if ($sessionRow === null) {
        throw new DomainException('Sesi billing tidak ditemukan.');
    }

    if ((string) $sessionRow['session_status'] !== 'active') {
        throw new DomainException('Sesi sudah selesai. Item pesanan tidak dapat dihapus lagi.');
    }

    $psCost = (int) $sessionRow['ps_cost'];

    $itemSql = "SELECT i.id, i.order_id, i.menu_id, i.quantity, m.name AS menu_name
                FROM fnb_order_items i
                INNER JOIN fnb_orders o ON o.id = i.order_id
                INNER JOIN fnb_menus m ON m.id = i.menu_id
                WHERE i.id = ? AND o.session_id = ?
                FOR UPDATE";
    $itemStmt = mysqli_prepare($conn, $itemSql);
    if ($itemStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query item pesanan: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($itemStmt, 'ii', $itemId, $sessionId);
    mysqli_stmt_execute($itemStmt);
    $itemResult = mysqli_stmt_get_result($itemStmt);
    $itemRow = $itemResult !== false ? mysqli_fetch_assoc($itemResult) : null;
    if ($itemResult !== false) {
        mysqli_free_result($itemResult);
    }
    mysqli_stmt_close($itemStmt);

    if ($itemRow === null) {
        throw new DomainException('Item pesanan tidak ditemukan pada sesi ini.');
    }

    $orderId = (int) $itemRow['order_id'];
    $menuId = (int) $itemRow['menu_id'];
    $quantity = (int) $itemRow['quantity'];
    $menuName = (string) $itemRow['menu_name'];

    $restoreSql = "UPDATE fnb_menus SET stock = stock + ? WHERE id = ?";
    $restoreStmt = mysqli_prepare($conn, $restoreSql);
    if ($restoreStmt === false) {
        throw new RuntimeException('Gagal menyiapkan pengembalian stok: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($restoreStmt, 'ii', $quantity, $menuId);
    if (!mysqli_stmt_execute($restoreStmt)) {
        throw new RuntimeException('Gagal mengembalikan stok menu: ' . mysqli_stmt_error($restoreStmt));
    }
    mysqli_stmt_close($restoreStmt);

    $deleteSql = "DELETE FROM fnb_order_items WHERE id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if ($deleteStmt === false) {
        throw new RuntimeException('Gagal menyiapkan penghapusan item: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($deleteStmt, 'i', $itemId);
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new RuntimeException('Gagal menghapus item pesanan: ' . mysqli_stmt_error($deleteStmt));
    }

    if (mysqli_stmt_affected_rows($deleteStmt) !== 1) {
        mysqli_stmt_close($deleteStmt);
        throw new DomainException('Item pesanan sudah berubah. Silakan muat ulang halaman.');
    }
    mysqli_stmt_close($deleteStmt);

    $remainingSql = "SELECT COALESCE(SUM(subtotal), 0) AS remaining
                     FROM fnb_order_items
                     WHERE order_id = ?";
    $remainingStmt = mysqli_prepare($conn, $remainingSql);
    if ($remainingStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query sisa pesanan: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($remainingStmt, 'i', $orderId);
    mysqli_stmt_execute($remainingStmt);
    $remainingResult = mysqli_stmt_get_result($remainingStmt);
    $remainingRow = $remainingResult !== false ? mysqli_fetch_assoc($remainingResult) : null;
    if ($remainingResult !== false) {
        mysqli_free_result($remainingResult);
    }
    mysqli_stmt_close($remainingStmt);

    $remainingTotal = (int) ($remainingRow['remaining'] ?? 0);

    if ($remainingTotal <= 0) {
        $deleteOrderSql = "DELETE FROM fnb_orders WHERE id = ?";
        $deleteOrderStmt = mysqli_prepare($conn, $deleteOrderSql);
        if ($deleteOrderStmt === false) {
            throw new RuntimeException('Gagal menyiapkan penghapusan pesanan: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($deleteOrderStmt, 'i', $orderId);
        if (!mysqli_stmt_execute($deleteOrderStmt)) {
            throw new RuntimeException('Gagal menghapus pesanan kosong: ' . mysqli_stmt_error($deleteOrderStmt));
        }
        mysqli_stmt_close($deleteOrderStmt);
    } else {
        $updateOrderSql = "UPDATE fnb_orders SET total_amount = ? WHERE id = ?";
        $updateOrderStmt = mysqli_prepare($conn, $updateOrderSql);
        if ($updateOrderStmt === false) {
            throw new RuntimeException('Gagal menyiapkan update total pesanan: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($updateOrderStmt, 'ii', $remainingTotal, $orderId);
        if (!mysqli_stmt_execute($updateOrderStmt)) {
            throw new RuntimeException('Gagal memperbarui total pesanan: ' . mysqli_stmt_error($updateOrderStmt));
        }
        mysqli_stmt_close($updateOrderStmt);
    }

    $sumSql = "SELECT COALESCE(SUM(total_amount), 0) AS total_fnb
               FROM fnb_orders
               WHERE session_id = ?";
    $sumStmt = mysqli_prepare($conn, $sumSql);
    if ($sumStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query total F&B: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($sumStmt, 'i', $sessionId);
    mysqli_stmt_execute($sumStmt);
    $sumResult = mysqli_stmt_get_result($sumStmt);
    $sumRow = $sumResult !== false ? mysqli_fetch_assoc($sumResult) : null;
    if ($sumResult !== false) {
        mysqli_free_result($sumResult);
    }
    mysqli_stmt_close($sumStmt);

    $fnbCost = (int) ($sumRow['total_fnb'] ?? 0);

    $updateSessionSql = "UPDATE billing_sessions
                         SET fnb_cost = ?, total_cost = ? + ?, updated_at = NOW()
                         WHERE id = ? AND session_status = 'active'";
    $updateSessionStmt = mysqli_prepare($conn, $updateSessionSql);
    if ($updateSessionStmt === false) {
        throw new RuntimeException('Gagal menyiapkan update sesi: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($updateSessionStmt, 'iiii', $fnbCost, $psCost, $fnbCost, $sessionId);
    if (!mysqli_stmt_execute($updateSessionStmt)) {
        throw new RuntimeException('Gagal memperbarui tagihan sesi: ' . mysqli_stmt_error($updateSessionStmt));
    }

    if (mysqli_stmt_affected_rows($updateSessionStmt) !== 1) {
        mysqli_stmt_close($updateSessionStmt);
        throw new DomainException('Sesi unit PS berubah saat item dihapus. Silakan muat ulang.');
    }
    mysqli_stmt_close($updateSessionStmt);

    mysqli_commit($conn);

    set_flash(
        'success',
        'Item Pesanan Dihapus',
        'Item "' . $menuName . '" (' . $quantity . ' pcs) dihapus dan stok menu dikembalikan. Tagihan sesi sudah disesuaikan.'
    );
} catch (DomainException $exception) {
    mysqli_rollback($conn);
    set_flash('error', 'Gagal Menghapus Item', $exception->getMessage());
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('[DGS] cafe_order_delete_item gagal: ' . $exception->getMessage());
    set_flash('error', 'Terjadi Kesalahan', 'Item pesanan gagal dihapus karena masalah sistem. Silakan coba lagi.');
}

redirect($backUrl);
