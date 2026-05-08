<?php
include '../db.php';
include 'auth_check.php';

$user_id = (string)(int)$_SESSION['user_id'];

// Flash messages from add_to_cart.php
$flash_success = '';
$flash_error   = '';
if (!empty($_SESSION['cart_success'])) {
    $flash_success = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
}
if (!empty($_SESSION['cart_error'])) {
    $flash_error = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

// Query all active cart items for this user, grouped by shop
$sql = "SELECT ci.CART_ITEM_ID, ci.QUANTITY, ci.PRICE,
               (ci.QUANTITY * ci.PRICE) AS LINE_TOTAL,
               p.PRODUCT_ID, p.PRODUCT_NAME,
               s.SHOP_ID, s.SHOP_NAME
        FROM CART_ITEM ci
        JOIN CART c    ON ci.CART_ID    = c.CART_ID
        JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
        JOIN SHOP s    ON p.SHOP_ID     = s.SHOP_ID
        WHERE c.CUSTOMER_ID = :p_uid AND c.STATUS = 'Active'
        ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':p_uid', $user_id);
oci_execute($stmt);

// Bucket rows by shop name
$by_shop    = [];
$grand_total = 0.0;
while ($row = oci_fetch_assoc($stmt)) {
    $shop = $row['SHOP_NAME'];
    if (!isset($by_shop[$shop])) {
        $by_shop[$shop] = ['items' => [], 'subtotal' => 0.0];
    }
    $by_shop[$shop]['items'][]  = $row;
    $by_shop[$shop]['subtotal'] += (float)$row['LINE_TOTAL'];
    $grand_total += (float)$row['LINE_TOTAL'];
}
oci_free_statement($stmt);

$selected_slot = null;
if (!empty($_SESSION['selected_slot_id'])) {
    $slot_id = (string)(int)$_SESSION['selected_slot_id'];
    $slot_sql = "SELECT SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION
                 FROM COLLECTION_SLOT
                 WHERE SLOT_ID = :p_sid";
    $slot_stmt = oci_parse($conn, $slot_sql);
    oci_bind_by_name($slot_stmt, ':p_sid', $slot_id);
    oci_execute($slot_stmt);
    $selected_slot = oci_fetch_assoc($slot_stmt);
    oci_free_statement($slot_stmt);

    if (!$selected_slot) {
        unset($_SESSION['selected_slot_id']);
    }
}

function cart_slot_date(?string $date_value): string {
    if (!$date_value) {
        return 'Date unavailable';
    }

    $date_part = substr($date_value, 0, 10);
    $timestamp = strtotime($date_part);

    return $timestamp ? date('l, d M Y', $timestamp) : $date_value;
}

$page_title = 'Shopping Cart - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="cart-page">
    <div class="container container-narrow">
        <h2 class="cart-header-title">SHOPPING CART</h2>

        <?php if ($flash_success): ?>
            <div style="background:#d1fae5;color:#065f46;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;">
                <?php echo htmlspecialchars($flash_success); ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem;">
                <?php echo htmlspecialchars($flash_error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($by_shop)): ?>
            <div style="text-align:center;padding:4rem 1rem;color:#888;">
                <span class="material-icons" style="font-size:3rem;display:block;margin-bottom:1rem;">shopping_cart</span>
                <p>Your cart is empty.</p>
                <a href="category.php" class="btn btn-primary" style="margin-top:1rem;display:inline-block;">SHOP NOW</a>
            </div>
        <?php else: ?>

            <?php foreach ($by_shop as $shop_name => $group): ?>
            <div class="cart-trader-block">
                <div class="cart-trader-header">
                    <h3>Trader: <?php echo htmlspecialchars($shop_name); ?></h3>
                    <span class="item-count"><?php echo count($group['items']); ?> Item<?php echo count($group['items']) > 1 ? 's' : ''; ?></span>
                </div>

                <?php foreach ($group['items'] as $item):
                    $img_file = __DIR__ . '/assets/images/' . $item['PRODUCT_NAME'] . '.png';
                    $has_img  = file_exists($img_file);
                ?>
                <div class="cart-item-row">
                    <div class="cart-item-img">
                        <?php if ($has_img): ?>
                            <img src="assets/images/<?php echo rawurlencode($item['PRODUCT_NAME']); ?>.png"
                                 alt="<?php echo htmlspecialchars($item['PRODUCT_NAME']); ?>">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:#f3f3f3;display:flex;align-items:center;justify-content:center;">
                                <span class="material-icons" style="color:#ccc;">image_not_supported</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-info">
                        <h4 class="cart-item-name">
                            <a href="product.php?id=<?php echo (int)$item['PRODUCT_ID']; ?>" style="color:inherit;text-decoration:none;">
                                <?php echo htmlspecialchars($item['PRODUCT_NAME']); ?>
                            </a>
                        </h4>
                        <div class="cart-qty-wrapper">
                            <span class="cart-qty-box">Qty: <?php echo (int)$item['QUANTITY']; ?></span>
                            <form method="post" action="remove_cart_item.php" style="display:inline;">
                                <input type="hidden" name="item_id" value="<?php echo (int)$item['CART_ITEM_ID']; ?>">
                                <button type="submit" class="cart-remove-link"
                                        style="background:none;border:none;cursor:pointer;color:#f97316;font-size:inherit;font-family:inherit;">
                                    REMOVE
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="cart-item-price">£<?php echo number_format((float)$item['LINE_TOTAL'], 2); ?></div>
                </div>
                <?php endforeach; ?>

                <div class="cart-trader-subtotal">
                    <span class="subtotal-label">SUB-TOTAL (<?php echo htmlspecialchars(strtoupper($shop_name)); ?>):</span>
                    <span class="subtotal-amount">£<?php echo number_format($group['subtotal'], 2); ?></span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="cart-separator"></div>

            <div class="cart-summary">
                <?php if ($selected_slot): ?>
                    <div class="cart-selected-slot">
                        <span class="material-icons">event_available</span>
                        <div>
                            <strong>Collection slot selected</strong>
                            <p>
                                <?php echo htmlspecialchars(cart_slot_date($selected_slot['COLLECTION_DATE'] ?? null)); ?>
                                at <?php echo htmlspecialchars($selected_slot['COLLECTION_TIME'] ?? 'Time unavailable'); ?>
                                <?php if (!empty($selected_slot['LOCATION'])): ?>
                                    &middot; <?php echo htmlspecialchars($selected_slot['LOCATION']); ?>
                                <?php endif; ?>
                            </p>
                            <a href="collection-slot.php">Change slot</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cart-summary-row cart-grand-total">
                    <span>GRAND TOTAL:</span>
                    <span class="amount">£<?php echo number_format($grand_total, 2); ?></span>
                </div>
                <div class="cart-actions">
                    <button class="btn-slot" onclick="window.location.href='collection-slot.php'">SELECT COLLECTION SLOT</button>
                    <button class="btn-checkout" onclick="window.location.href='checkout.php?mode=cart'">PROCEED TO CHECKOUT</button>
                </div>
            </div>

        <?php endif; ?>
    </div>
</section>

<?php include 'footer.php'; ?>
