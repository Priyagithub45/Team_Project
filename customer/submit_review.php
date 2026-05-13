<?php
include '../db.php';
include 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: category.php');
    exit;
}

csrf_require_post('customer_review');

$user_id = (string) (int) $_SESSION['user_id'];
$product_id_raw = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$rating_raw = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = trim($_POST['comment'] ?? '');

$product_id = $product_id_raw && $product_id_raw > 0 ? (string) (int) $product_id_raw : '';
$rating = $rating_raw && $rating_raw >= 1 && $rating_raw <= 5 ? (string) (int) $rating_raw : '';
$back = $product_id !== '' ? 'product.php?id=' . rawurlencode($product_id) : 'category.php';

if ($product_id === '' || $rating === '' || $comment === '') {
    $_SESSION['review_error'] = 'Please choose a rating and write a short review.';
    header('Location: ' . $back);
    exit;
}

if (strlen($comment) > 255) {
    $_SESSION['review_error'] = 'Review must be 255 characters or fewer.';
    header('Location: ' . $back);
    exit;
}

$stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT
                          FROM ORDERS o
                          JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
                          WHERE o.CUSTOMER_ID = :p_uid
                            AND oi.PRODUCT_ID = :p_pid");
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_bind_by_name($stmt, ':p_pid', $product_id);
oci_execute($stmt);
$purchase = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if ((int) ($purchase['CNT'] ?? 0) === 0) {
    $_SESSION['review_error'] = 'You can review this product after buying it.';
    header('Location: ' . $back);
    exit;
}

$stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT
                          FROM REVIEW
                          WHERE CUSTOMER_ID = :p_uid
                            AND PRODUCT_ID = :p_pid");
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_bind_by_name($stmt, ':p_pid', $product_id);
oci_execute($stmt);
$existing = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if ((int) ($existing['CNT'] ?? 0) > 0) {
    $_SESSION['review_error'] = 'You have already reviewed this product.';
    header('Location: ' . $back);
    exit;
}

$stmt = oci_parse($conn, 'LOCK TABLE REVIEW IN EXCLUSIVE MODE');
if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    $_SESSION['review_error'] = 'Could not prepare your review: ' . ($err['message'] ?? 'unknown error');
    header('Location: ' . $back);
    exit;
}
oci_free_statement($stmt);

$stmt = oci_parse($conn, 'SELECT NVL(MAX(REVIEW_ID), 0) + 1 AS NEXT_ID FROM REVIEW');
if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    $_SESSION['review_error'] = 'Could not prepare your review: ' . ($err['message'] ?? 'unknown error');
    header('Location: ' . $back);
    exit;
}
$row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);
$review_id = (string) (int) $row['NEXT_ID'];

$stmt = oci_parse($conn, "INSERT INTO REVIEW
                          (REVIEW_ID, RATING, COMMENT_TEXT, REVIEW_DATE, CUSTOMER_ID, PRODUCT_ID, STATUS, DISPLAY_FLAG)
                          VALUES
                          (:p_rid, :p_rating, :p_comment, SYSDATE, :p_uid, :p_pid, 'APPROVED', 'Y')");
oci_bind_by_name($stmt, ':p_rid', $review_id);
oci_bind_by_name($stmt, ':p_rating', $rating);
oci_bind_by_name($stmt, ':p_comment', $comment);
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_bind_by_name($stmt, ':p_pid', $product_id);

if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
    $err = oci_error($stmt);
    oci_rollback($conn);
    oci_free_statement($stmt);
    $_SESSION['review_error'] = 'Could not save your review: ' . ($err['message'] ?? 'unknown error');
    header('Location: ' . $back);
    exit;
}
oci_free_statement($stmt);

if (!oci_commit($conn)) {
    $err = oci_error($conn);
    oci_rollback($conn);
    $_SESSION['review_error'] = 'Could not save your review: ' . ($err['message'] ?? 'unknown error');
    header('Location: ' . $back);
    exit;
}

$_SESSION['review_success'] = 'Thank you. Your review has been added.';
header('Location: ' . $back);
exit;
