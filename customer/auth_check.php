<?php
/**
 * auth_check.php — include this at top of any page that needs login.
 * Usage: include 'auth_check.php';  (after include '../db.php';)
 *
 * If user not logged in, redirects to login.php.
 *
 * Lives at: CFO/customer/auth_check.php
 */

if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_errors'] = ['Please log in to continue.'];
    header('Location: login.php');
    exit;
}
?>
