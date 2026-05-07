<?php
/**
 * login_process.php — final clean version
 * Lives at: CFO/customer/login_process.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// ---- Basic check ----
if ($email === '' || $password === '') {
    $_SESSION['login_errors'] = ['Email and password are required.'];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

// ---- Lookup user ----
$sql = "
    SELECT u.user_id, u.name, u.email, u.password, u.status
    FROM system_user u
    WHERE LOWER(u.email) = LOWER(:p_email)
";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':p_email', $email);
oci_execute($stid);
$user = oci_fetch_assoc($stid);
oci_free_statement($stid);

$generic_error = 'Invalid email or password.';

if (!$user) {
    $_SESSION['login_errors'] = [$generic_error];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

// ---- Verify password ----
if (!password_verify($password, $user['PASSWORD'])) {
    $_SESSION['login_errors'] = [$generic_error];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

// ---- Status check ----
if (strtoupper($user['STATUS']) === 'SUSPENDED') {
    $_SESSION['login_errors'] = ['Your account is suspended. Contact support.'];
    header('Location: login.php');
    exit;
}

// ---- Customer role check (use :p_uid, never :uid — UID is Oracle reserved) ----
$stid = oci_parse($conn, "SELECT 1 AS X FROM customer WHERE user_id = :p_uid");
oci_bind_by_name($stid, ':p_uid', $user['USER_ID']);
oci_execute($stid);
$is_customer = oci_fetch_assoc($stid);
oci_free_statement($stid);

if (!$is_customer) {
    $_SESSION['login_errors'] = ['This portal is for customers only.'];
    header('Location: login.php');
    exit;
}

// ---- SUCCESS ----
session_regenerate_id(true);
$_SESSION['user_id'] = $user['USER_ID'];
$_SESSION['user_name'] = $user['NAME'];
$_SESSION['email'] = $user['EMAIL'];
$_SESSION['role'] = 'customer';
$_SESSION['flash_success'] = 'Logged in successfully.';

header('Location: index.php');
exit;
?>
