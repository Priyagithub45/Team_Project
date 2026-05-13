<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../csrf.php';

function trader_auth_redirect(string $message): void
{
    $_SESSION['trader_login_errors'] = [$message];
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'trader') {
    trader_auth_redirect('Please log in to continue.');
}

$current_trader_id = (int)$_SESSION['user_id'];

$auth_sql = "
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
    FROM SYSTEM_USER su
    JOIN TRADER t ON t.USER_ID = su.USER_ID
    LEFT JOIN SHOP s ON s.TRADER_ID = t.USER_ID
    WHERE su.USER_ID = :user_id
";

$auth_stmt = oci_parse($conn, $auth_sql);
if (!$auth_stmt) {
    $err = oci_error($conn);
    error_log('[TRADER AUTH PARSE] ' . ($err['message'] ?? 'unknown error'));
    trader_auth_redirect('We could not verify your trader account. Please log in again.');
}

oci_bind_by_name($auth_stmt, ':user_id', $current_trader_id);

if (!oci_execute($auth_stmt)) {
    $err = oci_error($auth_stmt);
    error_log('[TRADER AUTH CHECK] ' . ($err['message'] ?? 'unknown error'));
    trader_auth_redirect('We could not verify your trader account. Please log in again.');
}

$current_trader = oci_fetch_assoc($auth_stmt);
oci_free_statement($auth_stmt);

if (!$current_trader) {
    session_regenerate_id(true);
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['email'], $_SESSION['role']);
    trader_auth_redirect('This portal is for approved traders only.');
}

$user_status = strtoupper(trim((string)($current_trader['USER_STATUS'] ?? '')));
$trader_status = strtoupper(trim((string)($current_trader['TRADER_STATUS'] ?? '')));

if ($user_status === '') {
    $user_status = 'ACTIVE';
}
if ($trader_status === '') {
    $trader_status = 'ACTIVE';
}

if (!in_array($user_status, ['ACTIVE', 'APPROVED'], true) || !in_array($trader_status, ['ACTIVE', 'APPROVED'], true)) {
    session_regenerate_id(true);
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['email'], $_SESSION['role']);
    trader_auth_redirect('Your trader account is not active. Please contact admin.');
}

$_SESSION['user_name'] = $current_trader['NAME'];
$_SESSION['email'] = $current_trader['EMAIL'];
