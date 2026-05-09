<?php
require_once 'auth_check.php';
require_once 'profile_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

function profile_post(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function profile_count_query($conn, string $sql, array $params): ?int
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return null;
    }

    $binds = [];
    foreach ($params as $name => $value) {
        $binds[$name] = $value;
        oci_bind_by_name($stmt, $name, $binds[$name]);
    }

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return null;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    return (int)($row['CNT'] ?? 0);
}

$data = [
    'name' => profile_post('name'),
    'email' => profile_post('email'),
    'phone' => profile_post('phone'),
    'address' => profile_post('address'),
    'shop_name' => profile_post('shop_name'),
    'shop_location' => profile_post('shop_location'),
    'shop_contact' => profile_post('shop_contact'),
    'shop_description' => profile_post('shop_description'),
];

$errors = [];
$current_profile = trader_fetch_profile($conn, $current_trader_id);

if (!$current_profile || empty($current_profile['SHOP_ID'])) {
    $errors[] = 'No shop is linked to this trader account. Please ask admin to create your shop before editing profile details.';
}

if ($data['name'] === '') {
    $errors[] = 'Owner/full name is required.';
} elseif (strlen($data['name']) > 100) {
    $errors[] = 'Owner/full name must be 100 characters or fewer.';
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
} elseif (strlen($data['email']) > 100) {
    $errors[] = 'Email must be 100 characters or fewer.';
}

if (strlen($data['phone']) > 20) {
    $errors[] = 'Phone number must be 20 characters or fewer.';
}

if (strlen($data['address']) > 200) {
    $errors[] = 'Address must be 200 characters or fewer.';
}

if ($data['shop_name'] === '') {
    $errors[] = 'Shop name is required.';
} elseif (strlen($data['shop_name']) > 100) {
    $errors[] = 'Shop name must be 100 characters or fewer.';
}

if (strlen($data['shop_location']) > 100) {
    $errors[] = 'Shop location must be 100 characters or fewer.';
}

if (strlen($data['shop_contact']) > 20) {
    $errors[] = 'Shop contact number must be 20 characters or fewer.';
}

if (strlen($data['shop_description']) > 500) {
    $errors[] = 'Shop description must be 500 characters or fewer.';
}

if (empty($errors)) {
    $email_count = profile_count_query(
        $conn,
        'SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email)) AND USER_ID <> :user_id',
        [':email' => $data['email'], ':user_id' => $current_trader_id]
    );

    if ($email_count === null) {
        $errors[] = 'Could not check email uniqueness. Please try again.';
    } elseif ($email_count > 0) {
        $errors[] = 'Another account already uses this email address.';
    }
}

if (empty($errors)) {
    $shop_count = profile_count_query(
        $conn,
        'SELECT COUNT(*) AS CNT FROM SHOP WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(:shop_name)) AND TRADER_ID <> :trader_id',
        [':shop_name' => $data['shop_name'], ':trader_id' => $current_trader_id]
    );

    if ($shop_count === null) {
        $errors[] = 'Could not check shop name uniqueness. Please try again.';
    } elseif ($shop_count > 0) {
        $errors[] = 'Another trader already uses this shop name.';
    }
}

if (!empty($errors)) {
    trader_profile_errors_set($errors);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

$name = $data['name'];
$email = $data['email'];
$phone = $data['phone'] !== '' ? $data['phone'] : null;
$address = $data['address'] !== '' ? $data['address'] : null;
$shop_name = $data['shop_name'];
$shop_location = $data['shop_location'] !== '' ? $data['shop_location'] : null;
$shop_contact = $data['shop_contact'] !== '' ? $data['shop_contact'] : null;
$shop_description = $data['shop_description'] !== '' ? $data['shop_description'] : null;

$stmt = oci_parse(
    $conn,
    'UPDATE SYSTEM_USER
     SET NAME = :name,
         EMAIL = :email,
         PHONE_NO = :phone,
         ADDRESS = :address
     WHERE USER_ID = :user_id'
);

if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PROFILE USER PARSE] ' . ($err['message'] ?? 'unknown error'));
    trader_profile_errors_set(['Could not update account details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

oci_bind_by_name($stmt, ':name', $name);
oci_bind_by_name($stmt, ':email', $email);
oci_bind_by_name($stmt, ':phone', $phone);
oci_bind_by_name($stmt, ':address', $address);
oci_bind_by_name($stmt, ':user_id', $current_trader_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PROFILE USER] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not update account details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}
oci_free_statement($stmt);

$stmt = oci_parse(
    $conn,
    'UPDATE TRADER
     SET BUSINESS_NAME = :business_name
     WHERE USER_ID = :user_id'
);

if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PROFILE TRADER PARSE] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not update trader details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

oci_bind_by_name($stmt, ':business_name', $shop_name);
oci_bind_by_name($stmt, ':user_id', $current_trader_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PROFILE TRADER] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not update trader details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}
oci_free_statement($stmt);

$shop_set = [
    'SHOP_NAME = :shop_name',
    'LOCATION = :shop_location',
    'CONTACT_NO = :shop_contact',
];

if (trader_shop_description_column_exists($conn)) {
    $shop_set[] = 'DESCRIPTION = :shop_description';
}

$stmt = oci_parse(
    $conn,
    'UPDATE SHOP
     SET ' . implode(",\n         ", $shop_set) . '
     WHERE TRADER_ID = :trader_id'
);

if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PROFILE SHOP PARSE] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not update shop details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

oci_bind_by_name($stmt, ':shop_name', $shop_name);
oci_bind_by_name($stmt, ':shop_location', $shop_location);
oci_bind_by_name($stmt, ':shop_contact', $shop_contact);
if (trader_shop_description_column_exists($conn)) {
    oci_bind_by_name($stmt, ':shop_description', $shop_description);
}
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PROFILE SHOP] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not update shop details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}
oci_free_statement($stmt);

if (!oci_commit($conn)) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PROFILE COMMIT] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    trader_profile_errors_set(['Could not finish saving profile. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

$_SESSION['user_name'] = $name;
$_SESSION['email'] = $email;
trader_profile_flash_set('success', 'Profile and shop details updated successfully.');

header('Location: profile.php');
exit;
