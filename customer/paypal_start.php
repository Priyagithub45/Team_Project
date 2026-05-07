<?php
/**
 * Builds a PayPal Payments Standard sandbox cart form from the active cart.
 */
include '../db.php';
include 'auth_check.php';
include 'paypal_config.php';

$user_id = (string)(int)$_SESSION['user_id'];

if (empty($_SESSION['selected_slot_id'])) {
    header('Location: collection-slot.php');
    exit;
}
$slot_id = (string)(int)$_SESSION['selected_slot_id'];

$sql = "SELECT ci.PRODUCT_ID, ci.QUANTITY, ci.PRICE,
               p.PRODUCT_NAME, p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER,
               s.SHOP_NAME
        FROM CART_ITEM ci
        JOIN CART c    ON ci.CART_ID = c.CART_ID
        JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
        JOIN SHOP s    ON p.SHOP_ID = s.SHOP_ID
        WHERE c.CUSTOMER_ID = :p_uid
          AND c.STATUS = 'Active'
        ORDER BY ci.CART_ITEM_ID";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':p_uid', $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    oci_free_statement($stmt);
    $_SESSION['order_error'] = 'Could not prepare PayPal checkout: ' . ($e['message'] ?? 'unknown');
    header('Location: checkout.php');
    exit;
}

$items = [];
$cart_total = 0.0;
while ($row = oci_fetch_assoc($stmt)) {
    $qty = (int)$row['QUANTITY'];
    $stock = (int)$row['STOCK_QUANTITY'];
    $min = (int)$row['MIN_ORDER'];
    $max = (int)$row['MAX_ORDER'];

    if ($qty > $stock) {
        oci_free_statement($stmt);
        $_SESSION['order_error'] = 'Not enough stock for one or more items. Please update your cart before paying.';
        header('Location: checkout.php');
        exit;
    }

    if ($qty < $min || $qty > $max) {
        oci_free_statement($stmt);
        $_SESSION['order_error'] = 'One or more item quantities are outside the allowed order range.';
        header('Location: checkout.php');
        exit;
    }

    $items[] = $row;
    $cart_total += ((float)$row['PRICE'] * $qty);
}
oci_free_statement($stmt);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

$stmt = oci_parse($conn, "SELECT COUNT(*) AS USED_COUNT
                          FROM ORDERS
                          WHERE SLOT_ID = :p_sid");
oci_bind_by_name($stmt, ':p_sid', $slot_id);
oci_execute($stmt);
$slot_usage = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if ((int)($slot_usage['USED_COUNT'] ?? 20) >= 20) {
    $_SESSION['order_error'] = 'Selected collection slot is now full. Please choose another slot.';
    header('Location: checkout.php');
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
