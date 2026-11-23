<?php
/**
 * actions/cafe_order_create.php
 * Menyimpan pesanan F&B (makanan/minuman) yang berelasi ke sesi PS aktif
 * atau sebagai pesanan walk-in yang langsung lunas.
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

require_admin_auth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    set_flash('warning', 'Permintaan Tidak Valid', 'Formulir pesanan harus dikirim melalui tombol Simpan Pesanan.');
    redirect('pages/cafe.php');
}

verify_csrf_token('pages/cafe.php');

$targetRaw = $_POST['target_session_id'] ?? 'walkin';
$targetRaw = is_string($targetRaw) ? $targetRaw : '';
$itemsInput = $_POST['items'] ?? null;
$walkinCustomerRaw = $_POST['customer_name'] ?? '';
$walkinCustomer = is_string($walkinCustomerRaw) ? trim($walkinCustomerRaw) : '';

if (!is_array($itemsInput) || $itemsInput === []) {
    set_flash('error', 'Keranjang Kosong', 'Pilih minimal satu menu sebelum menyimpan pesanan.');
    redirect('pages/cafe.php');
}

if (count($itemsInput) > 50) {
    set_flash('error', 'Pesanan Terlalu Banyak', 'Maksimal 50 jenis menu dalam satu pesanan.');
    redirect('pages/cafe.php');
}

if ($walkinCustomer === '') {
    $walkinCustomer = 'Walk-in Customer';
}

if (mb_strlen($walkinCustomer) > 100) {
    $walkinCustomer = mb_substr($walkinCustomer, 0, 100);
}

$savedOrder = false;
$sessionId = null;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Gagal memulai transaksi: ' . mysqli_error($conn));
    }

    $stationId = null;
    $customerName = $walkinCustomer;
    $paymentStatus = 'paid';

    if ($targetRaw !== 'walkin') {
        $targetSessionId = (int) $targetRaw;

        if ($targetSessionId <= 0) {
            throw new DomainException('Target unit PS tidak valid.');
        }

        $sessionSql = "SELECT id, station_id, customer_name, session_status
                       FROM billing_sessions
                       WHERE id = ?
                       FOR UPDATE";
        $sessionStmt = mysqli_prepare($conn, $sessionSql);
        if ($sessionStmt === false) {
            throw new RuntimeException('Gagal menyiapkan query sesi: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($sessionStmt, 'i', $targetSessionId);
        mysqli_stmt_execute($sessionStmt);
        $sessionResult = mysqli_stmt_get_result($sessionStmt);
        $sessionRow = $sessionResult !== false ? mysqli_fetch_assoc($sessionResult) : null;
        if ($sessionResult !== false) {
            mysqli_free_result($sessionResult);
        }
        mysqli_stmt_close($sessionStmt);

        if ($sessionRow === null) {
            throw new DomainException('Sesi unit PS tidak ditemukan.');
        }

        if ((string) $sessionRow['session_status'] !== 'active') {
            throw new DomainException('Sesi unit PS sudah selesai. Muat ulang dashboard billing.');
        }

        $sessionId = $targetSessionId;
        $stationId = (int) $sessionRow['station_id'];
        $customerName = (string) $sessionRow['customer_name'];
        $paymentStatus = 'unpaid';
    }

    $orderItems = [];
    $orderTotal = 0;

    $menuSql = "SELECT id, name, price, stock, is_available FROM fnb_menus WHERE id = ? FOR UPDATE";

    foreach ($itemsInput as $menuIdKey => $qtyValue) {
        $menuId = (int) $menuIdKey;
        $qty = (int) $qtyValue;

        if ($menuId <= 0 || $qty <= 0) {
            throw new DomainException('Terdapat item pesanan yang tidak valid. Silakan perbarui keranjang.');
        }

        if ($qty > 999) {
            throw new DomainException('Jumlah item terlalu banyak (maksimal 999 per menu).');
        }

        $menuStmt = mysqli_prepare($conn, $menuSql);
        if ($menuStmt === false) {
            throw new RuntimeException('Gagal menyiapkan query menu: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($menuStmt, 'i', $menuId);
        mysqli_stmt_execute($menuStmt);
        $menuResult = mysqli_stmt_get_result($menuStmt);
        $menuRow = $menuResult !== false ? mysqli_fetch_assoc($menuResult) : null;
        if ($menuResult !== false) {
            mysqli_free_result($menuResult);
        }
        mysqli_stmt_close($menuStmt);

        if ($menuRow === null) {
            throw new DomainException('Menu yang dipilih tidak ditemukan. Silakan muat ulang halaman Cafe.');
        }

        $menuName = (string) $menuRow['name'];

        if ((int) $menuRow['is_available'] !== 1) {
            throw new DomainException('Menu "' . $menuName . '" sedang tidak tersedia.');
        }

        $stock = (int) $menuRow['stock'];
        if ($stock < $qty) {
            throw new DomainException(
                'Stok "' . $menuName . '" tidak cukup. Sisa stok ' . $stock . ', diminta ' . $qty . '.'
            );
        }

        $unitPrice = (int) $menuRow['price'];
        $subtotal = $unitPrice * $qty;
        $orderTotal += $subtotal;

        $orderItems[] = [
            'menu_id' => $menuId,
            'name' => $menuName,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
        ];
    }

    if ($orderItems === []) {
        throw new DomainException('Tidak ada item valid untuk disimpan.');
    }

    if ($orderTotal <= 0) {
        throw new DomainException('Total pesanan tidak valid.');
    }

    $orderSql = "INSERT INTO fnb_orders
            (station_id, session_id, customer_name, total_amount, payment_status, ordered_at)
        VALUES (?, ?, ?, ?, ?, NOW())";
    $orderStmt = mysqli_prepare($conn, $orderSql);
    if ($orderStmt === false) {
        throw new RuntimeException('Gagal menyiapkan query pesanan: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($orderStmt, 'iisis', $stationId, $sessionId, $customerName, $orderTotal, $paymentStatus);
    if (!mysqli_stmt_execute($orderStmt)) {
        throw new RuntimeException('Gagal menyimpan pesanan: ' . mysqli_stmt_error($orderStmt));
    }
    mysqli_stmt_close($orderStmt);

    $orderId = (int) mysqli_insert_id($conn);
    if ($orderId <= 0) {
        throw new RuntimeException('ID pesanan tidak valid setelah penyimpanan.');
    }

    $itemSql = "INSERT INTO fnb_order_items (order_id, menu_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)";
    $stockSql = "UPDATE fnb_menus SET stock = stock - ? WHERE id = ?";

    foreach ($orderItems as $orderItem) {
        $itemStmt = mysqli_prepare($conn, $itemSql);
        if ($itemStmt === false) {
            throw new RuntimeException('Gagal menyiapkan query item pesanan: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $itemStmt,
            'iiiii',
            $orderId,
            $orderItem['menu_id'],
            $orderItem['quantity'],
            $orderItem['unit_price'],
            $orderItem['subtotal']
        );
        if (!mysqli_stmt_execute($itemStmt)) {
            throw new RuntimeException('Gagal menyimpan item pesanan: ' . mysqli_stmt_error($itemStmt));
        }
        mysqli_stmt_close($itemStmt);

        $stockStmt = mysqli_prepare($conn, $stockSql);
        if ($stockStmt === false) {
            throw new RuntimeException('Gagal menyiapkan update stok: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stockStmt, 'ii', $orderItem['quantity'], $orderItem['menu_id']);
        if (!mysqli_stmt_execute($stockStmt)) {
            throw new RuntimeException('Gagal mengurangi stok menu: ' . mysqli_stmt_error($stockStmt));
        }
        mysqli_stmt_close($stockStmt);
    }

    if ($sessionId !== null) {
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
                             SET fnb_cost = ?, total_cost = ps_cost + ?, updated_at = NOW()
                             WHERE id = ? AND session_status = 'active'";
        $updateSessionStmt = mysqli_prepare($conn, $updateSessionSql);
        if ($updateSessionStmt === false) {
            throw new RuntimeException('Gagal menyiapkan update sesi: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($updateSessionStmt, 'iii', $fnbCost, $fnbCost, $sessionId);
        if (!mysqli_stmt_execute($updateSessionStmt)) {
            throw new RuntimeException('Gagal memperbarui tagihan sesi: ' . mysqli_stmt_error($updateSessionStmt));
        }

        if (mysqli_stmt_affected_rows($updateSessionStmt) !== 1) {
            mysqli_stmt_close($updateSessionStmt);
            throw new DomainException('Sesi unit PS berubah saat pesanan diproses. Silakan muat ulang.');
        }
        mysqli_stmt_close($updateSessionStmt);
    }

    mysqli_commit($conn);
    $savedOrder = true;

    if ($sessionId !== null) {
        set_flash(
            'success',
            'Pesanan Disimpan',
            'Pesanan untuk ' . $customerName . ' berhasil digabung ke tagihan sesi. Total '
            . format_rupiah($orderTotal) . ' (' . count($orderItems) . ' jenis menu).'
        );
    } else {
        set_flash(
            'success',
            'Pesanan Walk-in Disimpan',
            'Pesanan ' . $customerName . ' sebesar ' . format_rupiah($orderTotal)
            . ' tercatat lunas. Stok menu sudah diperbarui.'
        );
    }
} catch (DomainException $exception) {
    mysqli_rollback($conn);
    set_flash('error', 'Pesanan Gagal Disimpan', $exception->getMessage());
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    error_log('[DGS] cafe_order_create gagal: ' . $exception->getMessage());
    set_flash('error', 'Terjadi Kesalahan', 'Pesanan gagal disimpan karena masalah sistem. Silakan coba lagi.');
}

if ($savedOrder && $sessionId !== null) {
    redirect('pages/billing.php');
}

redirect('pages/cafe.php');
