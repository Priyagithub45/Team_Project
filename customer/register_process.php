<?php
/**
 * register_process.php
 * Validates the registration form, emails an OTP, and stores the pending
 * registration in the session until verify_otp.php confirms it.
 */

include '../db.php';
require_once '../mail_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$errors = [];
if ($name === '') {
    $errors['name'] = 'Full name is required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}
if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}
if ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match.';
}
if ($address === '') {
    $errors['address'] = 'Home address is required.';
}

if (empty($errors)) {
    $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:e))");
    oci_bind_by_name($stmt, ':e', $email);
    if (oci_execute($stmt)) {
        $row = oci_fetch_assoc($stmt);
        if ((int)$row['CNT'] > 0) {
            $errors['email'] = 'An account already exists with this email address.';
        }
    } else {
        $err = oci_error($stmt);
        error_log('[CUSTOMER REGISTER CHECK] ' . ($err['message'] ?? 'unknown error'));
        $errors['_general'] = 'Could not check for an existing account. Please try again.';
    }
    oci_free_statement($stmt);
}

if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
    ];
    header('Location: register.php');
    exit;
}

$otp = (string)random_int(100000, 999999);
$otp_expires_at = time() + (10 * 60);

$mail_sent = send_customer_registration_otp_email($email, $name, $otp);
$local_preview_saved = false;

if (!$mail_sent && cfo_is_local_request()) {
    $local_preview_saved = cfo_write_local_mail_preview(
        $email,
        'Your Cleckhuddesfax Online Mart verification code',
        "Hello {$name},\n\n"
            . "Your Cleckhuddesfax Online Mart OTP is: {$otp}\n\n"
            . "This code expires in 10 minutes.\n\n"
            . "If you did not request this registration, please ignore this email."
    );
}

if (!$mail_sent && !$local_preview_saved) {
    $_SESSION['register_errors'] = [
        '_general' => 'Could not send the OTP email. Gmail SMTP rejected the message. Please check the XAMPP sendmail log and Gmail app-password setup, then try again.',
    ];
    $_SESSION['register_old'] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
    ];
    header('Location: register.php');
    exit;
}

$_SESSION['pending_registration'] = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'address' => $address,
    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
    'otp_expires_at' => $otp_expires_at,
];
$_SESSION['otp_success'] = $mail_sent
    ? 'We sent a 6-digit OTP to ' . $email . '. Please verify it to finish registration.'
    : 'Email delivery is blocked by the local SMTP setup. For XAMPP testing, the OTP was saved in storage/logs/mail_preview.log.';

header('Location: verify_otp.php');
exit;
