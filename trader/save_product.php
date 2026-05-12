<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';
require_once 'product_image_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_product.php');
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

if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $errors['image'] = 'Please upload a product image.';
}

if (!empty($errors)) {
    trader_product_errors_set($errors);
    trader_old_set($_POST);
    header('Location: add_product.php');
    exit;
}

$product_id = null;
$stmt = oci_parse($conn, 'SELECT PRODUCTS_SEQ.NEXTVAL AS PRODUCT_ID FROM DUAL');
if (!$stmt || !oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = $stmt ? oci_error($stmt) : oci_error($conn);
    error_log('[TRADER SAVE PRODUCT SEQ] ' . ($err['message'] ?? 'unknown error'));
    trader_product_errors_set(['Could not prepare a new product ID. Please try again.']);
    trader_old_set($_POST);
    header('Location: add_product.php');
    exit;
}
$row = oci_fetch_assoc($stmt);
$product_id = (int)$row['PRODUCT_ID'];
oci_free_statement($stmt);

$upload = save_product_image_upload($_FILES['image'], $product_id);
if (!$upload['ok']) {
    oci_rollback($conn);
    trader_product_errors_set([$upload['error']]);
    trader_old_set($_POST);
    header('Location: add_product.php');
    exit;
}

$has_status = trader_product_status_column_exists($conn);
$has_image = trader_product_image_column_exists($conn);

$columns = [
    'PRODUCT_ID',
    'PRODUCT_NAME',
    'DESCRIPTION',
    'PRICE',
    'STOCK_QUANTITY',
    'EXPIRY_DATE',
    'SHOP_ID',
    'CATEGORY_ID',
    'QUANTITY_PER_ITEM',
    'MIN_ORDER',
    'MAX_ORDER',
    'ALLERGY_INFO',
];
$values = [
    ':product_id',
    ':product_name',
    ':description',
    ':price',
    ':stock_quantity',
    $data['expiry_date'] === '' ? 'NULL' : "TO_DATE(:expiry_date, 'YYYY-MM-DD')",
    ':shop_id',
    ':category_id',
    ':quantity_per_item',
    ':min_order',
    ':max_order',
    ':allergy_info',
];

if ($has_image) {
    $columns[] = 'IMAGE_PATH';
    $values[] = ':image_path';
}
if ($has_status) {
    $columns[] = 'STATUS';
    $values[] = ':status';
}

$sql = 'INSERT INTO PRODUCT (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PRODUCT PARSE] ' . ($err['message'] ?? 'unknown error'));
    @unlink(dirname(__DIR__) . '/' . $upload['path']);
    oci_rollback($conn);
    trader_product_errors_set(['Could not save product. Please try again.']);
    trader_old_set($_POST);
    header('Location: add_product.php');
    exit;
}

$description = $data['description'] !== '' ? $data['description'] : null;
$allergy_info = $data['allergy_info'] !== '' ? $data['allergy_info'] : null;
$expiry_date = $data['expiry_date'];
$shop_id = (int)$shop['SHOP_ID'];
$image_path = (string)$upload['path'];
$product_name = (string)$data['product_name'];
$price = (float)$data['price'];
$stock_quantity = (int)$data['stock_quantity'];
$category_id = (int)$data['category_id'];
$quantity_per_item = $data['quantity_per_item'];
$min_order = $data['min_order'];
$max_order = $data['max_order'];
$status = (string)$data['status'];

oci_bind_by_name($stmt, ':product_id', $product_id);
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
if ($has_image) {
    oci_bind_by_name($stmt, ':image_path', $image_path);
}
if ($has_status) {
    oci_bind_by_name($stmt, ':status', $status);
}

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PRODUCT INSERT] ' . ($err['message'] ?? 'unknown error'));
    @unlink(dirname(__DIR__) . '/' . $upload['path']);
    oci_rollback($conn);
    oci_free_statement($stmt);
    trader_product_errors_set(['Could not save product. Please check the values and try again.']);
    trader_old_set($_POST);
    header('Location: add_product.php');
    exit;
}

oci_commit($conn);
oci_free_statement($stmt);

trader_flash_set('success', 'Product added successfully.');
header('Location: products.php');
exit;
