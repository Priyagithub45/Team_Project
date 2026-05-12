<?php
/**
 * auth_check.php — include this at top of any page that needs login.
 * Usage: include 'auth_check.php';  (after include '../db.php';)
 *
 * If user not logged in, redirects to login.php.
 *
 * Lives at: CFO/customer/auth_check.php
 */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    $guest_cart = $_SESSION['guest_cart'] ?? null;
    session_unset();
    session_destroy();
    session_start();
    if ($guest_cart !== null) {
        $_SESSION['guest_cart'] = $guest_cart;
    }
    $_SESSION['login_errors'] = ['Please log in as a customer to continue.'];
    header('Location: login.php');
    exit;
}
?>
