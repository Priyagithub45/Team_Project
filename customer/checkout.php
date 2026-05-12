<?php
include '../db.php';
include 'auth_check.php';

$user_id = (string)(int)$_SESSION['user_id'];

$requested_mode = $_GET['mode'] ?? '';
if ($requested_mode === 'cart') {
    $_SESSION['checkout_mode'] = 'cart';
    unset($_SESSION['buy_now_item']);
} elseif ($requested_mode === 'buy_now' && !empty($_SESSION['buy_now_item'])) {
    $_SESSION['checkout_mode'] = 'buy_now';
} elseif (empty($_SESSION['checkout_mode'])) {
    $_SESSION['checkout_mode'] = 'cart';
}

$is_buy_now = (($_SESSION['checkout_mode'] ?? 'cart') === 'buy_now') && !empty($_SESSION['buy_now_item']);

// Guard: slot must be selected
if (empty($_SESSION['selected_slot_id'])) {
    header('Location: collection-slot.php');
    exit;
}
$slot_id = (string)(int)$_SESSION['selected_slot_id'];
$slot_time_expr = "REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')";
$allowed_slot_rules_sql = "cs.COLLECTION_DATE >= SYSDATE + 1
                           AND TO_CHAR(cs.COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
                           AND {$slot_time_expr} IN ('10-13','13-16','16-19')
                           AND (20 - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID)) > 0";

// ── Cart items for this user ──────────────────────────────────────────────────
$items      = [];
$cart_total = 0.0;

if ($is_buy_now) {
    $buy_now = $_SESSION['buy_now_item'];
    $product_id = (string)(int)$buy_now['product_id'];
    $quantity = (int)$buy_now['quantity'];

    $stmt = oci_parse($conn, "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE, s.SHOP_NAME
                              FROM PRODUCT p
                              JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                              WHERE p.PRODUCT_ID = :p_pid");
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    oci_execute($stmt);
    $product = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$product) {
        unset($_SESSION['buy_now_item'], $_SESSION['checkout_mode']);
        header('Location: category.php');
        exit;
    }

    $line_total = (float)$product['PRICE'] * $quantity;
    $items[] = [
        'PRODUCT_ID' => $product['PRODUCT_ID'],
        'PRODUCT_NAME' => $product['PRODUCT_NAME'],
        'SHOP_NAME' => $product['SHOP_NAME'],
        'QUANTITY' => $quantity,
        'PRICE' => $product['PRICE'],
        'LINE_TOTAL' => $line_total,
    ];
    $cart_total = $line_total;
} else {
    $sql_cart = "SELECT ci.CART_ITEM_ID, ci.QUANTITY, ci.PRICE,
                        (ci.QUANTITY * ci.PRICE) AS LINE_TOTAL,
                        p.PRODUCT_NAME, s.SHOP_NAME
                 FROM CART_ITEM ci
                 JOIN CART c    ON ci.CART_ID    = c.CART_ID
                 JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
                 JOIN SHOP s    ON p.SHOP_ID     = s.SHOP_ID
                 WHERE c.CUSTOMER_ID = :p_uid AND c.STATUS = 'Active'
                 ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";
    $stmt = oci_parse($conn, $sql_cart);
    oci_bind_by_name($stmt, ':p_uid', $user_id);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {
        $items[]     = $row;
        $cart_total += (float)$row['LINE_TOTAL'];
    }
    oci_free_statement($stmt);
}

// Guard: cart must not be empty
if (empty($items)) {
    header('Location: ' . ($is_buy_now ? 'category.php' : 'cart.php'));
    exit;
}

// ── Slot details ──────────────────────────────────────────────────────────────
$stmt = oci_parse($conn, "SELECT cs.SLOT_ID, cs.COLLECTION_DATE, cs.COLLECTION_TIME, cs.LOCATION
                           FROM COLLECTION_SLOT cs
                           WHERE cs.SLOT_ID = :p_sid
                             AND {$allowed_slot_rules_sql}");
oci_bind_by_name($stmt, ':p_sid', $slot_id);
oci_execute($stmt);
$slot = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$slot) {
    // Slot no longer valid
    unset($_SESSION['selected_slot_id']);
    header('Location: collection-slot.php');
    exit;
}

function checkout_slot_time_label(string $t): string {
    $slot = str_replace([' ', ':00'], '', $t);
    if ($slot === '10-13') return '10:00-13:00';
    if ($slot === '13-16') return '13:00-16:00';
    if ($slot === '16-19') return '16:00-19:00';
    return $t;
}

// ── User details ──────────────────────────────────────────────────────────────
$stmt = oci_parse($conn, "SELECT NAME, EMAIL, ADDRESS, PHONE_NO
                           FROM SYSTEM_USER WHERE USER_ID = :p_uid");
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_execute($stmt);
$user = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

// ── Payment methods ───────────────────────────────────────────────────────────
// ── Flash error from place_order.php ─────────────────────────────────────────
$order_error = '';
if (!empty($_SESSION['order_error'])) {
    $order_error = $_SESSION['order_error'];
    unset($_SESSION['order_error']);
}

$checkout_notice = '';
if (!empty($_SESSION['checkout_notice'])) {
    $checkout_notice = $_SESSION['checkout_notice'];
    unset($_SESSION['checkout_notice']);
}

// ── Bucket cart items by shop ─────────────────────────────────────────────────
$by_shop = [];
foreach ($items as $item) {
    $shop = $item['SHOP_NAME'];
    if (!isset($by_shop[$shop])) $by_shop[$shop] = [];
    $by_shop[$shop][] = $item;
}

$page_title = 'Checkout - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="invoice-page">
    <div class="container container-narrow">
        <div class="invoice-box">

            <div class="invoice-header">
                <h2>CHECKOUT</h2>
            </div>

            <?php if ($order_error): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;">
                <?php echo htmlspecialchars($order_error); ?>
            </div>
            <?php endif; ?>

            <?php if ($checkout_notice): ?>
            <div style="background:#d1fae5;color:#065f46;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;">
                <?php echo htmlspecialchars($checkout_notice); ?>
            </div>
            <?php endif; ?>

            <!-- Customer Details (read-only) -->
            <div class="invoice-customer">
                <h4>YOUR DETAILS</h4>
                <p>
                    <strong>Name:</strong> <?php echo htmlspecialchars($user['NAME'] ?? ''); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL'] ?? ''); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($user['PHONE_NO'] ?? 'Not provided'); ?><br>
                    <strong>Address:</strong> <?php echo htmlspecialchars($user['ADDRESS'] ?? 'Not provided'); ?>
                </p>
            </div>

            <!-- Collection Slot Summary -->
            <div class="invoice-customer" style="margin-top:1rem;">
                <h4>COLLECTION SLOT</h4>
                <p>
                    <strong>Date:</strong> <?php echo date('l, d M Y', strtotime(substr($slot['COLLECTION_DATE'], 0, 10))); ?><br>
                    <strong>Time:</strong> <?php echo htmlspecialchars(checkout_slot_time_label($slot['COLLECTION_TIME'])); ?><br>
                    <strong>Location:</strong> <?php echo htmlspecialchars($slot['LOCATION']); ?>
                </p>
                <a href="collection-slot.php" style="font-size:0.85rem;color:#f97316;">Change slot</a>
            </div>

            <!-- Order Summary -->
            <h4 class="invoice-table-title" style="margin-top:1.5rem;">
                <?php echo $is_buy_now ? 'INSTANT BUY SUMMARY' : 'ORDER SUMMARY'; ?>
            </h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>PRODUCT</th>
                        <th>QTY</th>
                        <th>UNIT PRICE</th>
                        <th>SUBTOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($by_shop as $shop_name => $shop_items): ?>
                    <tr>
                        <td colspan="4" style="background:#f9f9f9;font-weight:600;color:#f97316;padding:0.4rem 0.75rem;">
                            <?php echo htmlspecialchars(strtoupper($shop_name)); ?>
                        </td>
                    </tr>
                    <?php foreach ($shop_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['PRODUCT_NAME']); ?></td>
                        <td><?php echo (int)$item['QUANTITY']; ?></td>
                        <td>GBP <?php echo number_format((float)$item['PRICE'], 2); ?></td>
                        <td><strong>GBP <?php echo number_format((float)$item['LINE_TOTAL'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="invoice-totals">
                <div class="invoice-total-row grand-total-row">
                    <span>TOTAL TO PAY:</span>
                    <span>GBP <?php echo number_format($cart_total, 2); ?></span>
                </div>
            </div>

            <!-- Place Order Form -->
            <form method="post" action="place_order.php" id="checkout-payment-form" style="margin-top:1.5rem;">

                <div style="margin-bottom:1rem;">
                    <label style="display:block;font-weight:600;margin-bottom:0.4rem;">PAYMENT METHOD</label>
                    <select name="payment_choice" id="payment-choice" required
                            style="width:100%;padding:0.6rem;border:1px solid #ddd;border-radius:4px;font-family:inherit;">
                        <option value="">&mdash; Select payment method &mdash;</option>
                        <option value="cash">Cash</option>
                        <option value="paypal">PayPal Sandbox</option>
                    </select>
                    <p id="payment-choice-note" style="color:#888;font-size:0.9rem;margin-top:0.45rem;">
                        Choose Cash to pay at collection, or PayPal Sandbox to pay online now.
                    </p>
                </div>

                <button type="submit" class="btn-confirm" id="checkout-submit-button"
                        style="width:100%;padding:0.9rem;font-size:1rem;margin-top:0.5rem;">
                    PLACE ORDER
                </button>

            </form>

            <p style="text-align:center;margin-top:0.75rem;">
                <?php if ($is_buy_now): ?>
                    <a href="product.php?id=<?php echo (int)$items[0]['PRODUCT_ID']; ?>" style="color:#888;font-size:0.85rem;">&larr; Back to product</a>
                <?php else: ?>
                    <a href="cart.php" style="color:#888;font-size:0.85rem;">&larr; Back to cart</a>
                <?php endif; ?>
            </p>

        </div>
    </div>
</section>

<script>
const paymentForm = document.getElementById('checkout-payment-form');
const paymentChoice = document.getElementById('payment-choice');
const submitButton = document.getElementById('checkout-submit-button');
const paymentNote = document.getElementById('payment-choice-note');

function refreshPaymentButton() {
    if (paymentChoice.value === 'paypal') {
        paymentForm.action = 'paypal_start.php';
        submitButton.textContent = 'PAY WITH PAYPAL SANDBOX';
        submitButton.style.background = '#ffc439';
        submitButton.style.borderColor = '#ffc439';
        submitButton.style.color = '#111';
        paymentNote.textContent = 'You will be redirected to PayPal Sandbox. Payment will be marked as Paid after PayPal returns.';
        return;
    }

    paymentForm.action = 'place_order.php';
    submitButton.textContent = 'PLACE ORDER';
    submitButton.style.background = '';
    submitButton.style.borderColor = '';
    submitButton.style.color = '';
    paymentNote.textContent = paymentChoice.value === 'cash'
        ? 'Cash payment will be collected later, so payment status will be Pending.'
        : 'Choose Cash to pay at collection, or PayPal Sandbox to pay online now.';
}

paymentChoice.addEventListener('change', refreshPaymentButton);
refreshPaymentButton();
</script>

<?php include 'footer.php'; ?>
