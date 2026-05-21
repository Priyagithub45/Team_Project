<?php
include '../db.php';
include 'auth_check.php';
require_once '../product_discount_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: category.php');
    exit;
}

csrf_require_post('customer_cart');

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$back = ($product_id && $product_id > 0) ? 'product.php?id=' . (int)$product_id : 'category.php';

if (!$product_id || $product_id < 1 || !$quantity || $quantity < 1) {
    $_SESSION['cart_error'] = 'Invalid product or quantity.';
    header("Location: $back");
    exit;
}

$product_id = (string)(int)$product_id;
$quantity = (int)$quantity;

$effective_price_sql = cfo_effective_price_sql('p');
$stmt = oci_parse($conn, "SELECT PRODUCT_ID, PRODUCT_NAME, {$effective_price_sql} AS PRICE, STOCK_QUANTITY, MIN_ORDER, MAX_ORDER
                          FROM PRODUCT p
                          WHERE PRODUCT_ID = :p_pid");
oci_bind_by_name($stmt, ':p_pid', $product_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    $_SESSION['cart_error'] = 'Could not prepare instant checkout: ' . ($e['message'] ?? 'unknown');
    header("Location: $back");
    exit;
}

$product = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$product) {
    $_SESSION['cart_error'] = 'Product not found.';
    header('Location: category.php');
    exit;
}

$stock = (int)($product['STOCK_QUANTITY'] ?? 0);
$min_order = max(1, (int)($product['MIN_ORDER'] ?? 1));
$max_order = (int)($product['MAX_ORDER'] ?? 99);

if ($stock <= 0) {
    $_SESSION['cart_error'] = 'This product is currently out of stock.';
    header("Location: $back");
    exit;
}

if ($quantity < $min_order || $quantity > $max_order) {
    $_SESSION['cart_error'] = 'Quantity must be between ' . $min_order . ' and ' . $max_order . '.';
    header("Location: $back");
    exit;
}

if ($quantity > $stock) {
    $_SESSION['cart_error'] = 'Only ' . $stock . ' item' . ($stock === 1 ? '' : 's') . ' available for instant checkout.';
    header("Location: $back");
    exit;
}

$_SESSION['checkout_mode'] = 'buy_now';
$_SESSION['buy_now_item'] = [
    'product_id' => (int)$product_id,
    'quantity' => $quantity,
    'created_at' => time(),
];
unset($_SESSION['paypal_checkout_token'], $_SESSION['paypal_method_id'], $_SESSION['paypal_paid'], $_SESSION['paypal_txn_id']);

$_SESSION['checkout_notice'] = 'Instant checkout ready for ' . $product['PRODUCT_NAME'] . '.';

if (empty($_SESSION['selected_slot_id'])) {
    header('Location: collection-slot.php');
    exit;
}

header('Location: checkout.php?mode=buy_now');
exit;
