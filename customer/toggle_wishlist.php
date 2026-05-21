<?php
include '../db.php';
require_once '../csrf.php';
require_once 'product_image_helper.php';
require_once 'wishlist_helpers.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function wishlist_respond(bool $ok, string $message, string $location, array $extra = []): void
{
    global $is_ajax;

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $ok,
            $ok ? 'message' : 'error' => $message,
        ], $extra));
    } else {
        $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $message;
        header('Location: ' . $location);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$return_to = cfo_wishlist_safe_return('category.php');

if (!csrf_is_valid('customer_wishlist')) {
    wishlist_respond(false, 'Security check failed. Please refresh and try again.', $return_to);
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$product_id) {
    wishlist_respond(false, 'Invalid product selected.', $return_to);
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    $_SESSION['login_errors'] = ['Please log in as a customer to save wishlist items.'];
    wishlist_respond(false, 'Please log in to use your wishlist.', 'login.php', [
        'login_required' => true,
        'login_url' => 'login.php',
    ]);
}

if (!cfo_wishlist_table_exists($conn)) {
    wishlist_respond(false, 'Wishlist is not set up yet. Please run the latest database migration.', $return_to);
}

$active_filter = product_active_filter($conn, 'p');
$stmt = oci_parse($conn, "SELECT p.PRODUCT_ID, p.PRODUCT_NAME FROM PRODUCT p WHERE p.PRODUCT_ID = :product_id {$active_filter}");
oci_bind_by_name($stmt, ':product_id', $product_id);
if (!oci_execute($stmt)) {
    oci_free_statement($stmt);
    wishlist_respond(false, 'Could not check this product.', $return_to);
}
$product = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$product) {
    wishlist_respond(false, 'This product is not available for wishlist.', $return_to);
}

$customer_id = (int)$_SESSION['user_id'];
$action = strtolower(trim((string)($_POST['wishlist_action'] ?? 'toggle')));
$already_saved = cfo_wishlist_is_saved($conn, $customer_id, (int)$product_id);

if ($action === 'remove' || ($action === 'toggle' && $already_saved)) {
    $stmt = oci_parse(
        $conn,
        "DELETE FROM WISHLIST
         WHERE CUSTOMER_ID = :customer_id
           AND PRODUCT_ID = :product_id"
    );
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        wishlist_respond(false, 'Could not remove this wishlist item.', $return_to);
    }
    oci_free_statement($stmt);

    wishlist_respond(true, 'Removed from your wishlist.', $return_to, [
        'wishlisted' => false,
        'wishlist_count' => cfo_wishlist_count($conn, $customer_id),
    ]);
}

if ($already_saved) {
    wishlist_respond(true, 'Already saved in your wishlist.', $return_to, [
        'wishlisted' => true,
        'wishlist_count' => cfo_wishlist_count($conn, $customer_id),
    ]);
}

$stmt = oci_parse(
    $conn,
    "INSERT INTO WISHLIST (WISHLIST_ID, CUSTOMER_ID, PRODUCT_ID, CREATED_AT)
     VALUES (WISHLIST_SEQ.NEXTVAL, :customer_id, :product_id, SYSDATE)"
);
oci_bind_by_name($stmt, ':customer_id', $customer_id);
oci_bind_by_name($stmt, ':product_id', $product_id);

if (!oci_execute($stmt)) {
    $err = oci_error($stmt);
    oci_free_statement($stmt);
    $message = (isset($err['code']) && (int)$err['code'] === 1)
        ? 'Already saved in your wishlist.'
        : 'Could not save this product to your wishlist.';
    wishlist_respond(false, $message, $return_to);
}
oci_free_statement($stmt);

wishlist_respond(true, 'Saved to your wishlist.', $return_to, [
    'wishlisted' => true,
    'wishlist_count' => cfo_wishlist_count($conn, $customer_id),
]);
