<?php
/**
 * logout.php — clear session, send to login.
 * Lives at: CFO/customer/logout.php
 */
include '../db.php';   // starts session

$_SESSION = [];                      // clear all data
session_unset();
session_destroy();

// Also clear the session cookie on the client side
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: login.php');
exit;
?>
