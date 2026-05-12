<?php
include '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$qty     = filter_input(INPUT_POST, 'qty',     FILTER_VALIDATE_INT);

if (!$item_id || $item_id < 1 || !$qty || $qty < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid item or quantity']);
    exit;
}

// Guest path — item_id is product_id, update session quantity
if (isset($_POST['guest']) && $_POST['guest'] === '1') {
    $product_id = (string)(int)$item_id;
    $qty_int    = (int)$qty;

    $stmt = oci_parse($conn, 'SELECT PRICE, STOCK_QUANTITY, MAX_ORDER FROM PRODUCT WHERE PRODUCT_ID = :p_pid');
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        echo json_encode(['success' => false, 'error' => 'Product lookup failed']);
        exit;
    }
    $prod = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$prod) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    $price     = (float)$prod['PRICE'];
    $stock     = (int)$prod['STOCK_QUANTITY'];
    $max_order = (int)($prod['MAX_ORDER'] ?? 0);

    if ($qty_int > $stock) {
        if (!isset($_SESSION['guest_cart'])) { $_SESSION['guest_cart'] = []; }
        $_SESSION['guest_cart'][(int)$product_id] = $stock;
        echo json_encode([
            'success'    => false,
            'error'      => 'Only ' . $stock . ' in stock',
            'max_qty'    => $stock,
            'line_total' => number_format($stock * $price, 2),
            'new_qty'    => $stock,
        ]);
        exit;
    }

    $new_qty = $qty_int;
    if ($max_order > 0) {
        $new_qty = min($new_qty, $max_order);
    }
    $new_qty = min($new_qty, $stock);

    if (!isset($_SESSION['guest_cart'])) { $_SESSION['guest_cart'] = []; }
    $_SESSION['guest_cart'][(int)$product_id] = $new_qty;

    echo json_encode([
        'success'    => true,
        'new_qty'    => $new_qty,
        'line_total' => number_format($new_qty * $price, 2),
    ]);
    exit;
}

// Logged-in path
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (string)(int)$_SESSION['user_id'];
$item_id = (string)(int)$item_id;
$qty     = (string)(int)$qty;

$sql = "SELECT ci.CART_ITEM_ID, ci.PRICE, p.STOCK_QUANTITY
        FROM CART_ITEM ci
        JOIN CART c    ON ci.CART_ID    = c.CART_ID
        JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
        WHERE ci.CART_ITEM_ID = :p_iid
          AND c.CUSTOMER_ID   = :p_uid
          AND c.STATUS        = 'Active'";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':p_iid', $item_id);
oci_bind_by_name($stmt, ':p_uid', $user_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    error_log('[CART QTY UPDATE] ' . ($e['message'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'Lookup failed']);
    exit;
}

$row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Item not found in your cart']);
    exit;
}

$stock   = (int)$row['STOCK_QUANTITY'];
$price   = (float)$row['PRICE'];
$qty_int = (int)$qty;

if ($qty_int > $stock) {
    echo json_encode([
        'success'    => false,
        'error'      => 'Only ' . $stock . ' in stock',
        'max_qty'    => $stock,
        'line_total' => number_format($stock * $price, 2),
        'new_qty'    => $stock,
    ]);
    exit;
}

$upd = oci_parse($conn, 'UPDATE CART_ITEM SET QUANTITY = :p_qty WHERE CART_ITEM_ID = :p_iid');
oci_bind_by_name($upd, ':p_qty', $qty);
oci_bind_by_name($upd, ':p_iid', $item_id);

if (!oci_execute($upd)) {
    $e = oci_error($upd);
    oci_free_statement($upd);
    error_log('[CART QTY UPDATE] ' . ($e['message'] ?? 'unknown'));
    echo json_encode(['success' => false, 'error' => 'Update failed']);
    exit;
}
oci_free_statement($upd);

echo json_encode([
    'success'    => true,
    'new_qty'    => $qty_int,
    'line_total' => number_format($qty_int * $price, 2),
]);
exit;
