<?php

require_once __DIR__ . '/../csrf.php';

function cfo_wishlist_table_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TABLES
         WHERE TABLE_NAME = 'WISHLIST'"
    );

    if (!$stmt || !oci_execute($stmt)) {
        if ($stmt) {
            oci_free_statement($stmt);
        }
        $exists = false;
        return $exists;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $exists = (int)($row['CNT'] ?? 0) > 0;
    return $exists;
}

function cfo_wishlist_count($conn, int $customer_id): int
{
    if ($customer_id < 1 || !cfo_wishlist_table_exists($conn)) {
        return 0;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM WISHLIST
         WHERE CUSTOMER_ID = :customer_id"
    );
    if (!$stmt) {
        return 0;
    }

    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return 0;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return (int)($row['CNT'] ?? 0);
}

function cfo_wishlist_is_saved($conn, int $customer_id, int $product_id): bool
{
    if ($customer_id < 1 || $product_id < 1 || !cfo_wishlist_table_exists($conn)) {
        return false;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT 1 AS X
         FROM WISHLIST
         WHERE CUSTOMER_ID = :customer_id
           AND PRODUCT_ID = :product_id"
    );
    if (!$stmt) {
        return false;
    }

    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return false;
    }

    $saved = (bool)oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return $saved;
}

function cfo_wishlist_product_ids($conn, int $customer_id, array $product_ids): array
{
    $ids = [];
    foreach ($product_ids as $product_id) {
        $product_id = (int)$product_id;
        if ($product_id > 0) {
            $ids[$product_id] = $product_id;
        }
    }

    if ($customer_id < 1 || empty($ids) || !cfo_wishlist_table_exists($conn)) {
        return [];
    }

    $binds = [];
    $params = [];
    $index = 0;
    foreach ($ids as $id) {
        $name = ':pid' . $index;
        $binds[] = $name;
        $params[$name] = $id;
        $index++;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT PRODUCT_ID
         FROM WISHLIST
         WHERE CUSTOMER_ID = :customer_id
           AND PRODUCT_ID IN (" . implode(',', $binds) . ")"
    );
    if (!$stmt) {
        return [];
    }

    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    foreach ($params as $name => &$value) {
        oci_bind_by_name($stmt, $name, $value);
    }
    unset($value);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return [];
    }

    $saved = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $saved[(int)$row['PRODUCT_ID']] = true;
    }
    oci_free_statement($stmt);

    return $saved;
}

function cfo_wishlist_current_url(string $fallback = 'index.php'): string
{
    $request_uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($request_uri !== '') {
        return $request_uri;
    }

    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? $fallback));
    $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));

    return $script . ($query !== '' ? '?' . $query : '');
}

function cfo_wishlist_safe_return(string $fallback = 'category.php'): string
{
    $return_to = trim((string)($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
    if ($return_to === '') {
        return $fallback;
    }

    $parts = parse_url($return_to);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    if (str_starts_with($return_to, '//') || str_contains($return_to, "\n") || str_contains($return_to, "\r")) {
        return $fallback;
    }

    return $return_to;
}

function cfo_render_wishlist_button(int $product_id, bool $is_saved, string $return_to = '', string $mode = 'icon'): void
{
    $return_to = $return_to !== '' ? $return_to : cfo_wishlist_current_url();
    $button_class = 'wishlist-toggle wishlist-toggle-' . $mode . ($is_saved ? ' is-saved' : '');
    $label = $is_saved ? 'Saved to wishlist' : 'Save to wishlist';
    $icon = $is_saved ? 'favorite' : 'favorite_border';
    ?>
    <form method="post" action="toggle_wishlist.php" class="wishlist-form wishlist-form-<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field('customer_wishlist') ?>
        <input type="hidden" name="product_id" value="<?= htmlspecialchars((string)$product_id, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="wishlist_action" value="toggle">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit"
                class="<?= htmlspecialchars($button_class, ENT_QUOTES, 'UTF-8') ?>"
                data-ajax-wishlist="1"
                aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
                title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
            <span class="material-icons" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="wishlist-label"><?= htmlspecialchars($is_saved ? 'Saved' : 'Save', ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </form>
    <?php
}
