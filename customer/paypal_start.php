<?php
/**
 * Builds a PayPal Payments Standard sandbox form from cart or instant checkout.
 */
include '../db.php';
include 'auth_check.php';
include 'paypal_config.php';
require_once 'collection_slot_rules.php';
require_once '../product_discount_helpers.php';

$user_id = (string)(int)$_SESSION['user_id'];

if (empty($_SESSION['selected_slot_id'])) {
    header('Location: collection-slot.php');
    exit;
}
$slot_id = (string)(int)$_SESSION['selected_slot_id'];
$allowed_slot_rules_sql = collection_slot_allowed_sql('cs');

$is_buy_now = (($_SESSION['checkout_mode'] ?? 'cart') === 'buy_now') && !empty($_SESSION['buy_now_item']);
$items = [];
$cart_total = 0.0;

if ($is_buy_now) {
    $buy_now = $_SESSION['buy_now_item'];
    $product_id = (string)(int)$buy_now['product_id'];
    $quantity = (int)$buy_now['quantity'];

    $effective_price_sql = cfo_effective_price_sql('p');
    $stmt = oci_parse($conn, "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, {$effective_price_sql} AS PRICE,
                                     p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER, s.SHOP_NAME
                              FROM PRODUCT p
                              JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                              WHERE p.PRODUCT_ID = :p_pid");
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $_SESSION['order_error'] = 'Could not prepare PayPal checkout: ' . ($e['message'] ?? 'unknown');
        header('Location: checkout.php?mode=buy_now');
        exit;
    }
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$row) {
        $_SESSION['order_error'] = 'Product is no longer available.';
        header('Location: checkout.php?mode=buy_now');
        exit;
    }

    $row['QUANTITY'] = $quantity;
    $items[] = $row;
    $cart_total = ((float)$row['PRICE'] * $quantity);
} else {
    $sql = "SELECT ci.PRODUCT_ID, ci.QUANTITY, ci.PRICE,
                   p.PRODUCT_NAME, p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER,
                   s.SHOP_NAME
            FROM CART_ITEM ci
            JOIN CART c    ON ci.CART_ID    = c.CART_ID
            JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
            JOIN SHOP s    ON p.SHOP_ID     = s.SHOP_ID
            WHERE c.CUSTOMER_ID = :p_uid
              AND c.STATUS = 'Active'
            ORDER BY ci.CART_ITEM_ID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        oci_free_statement($stmt);
        $_SESSION['order_error'] = 'Could not prepare PayPal checkout: ' . ($e['message'] ?? 'unknown');
        header('Location: checkout.php?mode=cart');
        exit;
    }

    while ($row = oci_fetch_assoc($stmt)) {
        $items[] = $row;
        $cart_total += ((float)$row['PRICE'] * (int)$row['QUANTITY']);
    }
    oci_free_statement($stmt);
}

if (empty($items)) {
    header('Location: ' . ($is_buy_now ? 'category.php' : 'cart.php'));
    exit;
}

foreach ($items as $item) {
    $qty = (int)$item['QUANTITY'];
    $stock = (int)$item['STOCK_QUANTITY'];
    $min = max(1, (int)($item['MIN_ORDER'] ?? 1));
    $max = (int)($item['MAX_ORDER'] ?? 99);

    if ($qty > $stock) {
        $_SESSION['order_error'] = $is_buy_now
            ? 'Not enough stock for this product. Please choose a lower quantity.'
            : 'Not enough stock for one or more items. Please update your cart before paying.';
        header('Location: checkout.php?mode=' . ($is_buy_now ? 'buy_now' : 'cart'));
        exit;
    }

    if ($qty < $min || $qty > $max) {
        $_SESSION['order_error'] = $is_buy_now
            ? 'Quantity is outside the allowed order range.'
            : 'One or more item quantities are outside the allowed order range.';
        header('Location: checkout.php?mode=' . ($is_buy_now ? 'buy_now' : 'cart'));
        exit;
    }
}

$stmt = oci_parse($conn, "SELECT cs.SLOT_ID,
                                 (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID) AS USED_COUNT
                          FROM COLLECTION_SLOT cs
                          WHERE cs.SLOT_ID = :p_sid
                            AND {$allowed_slot_rules_sql}");
oci_bind_by_name($stmt, ':p_sid', $slot_id);
oci_execute($stmt);
$slot_usage = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$slot_usage) {
    unset($_SESSION['selected_slot_id']);
    $_SESSION['order_error'] = 'Selected collection slot is no longer available. Please choose a Wednesday, Thursday, or Friday slot at 10:00-13:00, 13:00-16:00, or 16:00-19:00.';
    header('Location: collection-slot.php');
    exit;
}

if ((int)($slot_usage['USED_COUNT'] ?? COLLECTION_SLOT_MAX_ORDERS) >= COLLECTION_SLOT_MAX_ORDERS) {
    $_SESSION['order_error'] = 'Selected collection slot is now full. Please choose another slot.';
    header('Location: checkout.php?mode=' . ($is_buy_now ? 'buy_now' : 'cart'));
    exit;
}

$paypal_method_id = null;
$stmt = oci_parse($conn, "SELECT METHOD_ID
                          FROM PAYMENT_METHOD
                          WHERE LOWER(METHOD_NAME) LIKE '%paypal%'
                            AND ROWNUM = 1");
if (oci_execute($stmt)) {
    $method = oci_fetch_assoc($stmt);
    if ($method) {
        $paypal_method_id = (string)(int)$method['METHOD_ID'];
    }
}
oci_free_statement($stmt);

$token = bin2hex(random_bytes(16));
$_SESSION['paypal_checkout_token'] = $token;
$_SESSION['paypal_method_id'] = $paypal_method_id;
unset($_SESSION['paypal_paid'], $_SESSION['paypal_txn_id']);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path;
$return_url = $base_url . '/place_order.php?paypal=success&token=' . urlencode($token);
$cancel_url = $base_url . '/paypal_cancel.php?token=' . urlencode($token);
$invoice_ref = 'CFO-' . (int)$user_id . '-' . time();

$page_title = 'Redirecting to PayPal - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="form-page" style="padding:40px 0;">
    <div class="container container-narrow" style="max-width:520px;margin:auto;text-align:center;">
        <h2 style="margin-bottom:12px;">PAYPAL CHECKOUT</h2>
        <p style="color:#555;margin-bottom:20px;">You are being redirected to PayPal Sandbox to complete payment.</p>

        <form id="paypal-form" action="<?php echo htmlspecialchars(PAYPAL_CHECKOUT_URL); ?>" method="post">
            <input type="hidden" name="cmd" value="_cart">
            <input type="hidden" name="upload" value="1">
            <input type="hidden" name="business" value="<?php echo htmlspecialchars(PAYPAL_BUSINESS_EMAIL); ?>">
            <input type="hidden" name="currency_code" value="<?php echo htmlspecialchars(PAYPAL_CURRENCY); ?>">
            <input type="hidden" name="charset" value="utf-8">
            <input type="hidden" name="no_shipping" value="1">
            <input type="hidden" name="rm" value="1">
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_url); ?>">
            <input type="hidden" name="cancel_return" value="<?php echo htmlspecialchars($cancel_url); ?>">
            <input type="hidden" name="custom" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="invoice" value="<?php echo htmlspecialchars($invoice_ref); ?>">

            <?php foreach ($items as $index => $item):
                $n = $index + 1;
                $name = $item['SHOP_NAME'] . ' - ' . $item['PRODUCT_NAME'];
            ?>
                <input type="hidden" name="item_name_<?php echo $n; ?>" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="item_number_<?php echo $n; ?>" value="<?php echo (int)$item['PRODUCT_ID']; ?>">
                <input type="hidden" name="amount_<?php echo $n; ?>" value="<?php echo number_format((float)$item['PRICE'], 2, '.', ''); ?>">
                <input type="hidden" name="quantity_<?php echo $n; ?>" value="<?php echo (int)$item['QUANTITY']; ?>">
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary" style="padding:0.8rem 1.5rem;">CONTINUE TO PAYPAL</button>
        </form>

        <p style="margin-top:16px;color:#777;font-size:0.9rem;">
            Total: <?php echo htmlspecialchars(PAYPAL_CURRENCY); ?> <?php echo number_format($cart_total, 2); ?>
        </p>
    </div>
</section>

<script>
document.getElementById('paypal-form').submit();
</script>

<?php include 'footer.php'; ?>
