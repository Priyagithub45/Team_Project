<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';
require_once 'profile_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}
csrf_require_post('trader_profile');

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

function save_shop_image_upload(array $file, int $shop_id): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Could not read the uploaded shop image. Please try again.'];
    }
    $max_bytes = 2 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max_bytes) {
        return ['ok' => false, 'error' => 'Shop image must be 2MB or smaller.'];
    }
    $tmp_name = $file['tmp_name'] ?? '';
    $mime_type = $tmp_name !== '' ? mime_content_type($tmp_name) : '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime_type])) {
        return ['ok' => false, 'error' => 'Shop image must be JPG, PNG, or WEBP.'];
    }
    $upload_dir = dirname(__DIR__) . '/uploads/shops';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        error_log('[IMAGE UPLOAD] Could not create shop upload directory: ' . $upload_dir);
        return ['ok' => false, 'error' => 'Could not create shop image folder.'];
    }
    $extension = $extensions[$mime_type];
    $relative_path = 'uploads/shops/shop_' . $shop_id . '_' . time() . '.' . $extension;
    $target_path = dirname(__DIR__) . '/' . $relative_path;
    if (!move_uploaded_file($tmp_name, $target_path)) {
        error_log('[IMAGE UPLOAD] move_uploaded_file failed: shop_id=' . $shop_id . ', target=' . $target_path);
        return ['ok' => false, 'error' => 'Could not save shop image.'];
    }
    return ['ok' => true, 'path' => $relative_path];
}

function cleanup_uploaded_shop_image(array $upload): void
{
    if (!empty($upload['path'])) {
        @unlink(dirname(__DIR__) . '/' . $upload['path']);
    }
}

$save_section = profile_post('save_section') ?: 'all';

// ── Personal-only save ──────────────────────────────────────────────────────
if ($save_section === 'personal') {
    $data = [
        '_section' => 'personal',
        'name'     => profile_post('name'),
        'email'    => profile_post('email'),
        'phone'    => profile_post('phone'),
        'address'  => profile_post('address'),
    ];
    $errors = ['_section' => 'personal'];

    if ($data['name'] === '') {
        $errors['name'] = 'Owner/full name is required.';
    } elseif (strlen($data['name']) > 100) {
        $errors['name'] = 'Owner/full name must be 100 characters or fewer.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (strlen($data['email']) > 100) {
        $errors['email'] = 'Email must be 100 characters or fewer.';
    }

    if (strlen($data['phone']) > 20) {
        $errors['phone'] = 'Phone number must be 20 characters or fewer.';
    }

    if (strlen($data['address']) > 200) {
        $errors['address'] = 'Address must be 200 characters or fewer.';
    }

    if (count($errors) === 1) { // only _section key
        $email_count = profile_count_query(
            $conn,
            'SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email)) AND USER_ID <> :user_id',
            [':email' => $data['email'], ':user_id' => $current_trader_id]
        );
        if ($email_count === null) {
            $errors['email'] = 'Could not check email uniqueness. Please try again.';
        } elseif ($email_count > 0) {
            $errors['email'] = 'Another account already uses this email address.';
        }
    }

    if (count($errors) > 1) {
        trader_profile_errors_set($errors);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }

    $name    = $data['name'];
    $email   = $data['email'];
    $phone   = $data['phone']   !== '' ? $data['phone']   : null;
    $address = $data['address'] !== '' ? $data['address'] : null;

    $stmt = oci_parse(
        $conn,
        'UPDATE SYSTEM_USER SET NAME = :name, EMAIL = :email, PHONE_NO = :phone, ADDRESS = :address WHERE USER_ID = :user_id'
    );
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER SAVE PERSONAL PARSE] ' . ($err['message'] ?? 'unknown'));
        trader_profile_errors_set(['_section' => 'personal', '_general' => 'Could not update personal details. Please try again.']);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }
    oci_bind_by_name($stmt, ':name',    $name);
    oci_bind_by_name($stmt, ':email',   $email);
    oci_bind_by_name($stmt, ':phone',   $phone);
    oci_bind_by_name($stmt, ':address', $address);
    oci_bind_by_name($stmt, ':user_id', $current_trader_id);
    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[TRADER SAVE PERSONAL] ' . ($err['message'] ?? 'unknown'));
        oci_rollback($conn);
        trader_profile_errors_set(['_section' => 'personal', '_general' => 'Could not update personal details. Please try again.']);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }
    oci_free_statement($stmt);
    oci_commit($conn);
    $_SESSION['user_name'] = $name;
    $_SESSION['email']     = $email;
    trader_profile_flash_set('success', 'Personal details updated successfully.');
    header('Location: profile.php');
    exit;
}

// ── Shop-only save ──────────────────────────────────────────────────────────
if ($save_section === 'shop') {
    $raw_shop_id = profile_post('shop_id');
    $shop_id     = filter_var($raw_shop_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$shop_id) {
        trader_profile_flash_set('error', 'Invalid shop selected.');
        header('Location: profile.php');
        exit;
    }

    $current_profile = trader_fetch_profile($conn, $current_trader_id, (int)$shop_id);
    if (!$current_profile || empty($current_profile['SHOP_ID'])) {
        trader_profile_flash_set('error', 'Shop not found or access denied.');
        header('Location: profile.php');
        exit;
    }

    $data = [
        '_section'         => 'shop',
        'shop_id'          => (int)$shop_id,
        'shop_name'        => profile_post('shop_name'),
        'shop_location'    => profile_post('shop_location'),
        'shop_contact'     => profile_post('shop_contact'),
        'shop_description' => profile_post('shop_description'),
    ];
    $errors = ['_section' => 'shop', '_shop_id' => (int)$shop_id];

    $shop_image_upload = ['ok' => true, 'path' => null];
    if (isset($_FILES['shop_image']) && ($_FILES['shop_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (!trader_shop_image_column_exists($conn)) {
            $errors[] = 'Shop image uploads are not enabled yet. Please run migration 009_add_shop_image_path.sql.';
        } else {
            $shop_image_upload = save_shop_image_upload($_FILES['shop_image'], (int)$shop_id);
            if (!$shop_image_upload['ok']) {
                $errors['shop_image'] = $shop_image_upload['error'];
            }
        }
    }

    if ($data['shop_name'] === '') {
        $errors['shop_name'] = 'Shop name is required.';
    } elseif (strlen($data['shop_name']) > 100) {
        $errors['shop_name'] = 'Shop name must be 100 characters or fewer.';
    }

    if (strlen($data['shop_location']) > 100) {
        $errors['shop_location'] = 'Shop location must be 100 characters or fewer.';
    }

    if (strlen($data['shop_contact']) > 20) {
        $errors['shop_contact'] = 'Shop contact number must be 20 characters or fewer.';
    }

    if (strlen($data['shop_description']) > 500) {
        $errors['shop_description'] = 'Shop description must be 500 characters or fewer.';
    }

    if (count($errors) === 2) { // only _section + _shop_id
        $shop_count = profile_count_query(
            $conn,
            'SELECT COUNT(*) AS CNT FROM SHOP WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(:shop_name)) AND SHOP_ID <> :shop_id',
            [':shop_name' => $data['shop_name'], ':shop_id' => $shop_id]
        );
        if ($shop_count === null) {
            $errors['shop_name'] = 'Could not check shop name uniqueness. Please try again.';
        } elseif ($shop_count > 0) {
            $errors['shop_name'] = 'Another trader already uses this shop name.';
        }
    }

    if (count($errors) > 2) {
        cleanup_uploaded_shop_image($shop_image_upload);
        trader_profile_errors_set($errors);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }

    $shop_name        = $data['shop_name'];
    $shop_location    = $data['shop_location']    !== '' ? $data['shop_location']    : null;
    $shop_contact     = $data['shop_contact']     !== '' ? $data['shop_contact']     : null;
    $shop_description = $data['shop_description'] !== '' ? $data['shop_description'] : null;
    $shop_image_path  = (string)($shop_image_upload['path'] ?? '');

    $shop_set = ['SHOP_NAME = :shop_name', 'LOCATION = :shop_location', 'CONTACT_NO = :shop_contact'];
    if (trader_shop_description_column_exists($conn)) {
        $shop_set[] = 'DESCRIPTION = :shop_description';
    }
    if (!empty($shop_image_upload['path'])) {
        $shop_set[] = 'IMAGE_PATH = :shop_image_path';
    }

    $stmt = oci_parse(
        $conn,
        'UPDATE SHOP SET ' . implode(",\n         ", $shop_set) . ' WHERE TRADER_ID = :trader_id AND SHOP_ID = :shop_id'
    );
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER SAVE SHOP PARSE] ' . ($err['message'] ?? 'unknown'));
        cleanup_uploaded_shop_image($shop_image_upload);
        trader_profile_errors_set(['_section' => 'shop', '_shop_id' => (int)$shop_id, '_general' => 'Could not update shop details. Please try again.']);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }
    oci_bind_by_name($stmt, ':shop_name',    $shop_name);
    oci_bind_by_name($stmt, ':shop_location', $shop_location);
    oci_bind_by_name($stmt, ':shop_contact',  $shop_contact);
    if (trader_shop_description_column_exists($conn)) {
        oci_bind_by_name($stmt, ':shop_description', $shop_description);
    }
    if (!empty($shop_image_upload['path'])) {
        oci_bind_by_name($stmt, ':shop_image_path', $shop_image_path);
    }
    oci_bind_by_name($stmt, ':trader_id', $current_trader_id);
    oci_bind_by_name($stmt, ':shop_id',   $shop_id);

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $err = oci_error($stmt);
        error_log('[TRADER SAVE SHOP] ' . ($err['message'] ?? 'unknown'));
        oci_rollback($conn);
        cleanup_uploaded_shop_image($shop_image_upload);
        trader_profile_errors_set(['_section' => 'shop', '_shop_id' => (int)$shop_id, '_general' => 'Could not update shop details. Please try again.']);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }
    oci_free_statement($stmt);

    if (!oci_commit($conn)) {
        $err = oci_error($conn);
        error_log('[TRADER SAVE SHOP COMMIT] ' . ($err['message'] ?? 'unknown'));
        oci_rollback($conn);
        cleanup_uploaded_shop_image($shop_image_upload);
        trader_profile_errors_set(['_section' => 'shop', '_shop_id' => (int)$shop_id, '_general' => 'Could not finish saving shop. Please try again.']);
        trader_profile_old_set($data);
        header('Location: profile.php');
        exit;
    }

    trader_profile_flash_set('success', 'Shop "' . $shop_name . '" updated successfully.');
    header('Location: profile.php');
    exit;
}

// ── Password change ─────────────────────────────────────────────────────────
if ($save_section === 'password') {
    $current_pw = (string)($_POST['current_password'] ?? '');
    $new_pw     = (string)($_POST['new_password'] ?? '');
    $confirm_pw = (string)($_POST['confirm_password'] ?? '');

    $errors = ['_section' => 'password'];

    if ($current_pw === '') {
        $errors['current_password'] = 'Current password is required.';
    }
    if ($new_pw === '') {
        $errors['new_password'] = 'New password is required.';
    } elseif (strlen($new_pw) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    }
    if ($confirm_pw === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new_pw !== '' && $new_pw !== $confirm_pw) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (count($errors) === 1) {
        $stmt = oci_parse($conn, 'SELECT PASSWORD FROM SYSTEM_USER WHERE USER_ID = :user_id');
        if (!$stmt) {
            $errors['_general'] = 'Could not verify current password. Please try again.';
        } else {
            oci_bind_by_name($stmt, ':user_id', $current_trader_id);
            if (!oci_execute($stmt)) {
                oci_free_statement($stmt);
                $errors['_general'] = 'Could not verify current password. Please try again.';
            } else {
                $row    = oci_fetch_assoc($stmt);
                oci_free_statement($stmt);
                $stored         = (string)($row['PASSWORD'] ?? '');
                $stored_trimmed = trim($stored);
                $cur_trimmed    = trim($current_pw);

                $pw_ok   = password_verify($current_pw, $stored) || password_verify($cur_trimmed, $stored_trimmed);
                $pw_info = password_get_info($stored_trimmed);
                $is_legacy = ($pw_info['algo'] ?? null) === null || ($pw_info['algoName'] ?? 'unknown') === 'unknown';
                if (!$pw_ok && $is_legacy) {
                    $pw_ok = hash_equals($stored, $current_pw) || hash_equals($stored_trimmed, $cur_trimmed);
                }
                if (!$pw_ok) {
                    $errors['current_password'] = 'Current password is incorrect.';
                }
            }
        }
    }

    if (count($errors) > 1) {
        trader_profile_errors_set($errors);
        header('Location: profile.php');
        exit;
    }

    $new_hash = password_hash($new_pw, PASSWORD_DEFAULT);
    $stmt = oci_parse($conn, 'UPDATE SYSTEM_USER SET PASSWORD = :password WHERE USER_ID = :user_id');
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER CHANGE PASSWORD PARSE] ' . ($err['message'] ?? 'unknown'));
        trader_profile_errors_set(['_section' => 'password', '_general' => 'Could not update password. Please try again.']);
        header('Location: profile.php');
        exit;
    }
    oci_bind_by_name($stmt, ':password', $new_hash);
    oci_bind_by_name($stmt, ':user_id',  $current_trader_id);
    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[TRADER CHANGE PASSWORD] ' . ($err['message'] ?? 'unknown'));
        oci_rollback($conn);
        trader_profile_errors_set(['_section' => 'password', '_general' => 'Could not update password. Please try again.']);
        header('Location: profile.php');
        exit;
    }
    oci_free_statement($stmt);
    oci_commit($conn);
    trader_profile_flash_set('success', 'Password changed successfully.');
    header('Location: profile.php');
    exit;
}

// ── Legacy / all-in-one save (backwards compat) ─────────────────────────────
$data = [
    'name'             => profile_post('name'),
    'email'            => profile_post('email'),
    'phone'            => profile_post('phone'),
    'address'          => profile_post('address'),
    'shop_name'        => profile_post('shop_name'),
    'shop_location'    => profile_post('shop_location'),
    'shop_contact'     => profile_post('shop_contact'),
    'shop_description' => profile_post('shop_description'),
];

$errors = [];
$shop_context    = trader_shop_context($conn, $current_trader_id, false);
$selected_shop_id = $shop_context['selected_shop_id'];
$current_profile = trader_fetch_profile($conn, $current_trader_id, $selected_shop_id);

if (!$current_profile || empty($current_profile['SHOP_ID'])) {
    $errors['_general'] = 'Please select one of your shops before editing profile details.';
}

$shop_image_upload = ['ok' => true, 'path' => null];
if (isset($_FILES['shop_image']) && ($_FILES['shop_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (!trader_shop_image_column_exists($conn)) {
        $errors[] = 'Shop image uploads are not enabled yet. Please run migration 009_add_shop_image_path.sql.';
    } elseif ($current_profile && !empty($current_profile['SHOP_ID'])) {
        $shop_image_upload = save_shop_image_upload($_FILES['shop_image'], (int)$current_profile['SHOP_ID']);
        if (!$shop_image_upload['ok']) {
            $errors['shop_image'] = $shop_image_upload['error'];
        }
    }
}

if ($data['name'] === '') {
    $errors['name'] = 'Owner/full name is required.';
} elseif (strlen($data['name']) > 100) {
    $errors['name'] = 'Owner/full name must be 100 characters or fewer.';
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
} elseif (strlen($data['email']) > 100) {
    $errors['email'] = 'Email must be 100 characters or fewer.';
}

if (strlen($data['phone']) > 20) {
    $errors['phone'] = 'Phone number must be 20 characters or fewer.';
}

if (strlen($data['address']) > 200) {
    $errors['address'] = 'Address must be 200 characters or fewer.';
}

if ($data['shop_name'] === '') {
    $errors['shop_name'] = 'Shop name is required.';
} elseif (strlen($data['shop_name']) > 100) {
    $errors['shop_name'] = 'Shop name must be 100 characters or fewer.';
}

if (strlen($data['shop_location']) > 100) {
    $errors['shop_location'] = 'Shop location must be 100 characters or fewer.';
}

if (strlen($data['shop_contact']) > 20) {
    $errors['shop_contact'] = 'Shop contact number must be 20 characters or fewer.';
}

if (strlen($data['shop_description']) > 500) {
    $errors['shop_description'] = 'Shop description must be 500 characters or fewer.';
}

if (empty($errors)) {
    $email_count = profile_count_query(
        $conn,
        'SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email)) AND USER_ID <> :user_id',
        [':email' => $data['email'], ':user_id' => $current_trader_id]
    );
    if ($email_count === null) {
        $errors['email'] = 'Could not check email uniqueness. Please try again.';
    } elseif ($email_count > 0) {
        $errors['email'] = 'Another account already uses this email address.';
    }
}

if (empty($errors)) {
    $shop_count = profile_count_query(
        $conn,
        'SELECT COUNT(*) AS CNT FROM SHOP WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(:shop_name)) AND SHOP_ID <> :shop_id',
        [':shop_name' => $data['shop_name'], ':shop_id' => $selected_shop_id]
    );
    if ($shop_count === null) {
        $errors['shop_name'] = 'Could not check shop name uniqueness. Please try again.';
    } elseif ($shop_count > 0) {
        $errors['shop_name'] = 'Another trader already uses this shop name.';
    }
}

if (!empty($errors)) {
    cleanup_uploaded_shop_image($shop_image_upload);
    trader_profile_errors_set($errors);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

$name             = $data['name'];
$email            = $data['email'];
$phone            = $data['phone']            !== '' ? $data['phone']            : null;
$address          = $data['address']          !== '' ? $data['address']          : null;
$shop_name        = $data['shop_name'];
$shop_location    = $data['shop_location']    !== '' ? $data['shop_location']    : null;
$shop_contact     = $data['shop_contact']     !== '' ? $data['shop_contact']     : null;
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
    cleanup_uploaded_shop_image($shop_image_upload);
    trader_profile_errors_set(['Could not update account details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

oci_bind_by_name($stmt, ':name',    $name);
oci_bind_by_name($stmt, ':email',   $email);
oci_bind_by_name($stmt, ':phone',   $phone);
oci_bind_by_name($stmt, ':address', $address);
oci_bind_by_name($stmt, ':user_id', $current_trader_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PROFILE USER] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    cleanup_uploaded_shop_image($shop_image_upload);
    trader_profile_errors_set(['Could not update account details. Please try again.']);
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
if (!empty($shop_image_upload['path'])) {
    $shop_set[] = 'IMAGE_PATH = :shop_image_path';
}

$shop_image_path = (string)($shop_image_upload['path'] ?? '');

$stmt = oci_parse(
    $conn,
    'UPDATE SHOP
     SET ' . implode(",\n         ", $shop_set) . '
     WHERE TRADER_ID = :trader_id
       AND SHOP_ID = :shop_id'
);

if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER SAVE PROFILE SHOP PARSE] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    cleanup_uploaded_shop_image($shop_image_upload);
    trader_profile_errors_set(['Could not update shop details. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

oci_bind_by_name($stmt, ':shop_name',    $shop_name);
oci_bind_by_name($stmt, ':shop_location', $shop_location);
oci_bind_by_name($stmt, ':shop_contact',  $shop_contact);
if (trader_shop_description_column_exists($conn)) {
    oci_bind_by_name($stmt, ':shop_description', $shop_description);
}
if (!empty($shop_image_upload['path'])) {
    oci_bind_by_name($stmt, ':shop_image_path', $shop_image_path);
}
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($stmt, ':shop_id',   $selected_shop_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    error_log('[TRADER SAVE PROFILE SHOP] ' . ($err['message'] ?? 'unknown error'));
    oci_rollback($conn);
    cleanup_uploaded_shop_image($shop_image_upload);
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
    cleanup_uploaded_shop_image($shop_image_upload);
    trader_profile_errors_set(['Could not finish saving profile. Please try again.']);
    trader_profile_old_set($data);
    header('Location: profile.php');
    exit;
}

$_SESSION['user_name'] = $name;
$_SESSION['email']     = $email;
trader_profile_flash_set('success', 'Profile and shop details updated successfully.');

header('Location: profile.php');
exit;
