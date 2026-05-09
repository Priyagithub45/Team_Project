<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset(
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['email'],
    $_SESSION['role']
);

session_regenerate_id(true);
$_SESSION['trader_login_success'] = 'You have been logged out.';

header('Location: login.php');
exit;
