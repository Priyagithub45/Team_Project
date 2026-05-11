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
    $shop = trader_fetch_current_shop($conn, $trader_id);
    if ($shop) {
        return $shop;
    }

    if (!trader_create_missing_shop($conn, $trader_id)) {
        return null;
    }

    return trader_fetch_current_shop($conn, $trader_id);
}

function trader_fetch_current_shop($conn, int $trader_id): ?array
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

function trader_next_shop_id($conn): ?int
{
    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_SEQUENCES
         WHERE SEQUENCE_NAME = 'SHOP_SEQ'"
    );

    if ($stmt && oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);

        if ((int)($row['CNT'] ?? 0) > 0) {
            $seq_stmt = oci_parse($conn, 'SELECT SHOP_SEQ.NEXTVAL AS SHOP_ID FROM DUAL');
            if ($seq_stmt && oci_execute($seq_stmt, OCI_NO_AUTO_COMMIT)) {
                $seq_row = oci_fetch_assoc($seq_stmt);
                oci_free_statement($seq_stmt);
                return (int)($seq_row['SHOP_ID'] ?? 0) ?: null;
            }
            if ($seq_stmt) {
                oci_free_statement($seq_stmt);
            }
        }
    } elseif ($stmt) {
        oci_free_statement($stmt);
    }

    $fallback_stmt = oci_parse($conn, 'SELECT NVL(MAX(SHOP_ID), 0) + 1 AS SHOP_ID FROM SHOP');
    if (!$fallback_stmt || !oci_execute($fallback_stmt, OCI_NO_AUTO_COMMIT)) {
        if ($fallback_stmt) {
            oci_free_statement($fallback_stmt);
        }
        return null;
    }

    $row = oci_fetch_assoc($fallback_stmt);
    oci_free_statement($fallback_stmt);

    return (int)($row['SHOP_ID'] ?? 0) ?: null;
}

function trader_create_missing_shop($conn, int $trader_id): bool
{
    $stmt = oci_parse(
        $conn,
        "SELECT su.NAME,
                su.PHONE_NO,
                su.ADDRESS,
                su.STATUS AS USER_STATUS,
                t.BUSINESS_NAME,
                t.STATUS AS TRADER_STATUS
         FROM SYSTEM_USER su
         JOIN TRADER t ON t.USER_ID = su.USER_ID
         WHERE su.USER_ID = :trader_id"
    );

    if (!$stmt) {
        return false;
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        oci_free_statement($stmt);
        return false;
    }

    $trader = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$trader) {
        return false;
    }

    $user_status = strtoupper(trim((string)($trader['USER_STATUS'] ?? 'ACTIVE')));
    $trader_status = strtoupper(trim((string)($trader['TRADER_STATUS'] ?? 'ACTIVE')));
    if ($user_status === '') {
        $user_status = 'ACTIVE';
    }
    if ($trader_status === '') {
        $trader_status = 'ACTIVE';
    }
    if (!in_array($user_status, ['ACTIVE', 'APPROVED'], true) || !in_array($trader_status, ['ACTIVE', 'APPROVED'], true)) {
        return false;
    }

    $shop_name = trim((string)($trader['BUSINESS_NAME'] ?? ''));
    if ($shop_name === '') {
        $owner_name = trim((string)($trader['NAME'] ?? 'Trader'));
        $shop_name = substr($owner_name . ' Shop', 0, 100);
    }

    $location = trim((string)($trader['ADDRESS'] ?? ''));
    $contact_no = trim((string)($trader['PHONE_NO'] ?? ''));
    $location_value = $location !== '' ? $location : null;
    $contact_value = $contact_no !== '' ? $contact_no : null;

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $shop_id = trader_next_shop_id($conn);
        if ($shop_id === null) {
            return false;
        }

        $insert = oci_parse(
            $conn,
            "INSERT INTO SHOP (SHOP_ID, SHOP_NAME, LOCATION, CONTACT_NO, TRADER_ID)
             VALUES (:shop_id, :shop_name, :location, :contact_no, :trader_id)"
        );

        if (!$insert) {
            return false;
        }

        oci_bind_by_name($insert, ':shop_id', $shop_id);
        oci_bind_by_name($insert, ':shop_name', $shop_name);
        oci_bind_by_name($insert, ':location', $location_value);
        oci_bind_by_name($insert, ':contact_no', $contact_value);
        oci_bind_by_name($insert, ':trader_id', $trader_id);

        if (oci_execute($insert, OCI_NO_AUTO_COMMIT)) {
            oci_free_statement($insert);
            return oci_commit($conn);
        }

        $err = oci_error($insert);
        oci_free_statement($insert);

        if ((int)($err['code'] ?? 0) !== 1) {
            error_log('[TRADER CREATE MISSING SHOP] ' . ($err['message'] ?? 'unknown error'));
            oci_rollback($conn);
            return false;
        }
    }

    error_log('[TRADER CREATE MISSING SHOP] Could not allocate a unique shop ID.');
    oci_rollback($conn);
    return false;
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
