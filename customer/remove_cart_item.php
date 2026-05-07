<?php
include '../db.php';
include 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
if (!$item_id || $item_id < 1) {
    header('Location: cart.php');
    exit;
}

$user_id  = (string)(int)$_SESSION['user_id'];
$item_id  = (string)(int)$item_id;

// Security: only delete if item belongs to THIS user's active cart
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
    $_SESSION['cart_error'] = 'Remove failed: ' . ($e['message'] ?? 'unknown');
    header('Location: cart.php');
    exit;
}

$rows_deleted = oci_num_rows($stmt);
oci_free_statement($stmt);

if ($rows_deleted === 0) {
    $_SESSION['cart_error'] = 'Item not found or does not belong to your cart.';
}

header('Location: cart.php');
exit;
