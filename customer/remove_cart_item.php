<?php
include '../db.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

// Guest path — remove by product_id from session
if (isset($_POST['guest']) && $_POST['guest'] === '1') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id && $product_id > 0) {
        unset($_SESSION['guest_cart'][(int)$product_id]);
    }
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'cart_count' => (int)array_sum($_SESSION['guest_cart'] ?? [])]);
        exit;
    }
    header('Location: cart.php');
    exit;
}

// Logged-in path
$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
if (!$item_id || $item_id < 1) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid item.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not logged in.']);
        exit;
    }
    header('Location: cart.php');
    exit;
}

$user_id = (string)(int)$_SESSION['user_id'];
$item_id = (string)(int)$item_id;

$stmt = oci_parse($conn, "DELETE FROM CART_ITEM
                           WHERE CART_ITEM_ID = :p_iid
                           AND CART_ID IN (
                               SELECT CART_ID FROM CART
                               WHERE CUSTOMER_ID = :p_uid AND STATUS = 'Active'
                           )");
oci_bind_by_name($stmt, ':p_iid', $item_id);
oci_bind_by_name($stmt, ':p_uid', $user_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    error_log('[CART REMOVE] ' . ($e['message'] ?? 'unknown'));
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Could not remove item. Please try again.']);
        exit;
    }
    $_SESSION['cart_error'] = 'Could not remove item. Please try again.';
    header('Location: cart.php');
    exit;
}
oci_free_statement($stmt);

if ($is_ajax) {
    $cc_stmt = oci_parse($conn, "SELECT NVL(SUM(ci.QUANTITY), 0) AS CNT
         FROM CART_ITEM ci
         JOIN CART c ON ci.CART_ID = c.CART_ID
         WHERE c.CUSTOMER_ID = :p_uid AND c.STATUS = 'Active'");
    oci_bind_by_name($cc_stmt, ':p_uid', $user_id);
    oci_execute($cc_stmt);
    $cc_row = oci_fetch_assoc($cc_stmt);
    $new_count = (int)($cc_row['CNT'] ?? 0);
    oci_free_statement($cc_stmt);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'cart_count' => $new_count]);
    exit;
}

header('Location: cart.php');
exit;
