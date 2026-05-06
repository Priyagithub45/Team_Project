<?php
/**
 * register_process.php — final clean version
 * Lives at: CFO/customer/register_process.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

// ---- READ INPUT ----
$name             = trim($_POST['name'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$phone            = trim($_POST['phone'] ?? '');
$address          = trim($_POST['address'] ?? '');

// ---- VALIDATE ----
$errors = [];
if ($name === '')                                     $errors[] = "Full name is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = "A valid email address is required.";
if (strlen($password) < 8)                            $errors[] = "Password must be at least 8 characters.";
if ($password !== $confirm_password)                  $errors[] = "Passwords do not match.";
if ($address === '')                                  $errors[] = "Home address is required.";

// ---- DUPLICATE EMAIL CHECK ----
if (empty($errors)) {
    $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:e))");
    oci_bind_by_name($stmt, ':e', $email);
    if (oci_execute($stmt)) {
        $r = oci_fetch_assoc($stmt);
        if ((int)$r['CNT'] > 0) $errors[] = "An account with this email already exists.";
    } else {
        $err = oci_error($stmt);
        $errors[] = "DB error: " . $err['message'];
    }
    oci_free_statement($stmt);
}

// ---- BAIL ON ERRORS ----
if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
    header("Location: register.php");
    exit;
}

// ---- INSERT (transaction) ----
$hash = password_hash($password, PASSWORD_BCRYPT);

// Get next user_id
$stmt = oci_parse($conn, "SELECT seq_system_user.NEXTVAL AS N FROM dual");
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
$new_user_id = (int)$row['N'];
oci_free_statement($stmt);

// Insert SYSTEM_USER
$stmt = oci_parse($conn, "
    INSERT INTO SYSTEM_USER (USER_ID, NAME, EMAIL, PASSWORD, PHONE_NO, ADDRESS, STATUS, CREATED_AT)
    VALUES (:u, :n, :e, :p, :ph, :a, 'Active', SYSDATE)
");
oci_bind_by_name($stmt, ':u',  $new_user_id);
oci_bind_by_name($stmt, ':n',  $name);
oci_bind_by_name($stmt, ':e',  $email);
oci_bind_by_name($stmt, ':p',  $hash);
oci_bind_by_name($stmt, ':ph', $phone);
oci_bind_by_name($stmt, ':a',  $address);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    oci_free_statement($stmt);
    $_SESSION['register_errors'] = ["Could not create account: " . $err['message']];
    $_SESSION['register_old'] = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
    header("Location: register.php");
    exit;
}
oci_free_statement($stmt);

// Insert CUSTOMER
$stmt = oci_parse($conn, "INSERT INTO CUSTOMER (USER_ID, LOYALTY_POINTS) VALUES (:u, 0)");
oci_bind_by_name($stmt, ':u', $new_user_id);
if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    oci_free_statement($stmt);
    $_SESSION['register_errors'] = ["Could not create customer profile: " . $err['message']];
    $_SESSION['register_old'] = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];
    header("Location: register.php");
    exit;
}
oci_free_statement($stmt);

// Commit
oci_commit($conn);

// ---- SUCCESS — auto-login + redirect ----
session_regenerate_id(true);
$_SESSION['user_id']    = $new_user_id;
$_SESSION['user_name']  = $name;
$_SESSION['user_email'] = $email;
$_SESSION['user_role']  = 'customer';
$_SESSION['flash_success'] = "Account created. Welcome, $name!";

header("Location: index.php");
exit;
?>
