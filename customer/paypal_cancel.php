<?php
include '../db.php';
include 'auth_check.php';

$token = $_GET['token'] ?? '';
$session_token = $_SESSION['paypal_checkout_token'] ?? '';

if ($token !== '' && $session_token !== '' && hash_equals($session_token, $token)) {
    unset($_SESSION['paypal_checkout_token'], $_SESSION['paypal_method_id'], $_SESSION['paypal_paid'], $_SESSION['paypal_txn_id']);
}

$_SESSION['order_error'] = 'PayPal payment was cancelled. Your cart is still available.';
header('Location: checkout.php');
exit;
