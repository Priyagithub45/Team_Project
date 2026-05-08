<?php
include '../db.php';
include 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$product_id || $product_id < 1 || !$quantity || $quantity < 1) {
    $_SESSION['cart_error'] = 'Invalid product or quantity.';
    header('Location: category.php');
    exit;
}

// Use strings — Oracle converts VARCHAR2 to NUMBER implicitly, avoids OCI type constant issues
$user_id = (string) (int) $_SESSION['user_id'];
$product_id = (string) (int) $product_id;
$quantity = (string) (int) $quantity;
$back = 'product.php?id=' . $product_id;

// ── Step 1: find existing active cart ────────────────────────────────────────
$stmt = oci_parse($conn, "SELECT cart_id FROM CART WHERE customer_id = :p_uid AND status = 'Active'");
oci_bind_by_name($stmt, ':p_uid', $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    $_SESSION['cart_error'] = 'Cart lookup failed: ' . ($e['message'] ?? 'unknown');
    header("Location: $back");
    exit;
}
$cart_row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if ($cart_row) {
    $cart_id = (string) (int) $cart_row['CART_ID'];
} else {
    // ── Step 2a: get next cart_id from sequence ───────────────────────────────
    $stmt = oci_parse($conn, 'SELECT CART_SEQ.NEXTVAL AS new_id FROM DUAL');
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $_SESSION['cart_error'] = 'Sequence fetch failed: ' . ($e['message'] ?? 'unknown');
        header("Location: $back");
        exit;
    }
    $seq = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    $cart_id = (string) (int) $seq['NEW_ID'];

    // ── Step 2b: insert new cart ──────────────────────────────────────────────
    $stmt = oci_parse($conn, "INSERT INTO CART (cart_id, customer_id, status)
                               VALUES (:cid, :p_uid, 'Active')");
    oci_bind_by_name($stmt, ':cid', $cart_id);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $_SESSION['cart_error'] = 'Cart create failed: ' . ($e['message'] ?? 'unknown');
        header("Location: $back");
        exit;
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
    $_SESSION['cart_error'] = 'Cart item lookup failed: ' . ($e['message'] ?? 'unknown');
    header("Location: $back");
    exit;
}
$item_row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

// ── Step 4: fetch product price to satisfy TRG_CARTITEM_VALIDATE ─────────────
$stmt = oci_parse($conn, 'SELECT price FROM PRODUCT WHERE product_id = :p_pid');
oci_bind_by_name($stmt, ':p_pid', $product_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    $_SESSION['cart_error'] = 'Product price fetch failed: ' . ($e['message'] ?? 'unknown');
    header("Location: $back");
    exit;
}
$prod = oci_fetch_assoc($stmt);
oci_free_statement($stmt);
if (!$prod) {
    $_SESSION['cart_error'] = 'Product not found.';
    header("Location: $back");
    exit;
}
$price = (string)$prod['PRICE'];

if ($item_row) {
    // ── Step 5a: increment quantity ───────────────────────────────────────────
    $new_qty = (string) ((int) $item_row['QUANTITY'] + (int) $quantity);
    $item_id = (string) (int) $item_row['CART_ITEM_ID'];
    $stmt = oci_parse($conn, 'UPDATE CART_ITEM SET quantity = :qty WHERE cart_item_id = :iid');
    oci_bind_by_name($stmt, ':qty', $new_qty);
    oci_bind_by_name($stmt, ':iid', $item_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $_SESSION['cart_error'] = 'Qty update failed: ' . ($e['message'] ?? 'unknown');
        header("Location: $back");
        exit;
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
        $_SESSION['cart_error'] = $msg;
        header("Location: $back");
        exit;
    }
    oci_free_statement($stmt);
}

$_SESSION['cart_success'] = 'Added to cart. You can keep browsing products.';
header("Location: $back");
exit;
