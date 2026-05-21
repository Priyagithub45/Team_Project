<?php
include '../db.php';
require_once '../product_discount_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['login_errors'] = ['Email and password are required.'];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

$sql = "SELECT u.user_id, u.name, u.email, u.password, u.status
        FROM system_user u
        WHERE LOWER(u.email) = LOWER(:p_email)";
$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ':p_email', $email);
if (!oci_execute($stid)) {
    $e = oci_error($stid);
    oci_free_statement($stid);
    error_log('[CUSTOMER LOGIN] ' . ($e['message'] ?? 'unknown'));
    $_SESSION['login_errors'] = ['Login failed. Please try again.'];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}
$user = oci_fetch_assoc($stid);
oci_free_statement($stid);

$generic_error = 'Invalid email or password.';

if (!$user) {
    $_SESSION['login_errors'] = [$generic_error];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

if (!password_verify($password, $user['PASSWORD'])) {
    $_SESSION['login_errors'] = [$generic_error];
    $_SESSION['login_old'] = ['email' => $email];
    header('Location: login.php');
    exit;
}

if (strtoupper($user['STATUS']) === 'SUSPENDED') {
    $_SESSION['login_errors'] = ['Your account is suspended. Contact support.'];
    header('Location: login.php');
    exit;
}

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

// Capture guest cart before regenerating session ID
$guest_cart = $_SESSION['guest_cart'] ?? [];

session_regenerate_id(true);
$_SESSION['user_id']        = $user['USER_ID'];
$_SESSION['user_name']      = $user['NAME'];
$_SESSION['email']          = $user['EMAIL'];
$_SESSION['role']           = 'customer';
$_SESSION['flash_success']  = 'Logged in successfully.';

if (!empty($guest_cart)) {
    $user_id_str = (string)(int)$user['USER_ID'];

    // Find or create active cart
    $stmt = oci_parse($conn, "SELECT CART_ID FROM CART WHERE CUSTOMER_ID = :p_uid AND STATUS = 'Active'");
    oci_bind_by_name($stmt, ':p_uid', $user_id_str);
    oci_execute($stmt);
    $cart_row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if ($cart_row) {
        $cart_id = (string)(int)$cart_row['CART_ID'];
    } else {
        $stmt = oci_parse($conn, 'SELECT CART_SEQ.NEXTVAL AS new_id FROM DUAL');
        oci_execute($stmt);
        $seq     = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        $cart_id = (string)(int)$seq['NEW_ID'];

        $stmt = oci_parse($conn, "INSERT INTO CART (CART_ID, CUSTOMER_ID, STATUS) VALUES (:cid, :p_uid, 'Active')");
        oci_bind_by_name($stmt, ':cid',   $cart_id);
        oci_bind_by_name($stmt, ':p_uid', $user_id_str);
        oci_execute($stmt);
        oci_free_statement($stmt);
    }

    foreach ($guest_cart as $product_id => $qty) {
        $product_id_str = (string)(int)$product_id;
        $qty_int        = (int)$qty;
        if ($qty_int < 1) continue;

        // Fetch product info (skip unavailable products)
        $effective_price_sql = cfo_effective_price_sql('p');
        $stmt = oci_parse($conn, "SELECT {$effective_price_sql} AS PRICE, STOCK_QUANTITY, MAX_ORDER FROM PRODUCT p WHERE PRODUCT_ID = :p_pid");
        oci_bind_by_name($stmt, ':p_pid', $product_id_str);
        if (!oci_execute($stmt)) { oci_free_statement($stmt); continue; }
        $prod = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        if (!$prod || (int)$prod['STOCK_QUANTITY'] < 1) continue;

        $price     = (string)$prod['PRICE'];
        $stock     = (int)$prod['STOCK_QUANTITY'];
        $max_order = (int)($prod['MAX_ORDER'] ?? 0);

        // Check if already in cart
        $stmt = oci_parse($conn, 'SELECT CART_ITEM_ID, QUANTITY FROM CART_ITEM WHERE CART_ID = :cid AND PRODUCT_ID = :pid');
        oci_bind_by_name($stmt, ':cid', $cart_id);
        oci_bind_by_name($stmt, ':pid', $product_id_str);
        if (!oci_execute($stmt)) { oci_free_statement($stmt); continue; }
        $existing = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);

        if ($existing) {
            $new_qty = (int)$existing['QUANTITY'] + $qty_int;
            if ($max_order > 0) { $new_qty = min($new_qty, $max_order); }
            $new_qty_str = (string)min($new_qty, $stock);
            $item_id_str = (string)(int)$existing['CART_ITEM_ID'];

            $stmt = oci_parse($conn, 'UPDATE CART_ITEM SET QUANTITY = :qty, PRICE = :p_price WHERE CART_ITEM_ID = :iid');
            oci_bind_by_name($stmt, ':qty', $new_qty_str);
            oci_bind_by_name($stmt, ':p_price', $price);
            oci_bind_by_name($stmt, ':iid', $item_id_str);
            if (!oci_execute($stmt)) {
                $e = oci_error($stmt);
                error_log('[LOGIN CART MERGE UPDATE] ' . ($e['message'] ?? 'unknown'));
            }
            oci_free_statement($stmt);
        } else {
            $new_qty = $qty_int;
            if ($max_order > 0) { $new_qty = min($new_qty, $max_order); }
            $new_qty_str = (string)min($new_qty, $stock);

            $stmt = oci_parse($conn, 'INSERT INTO CART_ITEM (CART_ITEM_ID, PRODUCT_ID, QUANTITY, CART_ID, PRICE)
                                       VALUES (CART_ITEM_SEQ.NEXTVAL, :pid, :qty, :cid, :p_price)');
            oci_bind_by_name($stmt, ':pid',     $product_id_str);
            oci_bind_by_name($stmt, ':qty',     $new_qty_str);
            oci_bind_by_name($stmt, ':cid',     $cart_id);
            oci_bind_by_name($stmt, ':p_price', $price);
            if (!oci_execute($stmt)) {
                $e = oci_error($stmt);
                error_log('[LOGIN CART MERGE INSERT] product_id=' . $product_id_str . ': ' . ($e['message'] ?? 'unknown'));
            }
            oci_free_statement($stmt);

            $stmt = oci_parse($conn, 'UPDATE CART_ITEM SET PRICE = :p_price WHERE CART_ID = :cid AND PRODUCT_ID = :pid');
            oci_bind_by_name($stmt, ':p_price', $price);
            oci_bind_by_name($stmt, ':cid', $cart_id);
            oci_bind_by_name($stmt, ':pid', $product_id_str);
            if (!oci_execute($stmt)) {
                $e = oci_error($stmt);
                error_log('[LOGIN CART MERGE DISCOUNT PRICE] product_id=' . $product_id_str . ': ' . ($e['message'] ?? 'unknown'));
            }
            oci_free_statement($stmt);
        }
    }

    unset($_SESSION['guest_cart']);
    header('Location: cart.php');
    exit;
}

header('Location: index.php');
exit;
