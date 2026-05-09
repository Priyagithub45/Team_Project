<?php

function trader_product_status_column_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'PRODUCT'
           AND COLUMN_NAME = 'STATUS'"
    );

    if (!$stmt || !oci_execute($stmt)) {
        $exists = false;
        return $exists;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $exists = ((int)($row['CNT'] ?? 0) > 0);
    return $exists;
}

function trader_product_image_column_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'PRODUCT'
           AND COLUMN_NAME = 'IMAGE_PATH'"
    );

    if (!$stmt || !oci_execute($stmt)) {
        $exists = false;
        return $exists;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $exists = ((int)($row['CNT'] ?? 0) > 0);
    return $exists;
}

function trader_current_shop($conn, int $trader_id): ?array
{
    $stmt = oci_parse(
        $conn,
        "SELECT SHOP_ID, SHOP_NAME
         FROM SHOP
         WHERE TRADER_ID = :trader_id
         ORDER BY SHOP_ID"
    );

    if (!$stmt) {
        return null;
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return null;
    }

    $shop = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    return $shop;
}

function trader_categories($conn): array
{
    $stmt = oci_parse($conn, 'SELECT CATEGORY_ID, CATEGORY_NAME FROM CATEGORY ORDER BY CATEGORY_NAME');
    if (!$stmt || !oci_execute($stmt)) {
        return [];
    }

    $categories = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $categories[] = $row;
    }
    oci_free_statement($stmt);

    return $categories;
}

function trader_flash_set(string $type, string $message): void
{
    $_SESSION['trader_product_flash'] = ['type' => $type, 'message' => $message];
}

function trader_flash_get(): ?array
{
    $flash = $_SESSION['trader_product_flash'] ?? null;
    unset($_SESSION['trader_product_flash']);
    return is_array($flash) ? $flash : null;
}

function trader_old_set(array $old): void
{
    $_SESSION['trader_product_old'] = $old;
}

function trader_old_get(): array
{
    $old = $_SESSION['trader_product_old'] ?? [];
    unset($_SESSION['trader_product_old']);
    return is_array($old) ? $old : [];
}

function trader_product_errors_set(array $errors): void
{
    $_SESSION['trader_product_errors'] = $errors;
}

function trader_product_errors_get(): array
{
    $errors = $_SESSION['trader_product_errors'] ?? [];
    unset($_SESSION['trader_product_errors']);
    return is_array($errors) ? $errors : [];
}

function trader_clean_string(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function trader_optional_number(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $number === false ? null : (int)$number;
}

function trader_validate_product_input(): array
{
    $data = [
        'product_name' => trader_clean_string('product_name'),
        'description' => trader_clean_string('description'),
        'price' => trader_clean_string('price'),
        'stock_quantity' => trader_clean_string('stock_quantity'),
        'expiry_date' => trader_clean_string('expiry_date'),
        'quantity_per_item' => trader_clean_string('quantity_per_item'),
        'min_order' => trader_clean_string('min_order'),
        'max_order' => trader_clean_string('max_order'),
        'allergy_info' => trader_clean_string('allergy_info'),
        'category_id' => trader_clean_string('category_id'),
        'status' => strtoupper(trader_clean_string('status') ?: 'ACTIVE'),
    ];

    $errors = [];

    if ($data['product_name'] === '') {
        $errors[] = 'Product name is required.';
    } elseif (strlen($data['product_name']) > 100) {
        $errors[] = 'Product name must be 100 characters or fewer.';
    }

    if (strlen($data['description']) > 200) {
        $errors[] = 'Description must be 200 characters or fewer.';
    }

    $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }

    $stock = filter_var($data['stock_quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($stock === false) {
        $errors[] = 'Stock quantity cannot be negative.';
    }

    $category_id = filter_var($data['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($category_id === false) {
        $errors[] = 'Please select a valid category.';
    }

    $quantity_per_item = trader_optional_number($data['quantity_per_item']);
    $min_order = trader_optional_number($data['min_order']);
    $max_order = trader_optional_number($data['max_order']);

    if ($data['quantity_per_item'] !== '' && $quantity_per_item === null) {
        $errors[] = 'Quantity per item must be 0 or greater.';
    }
    if ($data['min_order'] !== '' && $min_order === null) {
        $errors[] = 'Minimum order must be 0 or greater.';
    }
    if ($data['max_order'] !== '' && $max_order === null) {
        $errors[] = 'Maximum order must be 0 or greater.';
    }
    if ($min_order !== null && $max_order !== null && $min_order > $max_order) {
        $errors[] = 'Minimum order cannot be greater than maximum order.';
    }

    if ($data['expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expiry_date'])) {
        $errors[] = 'Expiry date must use YYYY-MM-DD format.';
    }

    if (strlen($data['allergy_info']) > 200) {
        $errors[] = 'Allergy information must be 200 characters or fewer.';
    }

    if (!in_array($data['status'], ['ACTIVE', 'INACTIVE', 'DISCONTINUED'], true)) {
        $errors[] = 'Please select a valid product status.';
    }

    $data['price'] = $price === false ? null : round((float)$price, 2);
    $data['stock_quantity'] = $stock === false ? null : (int)$stock;
    $data['category_id'] = $category_id === false ? null : (int)$category_id;
    $data['quantity_per_item'] = $quantity_per_item;
    $data['min_order'] = $min_order;
    $data['max_order'] = $max_order;

    return [$data, $errors];
}

function trader_fetch_owned_product($conn, int $product_id, int $trader_id): ?array
{
    $status_select = trader_product_status_column_exists($conn) ? ", NVL(p.STATUS, 'ACTIVE') AS STATUS" : ", 'ACTIVE' AS STATUS";
    $image_select = trader_product_image_column_exists($conn) ? ", p.IMAGE_PATH" : ", NULL AS IMAGE_PATH";
    $sql = "
        SELECT p.PRODUCT_ID,
               p.PRODUCT_NAME,
               p.DESCRIPTION,
               p.PRICE,
               p.STOCK_QUANTITY,
               TO_CHAR(p.EXPIRY_DATE, 'YYYY-MM-DD') AS EXPIRY_DATE,
               p.CATEGORY_ID,
               p.QUANTITY_PER_ITEM,
               p.MIN_ORDER,
               p.MAX_ORDER,
               p.ALLERGY_INFO
               {$status_select}
               {$image_select}
        FROM PRODUCT p
        JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
        WHERE p.PRODUCT_ID = :product_id
          AND s.TRADER_ID = :trader_id
    ";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return null;
    }

    oci_bind_by_name($stmt, ':product_id', $product_id);
    oci_bind_by_name($stmt, ':trader_id', $trader_id);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return null;
    }

    $product = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    return $product;
}
