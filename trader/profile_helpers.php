<?php

function trader_shop_description_column_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'SHOP'
           AND COLUMN_NAME = 'DESCRIPTION'"
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

function trader_shop_image_column_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'SHOP'
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

function trader_profile_flash_set(string $type, string $message): void
{
    $_SESSION['trader_profile_flash'] = ['type' => $type, 'message' => $message];
}

function trader_profile_flash_get(): ?array
{
    $flash = $_SESSION['trader_profile_flash'] ?? null;
    unset($_SESSION['trader_profile_flash']);
    return is_array($flash) ? $flash : null;
}

function trader_profile_errors_set(array $errors): void
{
    $_SESSION['trader_profile_errors'] = $errors;
}

function trader_profile_errors_get(): array
{
    $errors = $_SESSION['trader_profile_errors'] ?? [];
    unset($_SESSION['trader_profile_errors']);
    return is_array($errors) ? $errors : [];
}

function trader_profile_old_set(array $old): void
{
    $_SESSION['trader_profile_old'] = $old;
}

function trader_profile_old_get(): array
{
    $old = $_SESSION['trader_profile_old'] ?? [];
    unset($_SESSION['trader_profile_old']);
    return is_array($old) ? $old : [];
}

function trader_fetch_profile($conn, int $trader_id, ?int $shop_id = null): ?array
{
    $description_select = trader_shop_description_column_exists($conn)
        ? ', s.DESCRIPTION AS SHOP_DESCRIPTION'
        : ", NULL AS SHOP_DESCRIPTION";
    $image_select = trader_shop_image_column_exists($conn)
        ? ', s.IMAGE_PATH AS SHOP_IMAGE_PATH'
        : ", NULL AS SHOP_IMAGE_PATH";

    $sql = "
        SELECT su.USER_ID,
               su.NAME,
               su.EMAIL,
               su.PHONE_NO,
               su.ADDRESS,
               su.STATUS AS USER_STATUS,
               t.BUSINESS_NAME,
               t.STATUS AS TRADER_STATUS,
               s.SHOP_ID,
               s.SHOP_NAME,
               s.LOCATION AS SHOP_LOCATION,
               s.CONTACT_NO AS SHOP_CONTACT_NO
               {$description_select}
               {$image_select}
        FROM SYSTEM_USER su
        JOIN TRADER t ON t.USER_ID = su.USER_ID
        LEFT JOIN SHOP s ON s.TRADER_ID = t.USER_ID
            AND (:shop_id IS NULL OR s.SHOP_ID = :shop_id)
        WHERE su.USER_ID = :trader_id
        ORDER BY s.SHOP_ID
        FETCH FIRST 1 ROW ONLY
    ";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return null;
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    oci_bind_by_name($stmt, ':shop_id', $shop_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return null;
    }

    $profile = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    return $profile;
}

function trader_fetch_all_shop_profiles($conn, int $trader_id): array
{
    $description_select = trader_shop_description_column_exists($conn)
        ? ', s.DESCRIPTION AS SHOP_DESCRIPTION'
        : ", NULL AS SHOP_DESCRIPTION";
    $image_select = trader_shop_image_column_exists($conn)
        ? ', s.IMAGE_PATH AS SHOP_IMAGE_PATH'
        : ", NULL AS SHOP_IMAGE_PATH";

    $sql = "SELECT su.USER_ID,
                   su.NAME,
                   su.EMAIL,
                   su.PHONE_NO,
                   su.ADDRESS,
                   su.STATUS AS USER_STATUS,
                   t.BUSINESS_NAME,
                   t.STATUS AS TRADER_STATUS,
                   s.SHOP_ID,
                   s.SHOP_NAME,
                   s.LOCATION AS SHOP_LOCATION,
                   s.CONTACT_NO AS SHOP_CONTACT_NO
                   {$description_select}
                   {$image_select}
            FROM SYSTEM_USER su
            JOIN TRADER t ON t.USER_ID = su.USER_ID
            LEFT JOIN SHOP s ON s.TRADER_ID = t.USER_ID
            WHERE su.USER_ID = :trader_id
            ORDER BY s.SHOP_ID";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return [];
    }
    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return [];
    }
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }
    oci_free_statement($stmt);
    return $rows;
}
