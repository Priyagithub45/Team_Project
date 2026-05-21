<?php
/**
 * Real invoice / order confirmation page.
 */
include '../db.php';
include 'auth_check.php';

$user_id = (string)(int)$_SESSION['user_id'];
$order_id_raw = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id_raw || $order_id_raw < 1) {
    http_response_code(404);
    $page_title = 'Invoice Not Found - Cleckhuddesfax Online Mart';
    include 'header.php';
    ?>
    <section class="invoice-page">
        <div class="container container-narrow">
            <div class="invoice-box">
                <div class="invoice-header">
                    <h2>INVOICE NOT FOUND</h2>
                </div>
                <p>That invoice link is not valid.</p>
                <p><a href="profile.php" class="btn btn-primary" style="display:inline-block;margin-top:1rem;">VIEW PROFILE</a></p>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

$order_id = (string)(int)$order_id_raw;

$sql_order = "SELECT o.ORDER_ID, o.ORDER_DATE, o.TOTAL_AMOUNT, o.STATUS, o.CUSTOMER_ID,
                     su.NAME, su.EMAIL, su.ADDRESS, su.PHONE_NO,
                     cs.COLLECTION_DATE, cs.COLLECTION_TIME, cs.LOCATION,
                     p.PAYMENT_ID, p.PAYMENT_DATE, p.AMOUNT AS PAYMENT_AMOUNT, p.PAYMENT_STATUS,
                     pm.METHOD_NAME
              FROM ORDERS o
              JOIN SYSTEM_USER su ON su.USER_ID = o.CUSTOMER_ID
              LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
              LEFT JOIN PAYMENT p ON p.ORDER_ID = o.ORDER_ID
              LEFT JOIN PAYMENT_METHOD pm ON pm.METHOD_ID = p.METHOD_ID
              WHERE o.ORDER_ID = :p_oid";
$stmt = oci_parse($conn, $sql_order);
oci_bind_by_name($stmt, ':p_oid', $order_id);
oci_execute($stmt);
$order = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$order) {
    http_response_code(404);
    $page_title = 'Invoice Not Found - Cleckhuddesfax Online Mart';
    include 'header.php';
    ?>
    <section class="invoice-page">
        <div class="container container-narrow">
            <div class="invoice-box">
                <div class="invoice-header">
                    <h2>INVOICE NOT FOUND</h2>
                </div>
                <p>No order exists for that invoice number.</p>
                <p><a href="profile.php" class="btn btn-primary" style="display:inline-block;margin-top:1rem;">VIEW PROFILE</a></p>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

if ((int)$order['CUSTOMER_ID'] !== (int)$user_id) {
    http_response_code(403);
    $page_title = 'Access Denied - Cleckhuddesfax Online Mart';
    include 'header.php';
    ?>
    <section class="invoice-page">
        <div class="container container-narrow">
            <div class="invoice-box">
                <div class="invoice-header">
                    <h2>ACCESS DENIED</h2>
                </div>
                <p>This invoice does not belong to your account.</p>
                <p><a href="profile.php" class="btn btn-primary" style="display:inline-block;margin-top:1rem;">VIEW PROFILE</a></p>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

$sql_items = "SELECT oi.ORDER_ITEM_ID, oi.QUANTITY, oi.PRICE,
                     (oi.QUANTITY * oi.PRICE) AS LINE_TOTAL,
                     p.PRODUCT_NAME,
                     s.SHOP_NAME
              FROM ORDER_ITEM oi
              JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
              JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
              WHERE oi.ORDER_ID = :p_oid
              ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";
$stmt = oci_parse($conn, $sql_items);
oci_bind_by_name($stmt, ':p_oid', $order_id);
oci_execute($stmt);

$items_by_shop = [];
$item_subtotal = 0.0;
while ($item = oci_fetch_assoc($stmt)) {
    $shop_name = $item['SHOP_NAME'] ?? 'Unknown Trader';
    if (!isset($items_by_shop[$shop_name])) {
        $items_by_shop[$shop_name] = [];
    }

    $items_by_shop[$shop_name][] = $item;
    $item_subtotal += (float)$item['LINE_TOTAL'];
}
oci_free_statement($stmt);

$invoice_total = (float)($order['TOTAL_AMOUNT'] ?? $item_subtotal);
$payment_amount = (float)($order['PAYMENT_AMOUNT'] ?? $invoice_total);
$payment_method = $order['METHOD_NAME'] ?: 'PayPal Sandbox / On collection';
$payment_status = $order['PAYMENT_STATUS'] ?: 'Pending';
$order_status = $order['STATUS'] ?: 'Pending';
$is_paid = strtoupper(trim((string)$payment_status)) === 'PAID';

$order_date = $order['ORDER_DATE'] ? date('F j, Y', strtotime($order['ORDER_DATE'])) : 'N/A';
$payment_date = ($is_paid && $order['PAYMENT_DATE']) ? date('F j, Y, g:i A', strtotime($order['PAYMENT_DATE'])) : 'After collection';
$collection_date = $order['COLLECTION_DATE'] ? date('l, d M Y', strtotime(substr($order['COLLECTION_DATE'], 0, 10))) : 'N/A';
$total_label = $is_paid ? 'TOTAL PAID:' : 'TOTAL DUE:';

$page_title = 'Invoice #' . $order_id . ' - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="invoice-page">
    <div class="container container-narrow">
        <div class="invoice-box">
            <div class="invoice-header">
                <h2>ORDER CONFIRMATION</h2>
                <div style="text-align:right;font-size:0.85rem;line-height:1.6;">
                    <strong>Invoice:</strong> #ORD-<?php echo htmlspecialchars($order_id); ?><br>
                    <strong>Status:</strong> <?php echo htmlspecialchars($order_status); ?>
                </div>
            </div>

            <p class="invoice-date">Order Date: <?php echo htmlspecialchars($order_date); ?></p>

            <div class="invoice-customer">
                <h4>CUSTOMER DETAILS</h4>
                <p>
                    <strong>Name:</strong> <?php echo htmlspecialchars($order['NAME'] ?? ''); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($order['EMAIL'] ?? ''); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($order['PHONE_NO'] ?? 'Not provided'); ?><br>
                    <strong>Address:</strong> <?php echo htmlspecialchars($order['ADDRESS'] ?? 'Not provided'); ?>
                </p>
            </div>

            <div class="invoice-customer">
                <h4>COLLECTION SLOT</h4>
                <p>
                    <strong>Date:</strong> <?php echo htmlspecialchars($collection_date); ?><br>
                    <strong>Time:</strong> <?php echo htmlspecialchars($order['COLLECTION_TIME'] ?? 'N/A'); ?><br>
                    <strong>Location:</strong> <?php echo htmlspecialchars($order['LOCATION'] ?? 'N/A'); ?>
                </p>
            </div>

            <div class="invoice-customer">
                <h4>PAYMENT DETAILS</h4>
                <p>
                    <strong>Method:</strong> <?php echo htmlspecialchars($payment_method); ?><br>
                    <strong>Status:</strong> <?php echo htmlspecialchars($payment_status); ?><br>
                    <strong>Amount:</strong> GBP <?php echo number_format($payment_amount, 2); ?><br>
                    <strong>Payment Date:</strong> <?php echo htmlspecialchars($payment_date); ?>
                </p>
            </div>

            <h4 class="invoice-table-title">TRADER BREAKDOWN</h4>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>TRADER / PRODUCT</th>
                        <th>QTY</th>
                        <th>UNIT PRICE</th>
                        <th>SUBTOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items_by_shop)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;">No items found for this order.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items_by_shop as $shop_name => $shop_items): ?>
                            <?php foreach ($shop_items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars(strtoupper($shop_name)); ?></strong><br>
                                        <span class="text-italic"><?php echo htmlspecialchars($item['PRODUCT_NAME']); ?></span>
                                    </td>
                                    <td><?php echo (int)$item['QUANTITY']; ?></td>
                                    <td>GBP <?php echo number_format((float)$item['PRICE'], 2); ?></td>
                                    <td><strong>GBP <?php echo number_format((float)$item['LINE_TOTAL'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="invoice-totals">
                <div class="invoice-total-row">
                    <span>Item Subtotal:</span>
                    <span>GBP <?php echo number_format($item_subtotal, 2); ?></span>
                </div>
                <div class="invoice-total-row">
                    <span>Service Fee:</span>
                    <span>GBP 0.00</span>
                </div>
                <div class="invoice-total-row">
                    <span>Tax (0%):</span>
                    <span>GBP 0.00</span>
                </div>
                <div class="invoice-total-row grand-total-row">
                    <span><?php echo htmlspecialchars($total_label); ?></span>
                    <span>GBP <?php echo number_format($invoice_total, 2); ?></span>
                </div>
            </div>

            <div class="invoice-footer-text">
                <p class="thank-you">THANK YOU FOR YOUR BUSINESS.</p>
                <p class="customer-id">Customer ID: <?php echo htmlspecialchars($user_id); ?></p>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
