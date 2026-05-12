<?php
include '../db.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function cart_respond(bool $ok, string $msg, string $location = '', ?int $cart_count = null): void {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        $payload = ['success' => $ok, $ok ? 'message' : 'error' => $msg];
        if ($cart_count !== null) {
            $payload['cart_count'] = $cart_count;
        }
        echo json_encode($payload);
    } else {
        $_SESSION[$ok ? 'cart_success' : 'cart_error'] = $msg;
        header('Location: ' . ($location ?: 'category.php'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity   = filter_input(INPUT_POST, 'quantity',   FILTER_VALIDATE_INT);

if (!$product_id || $product_id < 1 || !$quantity || $quantity < 1) {
    cart_respond(false, 'Invalid product or quantity.', 'category.php');
}

$product_id_int = (int)$product_id;
$quantity_int   = (int)$quantity;
$back = 'product.php?id=' . $product_id_int;

$is_logged_in = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';

// ── GUEST CART (session-based) ────────────────────────────────────────────────
if (!$is_logged_in) {
    $stmt = oci_parse($conn, "SELECT PRICE, STOCK_QUANTITY, MIN_ORDER, MAX_ORDER
                               FROM PRODUCT
                               WHERE PRODUCT_ID = :pid");
    oci_bind_by_name($stmt, ':pid', $product_id_int);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        cart_respond(false, 'Could not look up product.', $back);
    }
    $prod = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$prod) {
        cart_respond(false, 'Product not found.', $back);
    }

    $stock     = (int)$prod['STOCK_QUANTITY'];
    $min_order = max(1, (int)($prod['MIN_ORDER'] ?? 1));
    $max_order = (int)($prod['MAX_ORDER'] ?? 0);

    if ($stock < 1) {
        cart_respond(false, 'This product is out of stock.', $back);
    }

    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }

    $current_qty = (int)($_SESSION['guest_cart'][$product_id_int] ?? 0);
    $new_qty     = $current_qty + $quantity_int;
    if ($max_order > 0) {
        $new_qty = min($new_qty, $max_order);
    }
    $new_qty = min($new_qty, $stock);
    if ($new_qty < $min_order) {
        $new_qty = $min_order;
    }

    $_SESSION['guest_cart'][$product_id_int] = $new_qty;
    $guest_count = (int)array_sum($_SESSION['guest_cart']);
    cart_respond(true, 'Added to cart. You can keep browsing products.', $back, $guest_count);
}

// ── LOGGED-IN DB CART ─────────────────────────────────────────────────────────
$user_id    = (string)(int)$_SESSION['user_id'];
$product_id = (string)$product_id_int;
$quantity   = (string)$quantity_int;

// ── Step 1: find existing active cart ────────────────────────────────────────
$stmt = oci_parse($conn, "SELECT cart_id FROM CART WHERE customer_id = :p_uid AND status = 'Active'");
oci_bind_by_name($stmt, ':p_uid', $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    cart_respond(false, 'Cart lookup failed: ' . ($e['message'] ?? 'unknown'), $back);
}
$cart_row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if ($cart_row) {
    $cart_id = (string)(int)$cart_row['CART_ID'];
} else {
    // ── Step 2a: get next cart_id from sequence ───────────────────────────────
    $stmt = oci_parse($conn, 'SELECT CART_SEQ.NEXTVAL AS new_id FROM DUAL');
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        cart_respond(false, 'Sequence fetch failed: ' . ($e['message'] ?? 'unknown'), $back);
    }
    $seq = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    $cart_id = (string)(int)$seq['NEW_ID'];

    // ── Step 2b: insert new cart ──────────────────────────────────────────────
    $stmt = oci_parse($conn, "INSERT INTO CART (cart_id, customer_id, status)
                               VALUES (:cid, :p_uid, 'Active')");
    oci_bind_by_name($stmt, ':cid', $cart_id);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        cart_respond(false, 'Cart create failed: ' . ($e['message'] ?? 'unknown'), $back);
    }
    oci_free_statement($stmt);
}

// ── Step 3: check if product already in cart ─────────────────────────────────
$stmt = oci_parse($conn, 'SELECT cart_item_id, quantity FROM CART_ITEM
                           WHERE cart_id = :cid AND product_id = :pid');
oci_bind_by_name($stmt, ':cid', $cart_id);
oci_bind_by_name($stmt, ':pid', $product_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    cart_respond(false, 'Cart item lookup failed: ' . ($e['message'] ?? 'unknown'), $back);
}
$item_row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

// ── Step 4: fetch product price to satisfy TRG_CARTITEM_VALIDATE ─────────────
$stmt = oci_parse($conn, 'SELECT price FROM PRODUCT WHERE product_id = :p_pid');
oci_bind_by_name($stmt, ':p_pid', $product_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    cart_respond(false, 'Product price fetch failed: ' . ($e['message'] ?? 'unknown'), $back);
}
$prod = oci_fetch_assoc($stmt);
oci_free_statement($stmt);
if (!$prod) {
    cart_respond(false, 'Product not found.', $back);
}
$price = (string)$prod['PRICE'];

if ($item_row) {
    // ── Step 5a: increment quantity ───────────────────────────────────────────
    $new_qty = (string)((int)$item_row['QUANTITY'] + (int)$quantity);
    $item_id = (string)(int)$item_row['CART_ITEM_ID'];
    $stmt = oci_parse($conn, 'UPDATE CART_ITEM SET quantity = :qty WHERE cart_item_id = :iid');
    oci_bind_by_name($stmt, ':qty', $new_qty);
    oci_bind_by_name($stmt, ':iid', $item_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        cart_respond(false, 'Qty update failed: ' . ($e['message'] ?? 'unknown'), $back);
    }
    oci_free_statement($stmt);
} else {
    // ── Step 5b: pass price explicitly so VALIDATE passes regardless of trigger order
    $stmt = oci_parse($conn, 'INSERT INTO CART_ITEM (cart_item_id, product_id, quantity, cart_id, price)
                               VALUES (CART_ITEM_SEQ.NEXTVAL, :pid, :qty, :cid, :p_price)');
    oci_bind_by_name($stmt, ':pid',     $product_id);
    oci_bind_by_name($stmt, ':qty',     $quantity);
    oci_bind_by_name($stmt, ':cid',     $cart_id);
    oci_bind_by_name($stmt, ':p_price', $price);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $msg = 'Could not add item: ' . ($e['message'] ?? 'unknown');
        if ($e && strpos($e['message'], '-20102') !== false) {
            $msg = 'Product unavailable — trader has been suspended.';
        }
        cart_respond(false, $msg, $back);
    }
    oci_free_statement($stmt);
}

// ── Get new cart count for badge update ───────────────────────────────────────
$cc_stmt = oci_parse($conn, "SELECT NVL(SUM(ci.QUANTITY), 0) AS CNT
     FROM CART_ITEM ci
     JOIN CART c ON ci.CART_ID = c.CART_ID
     WHERE c.CUSTOMER_ID = :p_uid AND c.STATUS = 'Active'");
oci_bind_by_name($cc_stmt, ':p_uid', $user_id);
oci_execute($cc_stmt);
$cc_row = oci_fetch_assoc($cc_stmt);
$new_cart_count = (int)($cc_row['CNT'] ?? 0);
oci_free_statement($cc_stmt);

cart_respond(true, 'Added to cart. You can keep browsing products.', $back, $new_cart_count);
