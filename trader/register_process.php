<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

function post_string(string $key): string
{
    $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
    return trim((string)($value ?? ''));
}

function redirect_with_errors(array $errors, array $old): void
{
    $_SESSION['trader_application_errors'] = $errors;
    $_SESSION['trader_application_old'] = $old;
    header('Location: register.php');
    exit;
}

function count_query($conn, string $sql, array $params, array &$errors, string $error_message): ?int
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER APPLICATION PARSE] ' . ($err['message'] ?? 'unknown error'));
        $errors[] = $error_message;
        return null;
    }

    foreach ($params as $name => $value) {
        oci_bind_by_name($stmt, $name, $params[$name]);
    }

    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[TRADER APPLICATION QUERY] ' . ($err['message'] ?? 'unknown error'));
        $errors[] = $error_message;
        oci_free_statement($stmt);
        return null;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return (int)($row['CNT'] ?? 0);
}

$owner_name = post_string('owner_name');
$email = post_string('email');
$phone = post_string('phone');
$address = post_string('address');
$shop_name = post_string('shop_name');
$category_raw = post_string('category_id');
$business_description = post_string('business_description');
$notes = post_string('notes');

$old = [
    'owner_name' => $owner_name,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'shop_name' => $shop_name,
    'business_description' => $business_description,
    'notes' => $notes,
];

$errors = [];

if ($owner_name === '') {
    $errors[] = 'Owner/full name is required.';
} elseif (strlen($owner_name) > 100) {
    $errors[] = 'Owner/full name must be 100 characters or fewer.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
} elseif (strlen($email) > 100) {
    $errors[] = 'Email must be 100 characters or fewer.';
}

if ($phone !== '' && strlen($phone) > 20) {
    $errors[] = 'Phone number must be 20 characters or fewer.';
}

if ($address === '') {
    $errors[] = 'Address is required.';
} elseif (strlen($address) > 200) {
    $errors[] = 'Address must be 200 characters or fewer.';
}

if ($shop_name === '') {
    $errors[] = 'Proposed shop name is required.';
} elseif (strlen($shop_name) > 100) {
    $errors[] = 'Proposed shop name must be 100 characters or fewer.';
}

$category_id = null;
if ($category_raw !== '') {
    $category_value = filter_var($category_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($category_value === false) {
        $errors[] = 'Please select a valid trader category.';
    } else {
        $category_id = (int)$category_value;
    }
}

if ($business_description === '') {
    $errors[] = 'Business description is required.';
} elseif (strlen($business_description) > 500) {
    $errors[] = 'Business description must be 500 characters or fewer.';
}

if (strlen($notes) > 500) {
    $errors[] = 'Notes must be 500 characters or fewer.';
}

if (!empty($errors)) {
    redirect_with_errors($errors, $old);
}

$account_count = count_query(
    $conn,
    'SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email))',
    [':email' => $email],
    $errors,
    'We could not check existing accounts right now. Please try again.'
);

if ($account_count !== null && $account_count > 0) {
    $errors[] = 'An account already exists with this email address.';
}

if ($category_id !== null) {
    $category_count = count_query(
        $conn,
        'SELECT COUNT(*) AS CNT FROM CATEGORY WHERE CATEGORY_ID = :category_id',
        [':category_id' => $category_id],
        $errors,
        'We could not verify the selected trader category. Please try again.'
    );

    if ($category_count !== null && $category_count === 0) {
        $errors[] = 'Please select a valid trader category.';
    }
}

$shop_count = count_query(
    $conn,
    'SELECT COUNT(*) AS CNT FROM SHOP WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(:shop_name))',
    [':shop_name' => $shop_name],
    $errors,
    'We could not check existing shop names right now. Please try again.'
);

if ($shop_count !== null && $shop_count > 0) {
    $errors[] = 'That shop name is already in use.';
}

$application_email_count = count_query(
    $conn,
    "SELECT COUNT(*) AS CNT
     FROM TRADER_APPLICATION
     WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email))
       AND STATUS IN ('PENDING', 'APPROVED')",
    [':email' => $email],
    $errors,
    'The trader application table is not ready. Please run sql/migrations/005_create_trader_application.sql.'
);

if ($application_email_count !== null && $application_email_count > 0) {
    $errors[] = 'A pending or approved trader application already exists for this email address.';
}

$application_shop_count = count_query(
    $conn,
    "SELECT COUNT(*) AS CNT
     FROM TRADER_APPLICATION
     WHERE UPPER(TRIM(PROPOSED_SHOP_NAME)) = UPPER(TRIM(:shop_name))
       AND STATUS IN ('PENDING', 'APPROVED')",
    [':shop_name' => $shop_name],
    $errors,
    'The trader application table is not ready. Please run sql/migrations/005_create_trader_application.sql.'
);

if ($application_shop_count !== null && $application_shop_count > 0) {
    $errors[] = 'A pending or approved trader application already exists for this shop name.';
}

if (!empty($errors)) {
    redirect_with_errors($errors, $old);
}

$insert_sql = "
    INSERT INTO TRADER_APPLICATION (
        APPLICATION_ID,
        OWNER_NAME,
        EMAIL,
        PHONE_NO,
        ADDRESS,
        PROPOSED_SHOP_NAME,
        CATEGORY_ID,
        BUSINESS_DESCRIPTION,
        NOTES,
        STATUS,
        CREATED_AT
    ) VALUES (
        TRADER_APPLICATION_SEQ.NEXTVAL,
        :owner_name,
        :email,
        :phone,
        :address,
        :shop_name,
        :category_id,
        :business_description,
        :notes,
        'PENDING',
        SYSTIMESTAMP
    )
";

$stmt = oci_parse($conn, $insert_sql);
if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER APPLICATION INSERT PARSE] ' . ($err['message'] ?? 'unknown error'));
    redirect_with_errors(['We could not submit your application right now. Please try again.'], $old);
}

oci_bind_by_name($stmt, ':owner_name', $owner_name);
oci_bind_by_name($stmt, ':email', $email);
oci_bind_by_name($stmt, ':phone', $phone);
oci_bind_by_name($stmt, ':address', $address);
oci_bind_by_name($stmt, ':shop_name', $shop_name);
oci_bind_by_name($stmt, ':category_id', $category_id);
oci_bind_by_name($stmt, ':business_description', $business_description);
oci_bind_by_name($stmt, ':notes', $notes);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    oci_free_statement($stmt);
    error_log('[TRADER APPLICATION INSERT] ' . ($err['message'] ?? 'unknown error'));
    redirect_with_errors(['We could not submit your application right now. Please try again.'], $old);
}

oci_commit($conn);
oci_free_statement($stmt);

$_SESSION['trader_application_success'] = 'Your trader application has been submitted. The admin team will review it before login access is enabled.';
header('Location: register.php');
exit;
