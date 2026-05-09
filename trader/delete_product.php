<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$product_id) {
    trader_flash_set('error', 'Invalid product selected.');
    header('Location: products.php');
    exit;
}

$has_status = trader_product_status_column_exists($conn);

if ($has_status) {
    $sql = "
        UPDATE PRODUCT p
        SET STATUS = 'INACTIVE'
        WHERE p.PRODUCT_ID = :product_id
          AND EXISTS (
              SELECT 1
              FROM SHOP s
              WHERE s.SHOP_ID = p.SHOP_ID
                AND s.TRADER_ID = :trader_id
          )
    ";
} else {
    $sql = "
        UPDATE PRODUCT p
        SET STOCK_QUANTITY = 0
        WHERE p.PRODUCT_ID = :product_id
          AND EXISTS (
              SELECT 1
              FROM SHOP s
              WHERE s.SHOP_ID = p.SHOP_ID
                AND s.TRADER_ID = :trader_id
          )
    ";
}

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER DELETE PRODUCT PARSE] ' . ($err['message'] ?? 'unknown error'));
    trader_flash_set('error', 'Could not inactivate product.');
    header('Location: products.php');
    exit;
}

oci_bind_by_name($stmt, ':product_id', $product_id);
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);

if (!oci_execute($stmt)) {
    $err = oci_error($stmt);
    error_log('[TRADER DELETE PRODUCT] ' . ($err['message'] ?? 'unknown error'));
    oci_free_statement($stmt);
    trader_flash_set('error', 'Could not inactivate product.');
    header('Location: products.php');
    exit;
}

$updated = oci_num_rows($stmt);
oci_free_statement($stmt);

if ($updated < 1) {
    trader_flash_set('error', 'Product not found for this trader account.');
} else {
    trader_flash_set('success', 'Product inactivated successfully.');
}

header('Location: products.php');
exit;
