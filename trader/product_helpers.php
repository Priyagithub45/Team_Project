<?php

require_once __DIR__ . '/../product_discount_helpers.php';

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

function trader_discount_tables_available($conn): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TABLES
         WHERE TABLE_NAME IN ('DISCOUNT', 'PRODUCT_DISCOUNT')"
    );

    if (!$stmt || !oci_execute($stmt)) {
        $available = false;
        return $available;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $available = ((int)($row['CNT'] ?? 0) === 2);
    return $available;
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

function trader_owned_shops($conn, int $trader_id): array
{
    $stmt = oci_parse(
        $conn,
        "SELECT SHOP_ID, SHOP_NAME
         FROM SHOP
         WHERE TRADER_ID = :trader_id
         ORDER BY SHOP_NAME"
    );

    if (!$stmt) {
        return [];
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return [];
    }

    $shops = [];
    while ($shop = oci_fetch_assoc($stmt)) {
        $shops[] = $shop;
    }
    oci_free_statement($stmt);

    return $shops;
}

function trader_fetch_owned_shop($conn, int $shop_id, int $trader_id): ?array
{
    $stmt = oci_parse(
        $conn,
        "SELECT SHOP_ID, SHOP_NAME
         FROM SHOP
         WHERE SHOP_ID = :shop_id
           AND TRADER_ID = :trader_id"
    );

    if (!$stmt) {
        return null;
    }

    oci_bind_by_name($stmt, ':shop_id', $shop_id);
    oci_bind_by_name($stmt, ':trader_id', $trader_id);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return null;
    }

    $shop = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    return $shop;
}

function trader_shop_context($conn, int $trader_id, bool $allow_all = true): array
{
    trader_current_shop($conn, $trader_id);
    $shops = trader_owned_shops($conn, $trader_id);
    $owned_by_id = [];

    foreach ($shops as $shop) {
        $owned_by_id[(int)$shop['SHOP_ID']] = $shop;
    }

    $requested = $_POST['shop_id'] ?? $_GET['shop_id'] ?? null;
    if ($requested !== null) {
        $requested = trim((string)$requested);

        if ($allow_all && $requested === 'all') {
            unset($_SESSION['trader_selected_shop_id']);
        } else {
            $requested_id = filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($requested_id !== false && isset($owned_by_id[(int)$requested_id])) {
                $_SESSION['trader_selected_shop_id'] = (int)$requested_id;
            }
        }
    }

    $selected_id = $_SESSION['trader_selected_shop_id'] ?? null;
    if ($selected_id !== null && !isset($owned_by_id[(int)$selected_id])) {
        unset($_SESSION['trader_selected_shop_id']);
        $selected_id = null;
    }

    if (!$allow_all && $selected_id === null && !empty($shops)) {
        $selected_id = (int)$shops[0]['SHOP_ID'];
        $_SESSION['trader_selected_shop_id'] = $selected_id;
    }

    $selected_shop = $selected_id !== null ? ($owned_by_id[(int)$selected_id] ?? null) : null;

    return [
        'allow_all' => $allow_all,
        'shops' => $shops,
        'selected_shop_id' => $selected_shop ? (int)$selected_shop['SHOP_ID'] : null,
        'selected_shop' => $selected_shop,
    ];
}

function trader_shop_filter_sql(array $shop_context, string $shop_alias = 's'): string
{
    return $shop_context['selected_shop_id'] !== null ? "AND {$shop_alias}.SHOP_ID = :selected_shop_id" : '';
}

function trader_bind_shop_filter($stmt, array $shop_context): void
{
    if ($shop_context['selected_shop_id'] !== null) {
        $selected_shop_id = (int)$shop_context['selected_shop_id'];
        oci_bind_by_name($stmt, ':selected_shop_id', $selected_shop_id);
    }
}

function trader_shop_context_label(array $shop_context, string $fallback = 'All shops'): string
{
    if (!empty($shop_context['selected_shop'])) {
        return (string)$shop_context['selected_shop']['SHOP_NAME'];
    }

    return $fallback;
}

function trader_account_label(array $current_trader): string
{
    $business_name = trim((string)($current_trader['BUSINESS_NAME'] ?? ''));
    if ($business_name !== '') {
        return $business_name;
    }

    $name = trim((string)($current_trader['NAME'] ?? ''));
    return $name !== '' ? $name : 'Trader Account';
}

function trader_render_shop_switcher(array $shop_context): void
{
    $action = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php'));
    $current_value = $shop_context['selected_shop_id'] !== null ? (string)$shop_context['selected_shop_id'] : 'all';
    ?>
    <form method="get" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="shop-switcher">
        <?php foreach ($_GET as $key => $value): ?>
            <?php if ($key === 'shop_id' || is_array($value)) { continue; } ?>
            <input type="hidden" name="<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
        <label for="trader_shop_switcher">Shop</label>
        <select id="trader_shop_switcher" name="shop_id" onchange="this.form.submit()">
            <?php if ($shop_context['allow_all']): ?>
                <option value="all"<?= $current_value === 'all' ? ' selected' : '' ?>>All shops</option>
            <?php endif; ?>
            <?php foreach ($shop_context['shops'] as $shop): ?>
                <?php $shop_id = (string)$shop['SHOP_ID']; ?>
                <option value="<?= htmlspecialchars($shop_id, ENT_QUOTES, 'UTF-8') ?>"<?= $current_value === $shop_id ? ' selected' : '' ?>>
                    <?= htmlspecialchars((string)$shop['SHOP_NAME'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
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
        'discount_rate' => trader_clean_string('discount_rate'),
        'status' => strtoupper(trader_clean_string('status') ?: 'ACTIVE'),
    ];

    $errors = [];

    if ($data['product_name'] === '') {
        $errors['product_name'] = 'Product name is required.';
    } elseif (strlen($data['product_name']) > 100) {
        $errors['product_name'] = 'Product name must be 100 characters or fewer.';
    }

    if (strlen($data['description']) > 200) {
        $errors['description'] = 'Description must be 200 characters or fewer.';
    }

    $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price <= 0) {
        $errors['price'] = 'Price must be greater than 0.';
    }

    $discount_rate = $data['discount_rate'] === ''
        ? 0.0
        : filter_var($data['discount_rate'], FILTER_VALIDATE_FLOAT);
    if ($discount_rate === false || $discount_rate < 0 || $discount_rate >= 100) {
        $errors['discount_rate'] = 'Discount must be between 0 and 99.99%.';
    }

    $stock = filter_var($data['stock_quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($stock === false) {
        $errors['stock_quantity'] = 'Stock quantity cannot be negative.';
    }

    $category_id = filter_var($data['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($category_id === false) {
        $errors['category_id'] = 'Please select a valid category.';
    }

    $quantity_per_item = trader_optional_number($data['quantity_per_item']);
    $min_order = trader_optional_number($data['min_order']);
    $max_order = trader_optional_number($data['max_order']);

    if ($data['quantity_per_item'] !== '' && $quantity_per_item === null) {
        $errors['quantity_per_item'] = 'Quantity per item must be 0 or greater.';
    }
    if ($data['min_order'] !== '' && $min_order === null) {
        $errors['min_order'] = 'Minimum order must be 0 or greater.';
    }
    if ($data['max_order'] !== '' && $max_order === null) {
        $errors['max_order'] = 'Maximum order must be 0 or greater.';
    }
    if ($min_order !== null && $max_order !== null && $min_order > $max_order) {
        $errors['min_order'] = 'Minimum order cannot be greater than maximum order.';
    }

    if ($data['expiry_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expiry_date'])) {
        $errors['expiry_date'] = 'Expiry date must use YYYY-MM-DD format.';
    }

    if (strlen($data['allergy_info']) > 200) {
        $errors['allergy_info'] = 'Allergy information must be 200 characters or fewer.';
    }

    if (!in_array($data['status'], ['ACTIVE', 'INACTIVE', 'DISCONTINUED'], true)) {
        $errors['status'] = 'Please select a valid product status.';
    }

    $data['price'] = $price === false ? null : round((float)$price, 2);
    $data['discount_rate'] = $discount_rate === false ? 0.0 : round((float)$discount_rate, 2);
    $data['stock_quantity'] = $stock === false ? null : (int)$stock;
    $data['category_id'] = $category_id === false ? null : (int)$category_id;
    $data['quantity_per_item'] = $quantity_per_item;
    $data['min_order'] = $min_order;
    $data['max_order'] = $max_order;

    return [$data, $errors];
}

function trader_apply_product_discount($conn, int $product_id, float $discount_rate, string $product_name): bool
{
    if (!trader_discount_tables_available($conn)) {
        return true;
    }

    $delete = oci_parse($conn, 'DELETE FROM PRODUCT_DISCOUNT WHERE PRODUCT_ID = :product_id');
    if (!$delete) {
        return false;
    }
    oci_bind_by_name($delete, ':product_id', $product_id);
    if (!oci_execute($delete, OCI_NO_AUTO_COMMIT)) {
        oci_free_statement($delete);
        return false;
    }
    oci_free_statement($delete);

    if ($discount_rate <= 0) {
        return true;
    }

    $lock = oci_parse($conn, 'LOCK TABLE DISCOUNT IN EXCLUSIVE MODE');
    if (!$lock || !oci_execute($lock, OCI_NO_AUTO_COMMIT)) {
        if ($lock) {
            oci_free_statement($lock);
        }
        return false;
    }
    oci_free_statement($lock);

    $seq = oci_parse($conn, 'SELECT NVL(MAX(DISCOUNT_ID), 0) + 1 AS DISCOUNT_ID FROM DISCOUNT');
    if (!$seq || !oci_execute($seq, OCI_NO_AUTO_COMMIT)) {
        if ($seq) {
            oci_free_statement($seq);
        }
        return false;
    }
    $row = oci_fetch_assoc($seq);
    oci_free_statement($seq);

    $discount_id = (int)($row['DISCOUNT_ID'] ?? 0);
    if ($discount_id < 1) {
        return false;
    }

    $discount_name = substr(trim($product_name) . ' trader discount', 0, 100);
    $insert_discount = oci_parse(
        $conn,
        "INSERT INTO DISCOUNT (DISCOUNT_ID, DISCOUNT_NAME, DISCOUNT_RATE, START_DATE, END_DATE)
         VALUES (:discount_id, :discount_name, :discount_rate, SYSDATE, NULL)"
    );
    if (!$insert_discount) {
        return false;
    }
    oci_bind_by_name($insert_discount, ':discount_id', $discount_id);
    oci_bind_by_name($insert_discount, ':discount_name', $discount_name);
    oci_bind_by_name($insert_discount, ':discount_rate', $discount_rate);
    if (!oci_execute($insert_discount, OCI_NO_AUTO_COMMIT)) {
        oci_free_statement($insert_discount);
        return false;
    }
    oci_free_statement($insert_discount);

    $link = oci_parse(
        $conn,
        'INSERT INTO PRODUCT_DISCOUNT (PRODUCT_ID, DISCOUNT_ID) VALUES (:product_id, :discount_id)'
    );
    if (!$link) {
        return false;
    }
    oci_bind_by_name($link, ':product_id', $product_id);
    oci_bind_by_name($link, ':discount_id', $discount_id);
    if (!oci_execute($link, OCI_NO_AUTO_COMMIT)) {
        oci_free_statement($link);
        return false;
    }
    oci_free_statement($link);

    return true;
}

function trader_fetch_owned_product($conn, int $product_id, int $trader_id): ?array
{
    $status_select = trader_product_status_column_exists($conn) ? ", NVL(p.STATUS, 'ACTIVE') AS STATUS" : ", 'ACTIVE' AS STATUS";
    $image_select = trader_product_image_column_exists($conn) ? ", p.IMAGE_PATH" : ", NULL AS IMAGE_PATH";
    $discount_select = trader_discount_tables_available($conn) ? cfo_discount_select_sql('p') : ", 0 AS DISCOUNT_RATE, p.PRICE AS DISCOUNTED_PRICE";
    $sql = "
        SELECT p.PRODUCT_ID,
               p.PRODUCT_NAME,
               p.DESCRIPTION,
               p.PRICE
               {$discount_select},
               p.STOCK_QUANTITY,
               TO_CHAR(p.EXPIRY_DATE, 'YYYY-MM-DD') AS EXPIRY_DATE,
               p.CATEGORY_ID,
               p.QUANTITY_PER_ITEM,
               p.MIN_ORDER,
               p.MAX_ORDER,
               p.ALLERGY_INFO,
               p.SHOP_ID,
               s.SHOP_NAME
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
