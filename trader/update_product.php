<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';
require_once 'product_image_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}
csrf_require_post('trader_product_update');

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$product_id) {
    trader_flash_set('error', 'Invalid product selected.');
    header('Location: products.php');
    exit;
}

$existing = trader_fetch_owned_product($conn, (int)$product_id, $current_trader_id);
if (!$existing) {
    trader_flash_set('error', 'Product not found for this trader account.');
    header('Location: products.php');
    exit;
}

[$data, $errors] = trader_validate_product_input();
$shop_id_input = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$shop = $shop_id_input ? trader_fetch_owned_shop($conn, (int)$shop_id_input, $current_trader_id) : null;

if (!$shop) {
    $errors['shop_id'] = 'Please select one of your shops for this product.';
} else {
    $_SESSION['trader_selected_shop_id'] = (int)$shop['SHOP_ID'];
}

if (!empty($errors)) {
    trader_product_errors_set($errors);
    trader_old_set($_POST);
    header('Location: edit_product.php?id=' . (int)$product_id);
    exit;
}

$has_status = trader_product_status_column_exists($conn);
$has_image = trader_product_image_column_exists($conn);
$image_path = null;

if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $upload = save_product_image_upload($_FILES['image'], (int)$product_id);
    if (!$upload['ok']) {
        trader_product_errors_set([$upload['error']]);
        trader_old_set($_POST);
        header('Location: edit_product.php?id=' . (int)$product_id);
        exit;
    }
    $image_path = (string)$upload['path'];
}

$set_parts = [
    'PRODUCT_NAME = :product_name',
    'DESCRIPTION = :description',
    'PRICE = :price',
    'STOCK_QUANTITY = :stock_quantity',
    'EXPIRY_DATE = ' . ($data['expiry_date'] === '' ? 'NULL' : "TO_DATE(:expiry_date, 'YYYY-MM-DD')"),
    'SHOP_ID = :shop_id',
    'CATEGORY_ID = :category_id',
    'QUANTITY_PER_ITEM = :quantity_per_item',
    'MIN_ORDER = :min_order',
    'MAX_ORDER = :max_order',
    'ALLERGY_INFO = :allergy_info',
];

if ($has_image && $image_path !== null) {
    $set_parts[] = 'IMAGE_PATH = :image_path';
}
if ($has_status) {
    $set_parts[] = 'STATUS = :status';
}

$sql = "
    UPDATE PRODUCT p
    SET " . implode(",\n        ", $set_parts) . "
    WHERE p.PRODUCT_ID = :product_id
      AND EXISTS (
          SELECT 1
          FROM SHOP s
          WHERE s.SHOP_ID = p.SHOP_ID
            AND s.TRADER_ID = :trader_id
      )
";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER UPDATE PRODUCT PARSE] ' . ($err['message'] ?? 'unknown error'));
    trader_product_errors_set(['Could not update product. Please try again.']);
    trader_old_set($_POST);
    header('Location: edit_product.php?id=' . (int)$product_id);
    exit;
}

$description = $data['description'] !== '' ? $data['description'] : null;
$allergy_info = $data['allergy_info'] !== '' ? $data['allergy_info'] : null;
$expiry_date = $data['expiry_date'];
$product_name = (string)$data['product_name'];
$price = (float)$data['price'];
$stock_quantity = (int)$data['stock_quantity'];
$shop_id = (int)$shop['SHOP_ID'];
$category_id = (int)$data['category_id'];
$quantity_per_item = $data['quantity_per_item'];
$min_order = $data['min_order'];
$max_order = $data['max_order'];
$status = (string)$data['status'];

oci_bind_by_name($stmt, ':product_name', $product_name);
oci_bind_by_name($stmt, ':description', $description);
oci_bind_by_name($stmt, ':price', $price);
oci_bind_by_name($stmt, ':stock_quantity', $stock_quantity);
if ($data['expiry_date'] !== '') {
    oci_bind_by_name($stmt, ':expiry_date', $expiry_date);
}
oci_bind_by_name($stmt, ':shop_id', $shop_id);
oci_bind_by_name($stmt, ':category_id', $category_id);
oci_bind_by_name($stmt, ':quantity_per_item', $quantity_per_item);
oci_bind_by_name($stmt, ':min_order', $min_order);
oci_bind_by_name($stmt, ':max_order', $max_order);
oci_bind_by_name($stmt, ':allergy_info', $allergy_info);
if ($has_image && $image_path !== null) {
    oci_bind_by_name($stmt, ':image_path', $image_path);
}
if ($has_status) {
    oci_bind_by_name($stmt, ':status', $status);
}
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);

if (!oci_execute($stmt)) {
    $err = oci_error($stmt);
    error_log('[TRADER UPDATE PRODUCT] ' . ($err['message'] ?? 'unknown error'));
    oci_free_statement($stmt);
    trader_product_errors_set(['Could not update product. Please check the values and try again.']);
    trader_old_set($_POST);
    header('Location: edit_product.php?id=' . (int)$product_id);
    exit;
}

$updated = oci_num_rows($stmt);
oci_free_statement($stmt);

if ($updated < 1) {
    trader_flash_set('error', 'Product was not updated.');
} else {
    trader_flash_set('success', 'Product updated successfully.');
}

header('Location: products.php');
exit;
