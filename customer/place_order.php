<?php
/**
 * place_order.php - create an order from either the active cart or instant checkout.
 */
include '../db.php';
include 'auth_check.php';

$is_paypal_return = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['paypal'] ?? '') === 'success') {
    $paypal_token = $_GET['token'] ?? '';
    $session_token = $_SESSION['paypal_checkout_token'] ?? '';
    $is_paypal_return = $paypal_token !== ''
        && $session_token !== ''
        && hash_equals($session_token, $paypal_token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$is_paypal_return) {
    header('Location: checkout.php');
    exit;
}

$user_id = (string)(int)$_SESSION['user_id'];

if (empty($_SESSION['selected_slot_id'])) {
    header('Location: collection-slot.php');
    exit;
}
$slot_id = (string)(int)$_SESSION['selected_slot_id'];

if ($is_paypal_return) {
    $method_id = !empty($_SESSION['paypal_method_id']) ? (string)(int)$_SESSION['paypal_method_id'] : null;
    $payment_status = 'Paid';
} else {
    $payment_choice = strtolower(trim((string)($_POST['payment_choice'] ?? '')));

    if ($payment_choice === 'paypal') {
        header('Location: paypal_start.php');
        exit;
    }

    if ($payment_choice !== 'cash') {
        $_SESSION['order_error'] = 'Please choose Cash or PayPal as your payment method.';
        $mode = (($_SESSION['checkout_mode'] ?? 'cart') === 'buy_now') ? 'buy_now' : 'cart';
        header('Location: checkout.php?mode=' . $mode);
        exit;
    }

    $method_id = null;
    $payment_status = 'Pending';
}

$is_buy_now = (($_SESSION['checkout_mode'] ?? 'cart') === 'buy_now') && !empty($_SESSION['buy_now_item']);

function order_error_message(string $fallback, ?array $error = null): string
{
    $raw = $error['message'] ?? '';

    if (strpos($raw, '-20001') !== false) {
        return 'Not enough stock for one or more items. Please update your cart and try again.';
    }
    if (strpos($raw, '-20002') !== false) {
        return 'One or more item quantities are outside the allowed order range.';
    }
    if (strpos($raw, '-20003') !== false) {
        return 'Selected collection slot is now full. Please choose another slot.';
    }
    if (strpos($raw, '-20004') !== false) {
        return 'Selected collection slot must be at least 24 hours away. Please choose another slot.';
    }
    if (strpos($raw, '-20005') !== false) {
        return 'Collection slots are only available on Wednesday, Thursday, and Friday.';
    }
    if (strpos($raw, '-20006') !== false) {
        return 'Collection slots are only available at 10:00-13:00, 13:00-16:00, or 16:00-19:00.';
    }
    if (strpos($raw, '-20103') !== false) {
        return 'One or more products are currently unavailable because the trader account is suspended.';
    }
    if (strpos($raw, 'ORA-02291') !== false && stripos($raw, 'PAYMENT') !== false) {
        return 'Selected payment method is not available. Please choose another method.';
    }

    if ($raw !== '') {
        error_log('[PLACE ORDER] ' . $fallback . ': ' . $raw);
    }

    return $fallback;
}

function redirect_order_failure($conn, string $message): void
{
    oci_rollback($conn);
    $_SESSION['order_error'] = $message;
    $mode = (($_SESSION['checkout_mode'] ?? 'cart') === 'buy_now') ? 'buy_now' : 'cart';
    header('Location: checkout.php?mode=' . $mode);
    exit;
}

function execute_or_fail($conn, $stmt, string $message): void
{
    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $error = oci_error($stmt);
        oci_free_statement($stmt);
        redirect_order_failure($conn, order_error_message($message, $error));
    }
}

// Lock the chosen slot so trigger capacity checks cannot race another checkout.
$slot_time_expr = "REPLACE(REPLACE(COLLECTION_TIME, ' ', ''), ':00', '')";
$stmt = oci_parse($conn, "SELECT SLOT_ID
                          FROM COLLECTION_SLOT
                          WHERE SLOT_ID = :p_sid
                            AND COLLECTION_DATE >= SYSDATE + 1
                            AND TO_CHAR(COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
                            AND {$slot_time_expr} IN ('10-13','13-16','16-19')
                          FOR UPDATE");
oci_bind_by_name($stmt, ':p_sid', $slot_id);
execute_or_fail($conn, $stmt, 'Could not verify the selected collection slot.');
$slot = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$slot) {
    unset($_SESSION['selected_slot_id']);
    redirect_order_failure($conn, 'Selected collection slot is no longer available. Please choose a Wednesday, Thursday, or Friday slot at 10:00-13:00, 13:00-16:00, or 16:00-19:00.');
}

$cart_items = [];
$total_amount = 0.0;

if ($is_buy_now) {
    $buy_now = $_SESSION['buy_now_item'];
    $product_id = (string)(int)$buy_now['product_id'];
    $quantity = (int)$buy_now['quantity'];

    $stmt = oci_parse($conn, "SELECT PRODUCT_ID, PRICE, STOCK_QUANTITY, MIN_ORDER, MAX_ORDER
                              FROM PRODUCT
                              WHERE PRODUCT_ID = :p_pid
                              FOR UPDATE");
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    execute_or_fail($conn, $stmt, 'Could not reserve product stock for checkout.');
    $product = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$product) {
        redirect_order_failure($conn, 'Product is no longer available.');
    }

    $stock = (int)$product['STOCK_QUANTITY'];
    $min_order = max(1, (int)($product['MIN_ORDER'] ?? 1));
    $max_order = (int)($product['MAX_ORDER'] ?? 99);

    if ($quantity > $stock) {
        redirect_order_failure($conn, 'Not enough stock for this product. Please choose a lower quantity.');
    }
    if ($quantity < $min_order || $quantity > $max_order) {
        redirect_order_failure($conn, 'Quantity is outside the allowed order range.');
    }

    $price = (float)$product['PRICE'];
    $cart_items[] = [
        'PRODUCT_ID' => $product['PRODUCT_ID'],
        'QUANTITY' => $quantity,
        'PRICE' => $product['PRICE'],
    ];
    $total_amount += $price * $quantity;
} else {
    // Lock the user's active cart row(s) so cart contents stay stable while ordering.
    $stmt = oci_parse($conn, "SELECT CART_ID FROM CART WHERE CUSTOMER_ID = :p_uid AND STATUS = 'Active' FOR UPDATE");
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    execute_or_fail($conn, $stmt, 'Could not lock your cart for checkout.');

    $active_cart_ids = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $active_cart_ids[] = (int)$row['CART_ID'];
    }
    oci_free_statement($stmt);

    if (empty($active_cart_ids)) {
        redirect_order_failure($conn, 'Your cart is empty.');
    }

    // Fetch cart items after locking the cart. Triggers will enforce stock/min/max.
    $stmt = oci_parse($conn, "SELECT ci.PRODUCT_ID, ci.QUANTITY, ci.PRICE
                              FROM CART_ITEM ci
                              JOIN CART c ON ci.CART_ID = c.CART_ID
                              WHERE c.CUSTOMER_ID = :p_uid
                                AND c.STATUS = 'Active'
                              ORDER BY ci.CART_ITEM_ID");
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    execute_or_fail($conn, $stmt, 'Could not fetch your cart items.');

    while ($row = oci_fetch_assoc($stmt)) {
        $quantity = (int)$row['QUANTITY'];
        $price = (float)$row['PRICE'];

        $cart_items[] = $row;
        $total_amount += $price * $quantity;
    }
    oci_free_statement($stmt);
}

if (empty($cart_items)) {
    redirect_order_failure($conn, $is_buy_now ? 'No instant checkout item was selected.' : 'Your cart is empty.');
}

$total_amount = number_format($total_amount, 2, '.', '');

if (!$is_buy_now) {
    // Lock product rows in a consistent order before ORDER_ITEM triggers read/reduce stock.
    $stmt = oci_parse($conn, "SELECT p.PRODUCT_ID
                              FROM PRODUCT p
                              WHERE p.PRODUCT_ID IN (
                                  SELECT ci.PRODUCT_ID
                                  FROM CART_ITEM ci
                                  JOIN CART c ON ci.CART_ID = c.CART_ID
                                  WHERE c.CUSTOMER_ID = :p_uid
                                    AND c.STATUS = 'Active'
                              )
                              ORDER BY p.PRODUCT_ID
                              FOR UPDATE");
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    execute_or_fail($conn, $stmt, 'Could not reserve product stock for checkout.');
    while (oci_fetch_assoc($stmt)) {
        // Fetch every locked product row before order item triggers read/update stock.
    }
    oci_free_statement($stmt);
}

if ($method_id !== null) {
    $stmt = oci_parse($conn, 'SELECT METHOD_ID FROM PAYMENT_METHOD WHERE METHOD_ID = :p_mid');
    oci_bind_by_name($stmt, ':p_mid', $method_id);
    execute_or_fail($conn, $stmt, 'Could not verify the selected payment method.');
    $method = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$method) {
        redirect_order_failure($conn, 'Selected payment method is not available. Please choose another method.');
    }
}

if (!$is_paypal_return) {
    $stmt = oci_parse($conn, "SELECT METHOD_ID
                              FROM PAYMENT_METHOD
                              WHERE UPPER(TRIM(METHOD_NAME)) = 'CASH'
                                AND ROWNUM = 1");
    execute_or_fail($conn, $stmt, 'Could not verify the cash payment method.');
    $cash_method = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if ($cash_method) {
        $method_id = (string)(int)$cash_method['METHOD_ID'];
    }
}

// Create the order and return its generated id from Oracle.
$order_id = null;
$stmt = oci_parse($conn, "INSERT INTO ORDERS (ORDER_ID, CUSTOMER_ID, SLOT_ID, TOTAL_AMOUNT, ORDER_DATE, STATUS)
                          VALUES (ORDER_SEQ.NEXTVAL, :p_uid, :p_sid, :p_total, SYSDATE, 'Paid')
                          RETURNING ORDER_ID INTO :p_oid");
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_bind_by_name($stmt, ':p_sid', $slot_id);
oci_bind_by_name($stmt, ':p_total', $total_amount);
oci_bind_by_name($stmt, ':p_oid', $order_id, 40);
execute_or_fail($conn, $stmt, 'Could not create your order.');
oci_free_statement($stmt);

if (!$order_id) {
    redirect_order_failure($conn, 'Could not create your order.');
}
$order_id = (string)(int)$order_id;

// ORDER_ITEM has no sequence in the supplied schema, so lock the table while assigning ids.
$stmt = oci_parse($conn, 'LOCK TABLE ORDER_ITEM IN EXCLUSIVE MODE');
execute_or_fail($conn, $stmt, 'Could not prepare order items.');
oci_free_statement($stmt);

$stmt = oci_parse($conn, 'SELECT NVL(MAX(ORDER_ITEM_ID), 0) AS MAX_ID FROM ORDER_ITEM');
execute_or_fail($conn, $stmt, 'Could not prepare order item ids.');
$row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);
$next_order_item_id = (int)$row['MAX_ID'] + 1;

foreach ($cart_items as $item) {
    $order_item_id = (string)$next_order_item_id++;
    $product_id = (string)(int)$item['PRODUCT_ID'];
    $quantity = (string)(int)$item['QUANTITY'];
    $price = number_format((float)$item['PRICE'], 2, '.', '');

    $stmt = oci_parse($conn, "INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, PRICE)
                              VALUES (:p_iid, :p_oid, :p_pid, :p_qty, :p_price)");
    oci_bind_by_name($stmt, ':p_iid', $order_item_id);
    oci_bind_by_name($stmt, ':p_oid', $order_id);
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    oci_bind_by_name($stmt, ':p_qty', $quantity);
    oci_bind_by_name($stmt, ':p_price', $price);
    execute_or_fail($conn, $stmt, 'Could not add one or more items to your order.');
    oci_free_statement($stmt);
}

if ($method_id !== null) {
    $stmt = oci_parse($conn, "INSERT INTO PAYMENT (PAYMENT_ID, AMOUNT, PAYMENT_STATUS, ORDER_ID, METHOD_ID, USER_ID)
                              VALUES (PAYMENT_SEQ.NEXTVAL, :p_amount, :p_status, :p_oid, :p_mid, :p_uid)");
    oci_bind_by_name($stmt, ':p_amount', $total_amount);
    oci_bind_by_name($stmt, ':p_status', $payment_status);
    oci_bind_by_name($stmt, ':p_oid', $order_id);
    oci_bind_by_name($stmt, ':p_mid', $method_id);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
} else {
    $stmt = oci_parse($conn, "INSERT INTO PAYMENT (PAYMENT_ID, AMOUNT, PAYMENT_STATUS, ORDER_ID, USER_ID)
                              VALUES (PAYMENT_SEQ.NEXTVAL, :p_amount, :p_status, :p_oid, :p_uid)");
    oci_bind_by_name($stmt, ':p_amount', $total_amount);
    oci_bind_by_name($stmt, ':p_status', $payment_status);
    oci_bind_by_name($stmt, ':p_oid', $order_id);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
}
execute_or_fail($conn, $stmt, 'Could not record payment for your order.');
oci_free_statement($stmt);

if (!$is_buy_now) {
    $stmt = oci_parse($conn, "UPDATE CART
                              SET STATUS = 'Closed'
                              WHERE CUSTOMER_ID = :p_uid
                                AND STATUS = 'Active'");
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    execute_or_fail($conn, $stmt, 'Could not close your cart.');
    oci_free_statement($stmt);
}

if (!oci_commit($conn)) {
    $error = oci_error($conn);
    redirect_order_failure($conn, order_error_message('Could not complete your order. Please try again.', $error));
}

unset($_SESSION['selected_slot_id']);
unset($_SESSION['buy_now_item'], $_SESSION['checkout_mode']);
unset($_SESSION['paypal_checkout_token'], $_SESSION['paypal_method_id'], $_SESSION['paypal_paid'], $_SESSION['paypal_txn_id']);
header('Location: invoice.php?order_id=' . rawurlencode($order_id));
exit;
