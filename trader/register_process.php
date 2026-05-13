<?php
require_once '../db.php';
require_once '../mail_helpers.php';

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

function table_column_exists($conn, string $table_name, string $column_name): bool
{
    $stmt = oci_parse(
        $conn,
        'SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = UPPER(:table_name)
           AND COLUMN_NAME = UPPER(:column_name)'
    );

    if (!$stmt) {
        return false;
    }

    oci_bind_by_name($stmt, ':table_name', $table_name);
    oci_bind_by_name($stmt, ':column_name', $column_name);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return false;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return (int)($row['CNT'] ?? 0) > 0;
}

$owner_name = post_string('owner_name');
$email = post_string('email');
$phone = post_string('phone');
$license_no = post_string('license_no');
$address = post_string('address');
$shop_name = post_string('shop_name');
$category_raw = post_string('category_id');
$business_description = post_string('business_description');
$notes = post_string('notes');
$email_consent = post_string('email_consent') === '1';

$old = [
    'owner_name' => $owner_name,
    'email' => $email,
    'phone' => $phone,
    'license_no' => $license_no,
    'address' => $address,
    'shop_name' => $shop_name,
    'business_description' => $business_description,
    'notes' => $notes,
    'email_consent' => $email_consent ? '1' : '',
];

$errors = [];

if ($owner_name === '') {
    $errors['owner_name'] = 'Owner/full name is required.';
} elseif (strlen($owner_name) > 100) {
    $errors['owner_name'] = 'Owner/full name must be 100 characters or fewer.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
} elseif (strlen($email) > 100) {
    $errors['email'] = 'Email must be 100 characters or fewer.';
}

if ($phone !== '' && strlen($phone) > 20) {
    $errors['phone'] = 'Phone number must be 20 characters or fewer.';
}

if ($license_no === '') {
    $errors['license_no'] = 'Trading license number is required.';
} elseif (strlen($license_no) > 50) {
    $errors['license_no'] = 'Trading license number must be 50 characters or fewer.';
}

if ($address === '') {
    $errors['address'] = 'Address is required.';
} elseif (strlen($address) > 200) {
    $errors['address'] = 'Address must be 200 characters or fewer.';
}

if ($shop_name === '') {
    $errors['shop_name'] = 'Proposed shop name is required.';
} elseif (strlen($shop_name) > 100) {
    $errors['shop_name'] = 'Proposed shop name must be 100 characters or fewer.';
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

if (!$email_consent) {
    $errors['email_consent'] = 'Please agree to receive transactional emails about your trader application and account.';
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
    $errors['email'] = 'An account already exists with this email address.';
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
    $errors['shop_name'] = 'That shop name is already in use.';
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
    $errors['email'] = 'A pending or approved application already exists for this email address.';
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
    $errors['shop_name'] = 'A pending or approved application already exists for this shop name.';
}

$has_license_column = table_column_exists($conn, 'TRADER_APPLICATION', 'LICENSE_NO');
$has_email_consent_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_CONSENT');
$has_email_consent_at_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_CONSENT_AT');
$has_email_verify_token_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_VERIFY_TOKEN');
$has_email_otp_hash_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_VERIFY_OTP_HASH');
$has_email_otp_expires_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_VERIFY_OTP_EXPIRES_AT');
$has_email_otp_attempts_column = table_column_exists($conn, 'TRADER_APPLICATION', 'EMAIL_VERIFY_OTP_ATTEMPTS');
$email_verify_token = $has_email_verify_token_column ? bin2hex(random_bytes(32)) : '';
$email_otp = ($has_email_otp_hash_column && $has_email_otp_expires_column) ? (string)random_int(100000, 999999) : '';
$email_otp_hash = $email_otp !== '' ? password_hash($email_otp, PASSWORD_DEFAULT) : '';

if (!empty($errors)) {
    redirect_with_errors($errors, $old);
}

$columns = [
    'APPLICATION_ID',
    'OWNER_NAME',
    'EMAIL',
    'PHONE_NO',
    'ADDRESS',
    'PROPOSED_SHOP_NAME',
];
$values = [
    'TRADER_APPLICATION_SEQ.NEXTVAL',
    ':owner_name',
    ':email',
    ':phone',
    ':address',
    ':shop_name',
];

if ($has_license_column) {
    $columns[] = 'LICENSE_NO';
    $values[] = ':license_no';
}

if ($has_email_consent_column) {
    $columns[] = 'EMAIL_CONSENT';
    $values[] = ':email_consent';
}

if ($has_email_consent_at_column) {
    $columns[] = 'EMAIL_CONSENT_AT';
    $values[] = 'SYSTIMESTAMP';
}

if ($has_email_verify_token_column) {
    $columns[] = 'EMAIL_VERIFY_TOKEN';
    $values[] = ':email_verify_token';
}

if ($has_email_otp_hash_column) {
    $columns[] = 'EMAIL_VERIFY_OTP_HASH';
    $values[] = ':email_verify_otp_hash';
}

if ($has_email_otp_expires_column) {
    $columns[] = 'EMAIL_VERIFY_OTP_EXPIRES_AT';
    $values[] = "SYSTIMESTAMP + INTERVAL '15' MINUTE";
}

if ($has_email_otp_attempts_column) {
    $columns[] = 'EMAIL_VERIFY_OTP_ATTEMPTS';
    $values[] = '0';
}

$columns = array_merge($columns, [
    'CATEGORY_ID',
    'BUSINESS_DESCRIPTION',
    'NOTES',
    'STATUS',
    'CREATED_AT',
]);
$values = array_merge($values, [
    ':category_id',
    ':business_description',
    ':notes',
    "'PENDING'",
    'SYSTIMESTAMP',
]);

$insert_sql = 'INSERT INTO TRADER_APPLICATION (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';

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
if ($has_license_column) {
    oci_bind_by_name($stmt, ':license_no', $license_no);
}
if ($has_email_consent_column) {
    $email_consent_value = $email_consent ? 1 : 0;
    oci_bind_by_name($stmt, ':email_consent', $email_consent_value);
}
if ($has_email_verify_token_column) {
    oci_bind_by_name($stmt, ':email_verify_token', $email_verify_token);
}
if ($has_email_otp_hash_column) {
    oci_bind_by_name($stmt, ':email_verify_otp_hash', $email_otp_hash);
}
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

if ($email_otp !== '') {
    try {
        if (!send_trader_application_otp_email($email, $owner_name, $email_otp)) {
            error_log('[TRADER APPLICATION] OTP email was not sent to ' . $email);
        }
    } catch (Throwable $e) {
        error_log('[TRADER APPLICATION] OTP email error for ' . $email . ': ' . $e->getMessage());
    }
} elseif ($email_verify_token !== '') {
    try {
        if (!send_trader_application_verification_email($email, $owner_name, $email_verify_token)) {
            error_log('[TRADER APPLICATION] Verification email was not sent to ' . $email);
        }
    } catch (Throwable $e) {
        error_log('[TRADER APPLICATION] Verification email error for ' . $email . ': ' . $e->getMessage());
    }
}

if ($email_otp !== '') {
    $_SESSION['trader_application_success'] = 'Your trader application has been submitted. Enter the OTP sent to your email to authorize this application.';
    header('Location: verify_application_email.php?email=' . rawurlencode($email));
} else {
    $_SESSION['trader_application_success'] = 'Your trader application has been submitted. The admin team will review it before login access is enabled.';
    header('Location: register.php');
}
exit;
