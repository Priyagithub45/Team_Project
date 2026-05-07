<?php
include '../db.php';
include 'auth_check.php';

$token = $_GET['token'] ?? $_POST['custom'] ?? '';
$session_token = $_SESSION['paypal_checkout_token'] ?? '';

if ($token === '' || $session_token === '' || !hash_equals($session_token, $token)) {
    $_SESSION['order_error'] = 'PayPal checkout session could not be verified. Please try again.';
    header('Location: checkout.php');
    exit;
}

$_SESSION['paypal_paid'] = true;
$_SESSION['paypal_txn_id'] = $_POST['txn_id'] ?? $_GET['tx'] ?? '';

header('Location: place_order.php?paypal=success&token=' . rawurlencode($token));
exit;
