<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$generic_error = 'Invalid trader email or password.';

if ($email === '' || $password === '') {
    $_SESSION['trader_login_errors'] = ['Email and password are required.'];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

$sql = "
    SELECT su.USER_ID,
           su.NAME,
           su.EMAIL,
           su.PASSWORD,
           su.STATUS AS USER_STATUS,
           t.USER_ID AS TRADER_USER_ID,
           t.STATUS AS TRADER_STATUS,
           t.BUSINESS_NAME
    FROM SYSTEM_USER su
    LEFT JOIN TRADER t ON t.USER_ID = su.USER_ID
    WHERE UPPER(TRIM(su.EMAIL)) = UPPER(TRIM(:email))
";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $err = oci_error($conn);
    error_log('[TRADER LOGIN PARSE] ' . ($err['message'] ?? 'unknown error'));
    $_SESSION['trader_login_errors'] = ['Trader login is temporarily unavailable. Please try again.'];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

oci_bind_by_name($stmt, ':email', $email);

if (!oci_execute($stmt)) {
    $err = oci_error($stmt);
    error_log('[TRADER LOGIN QUERY] ' . ($err['message'] ?? 'unknown error'));
    $_SESSION['trader_login_errors'] = ['Trader login is temporarily unavailable. Please try again.'];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

$user = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$user || empty($user['TRADER_USER_ID'])) {
    $_SESSION['trader_login_errors'] = [$generic_error];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

$stored_password = (string)($user['PASSWORD'] ?? '');
$stored_password_trimmed = trim($stored_password);
$password_trimmed = trim($password);
$password_ok = password_verify($password, $stored_password) || password_verify($password_trimmed, $stored_password_trimmed);
$password_info = password_get_info($stored_password_trimmed);
$is_legacy_plain_password = ($password_info['algo'] ?? null) === null
    || ($password_info['algoName'] ?? 'unknown') === 'unknown';

if (!$password_ok && $is_legacy_plain_password) {
    $password_ok = hash_equals($stored_password, $password)
        || hash_equals($stored_password_trimmed, $password_trimmed);
}

if (!$password_ok) {
    $_SESSION['trader_login_errors'] = [$generic_error];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

$user_status = strtoupper(trim((string)($user['USER_STATUS'] ?? '')));
$trader_status = strtoupper(trim((string)($user['TRADER_STATUS'] ?? '')));

if ($user_status === '') {
    $user_status = 'ACTIVE';
}
if ($trader_status === '') {
    $trader_status = 'ACTIVE';
}

if (!in_array($user_status, ['ACTIVE', 'APPROVED'], true) || !in_array($trader_status, ['ACTIVE', 'APPROVED'], true)) {
    $_SESSION['trader_login_errors'] = ['Your trader account is not active. Please contact admin.'];
    $_SESSION['trader_login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['USER_ID'];
$_SESSION['user_name'] = $user['NAME'];
$_SESSION['email'] = $user['EMAIL'];
$_SESSION['role'] = 'trader';
$_SESSION['trader_flash_success'] = 'Welcome back, ' . $user['NAME'] . '.';

header('Location: dashboard.php');
exit;
