<?php
include '../db.php';
require_once '../csrf.php';
include 'product_image_helper.php';

$is_logged_in = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';

$flash_success = $_SESSION['cart_success'] ?? '';
$flash_error   = $_SESSION['cart_error']   ?? '';
unset($_SESSION['cart_success'], $_SESSION['cart_error']);

$image_select  = product_image_select($conn, 'p');
$by_shop       = [];
$grand_total   = 0.0;
$selected_slot = null;

if ($is_logged_in) {
    $user_id = (string)(int)$_SESSION['user_id'];

    $sql = "SELECT ci.CART_ITEM_ID, ci.QUANTITY, ci.PRICE,
                   (ci.QUANTITY * ci.PRICE) AS LINE_TOTAL,
                   p.PRODUCT_ID, p.PRODUCT_NAME, p.STOCK_QUANTITY,
                   p.MIN_ORDER, p.MAX_ORDER
                   {$image_select},
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

    if (!empty($_SESSION['selected_slot_id'])) {
        $slot_id  = (string)(int)$_SESSION['selected_slot_id'];
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
} else {
    // Guest path — build cart from session product_ids
    $guest_cart = $_SESSION['guest_cart'] ?? [];
    if (!empty($guest_cart)) {
        $product_ids  = array_keys($guest_cart);
        $placeholders = [];
        for ($i = 0, $n = count($product_ids); $i < $n; $i++) {
            $placeholders[] = ':p' . $i;
        }
        $in_clause = implode(',', $placeholders);

        $sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE, p.STOCK_QUANTITY,
                       p.MIN_ORDER, p.MAX_ORDER {$image_select},
                       s.SHOP_ID, s.SHOP_NAME
                FROM PRODUCT p
                JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                WHERE p.PRODUCT_ID IN ({$in_clause})
                ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";

        $stmt      = oci_parse($conn, $sql);
        $bind_vals = [];
        foreach ($product_ids as $i => $pid) {
            $bind_vals[$i] = (string)(int)$pid;
            oci_bind_by_name($stmt, ':p' . $i, $bind_vals[$i]);
        }
        oci_execute($stmt);

        while ($row = oci_fetch_assoc($stmt)) {
            $pid   = (int)$row['PRODUCT_ID'];
            $qty   = max(1, (int)($guest_cart[$pid] ?? 1));
            $price = (float)$row['PRICE'];

            // Use product_id as CART_ITEM_ID so the shared template works unchanged
            $row['CART_ITEM_ID'] = $pid;
            $row['QUANTITY']     = $qty;
            $row['LINE_TOTAL']   = $qty * $price;

            $shop = $row['SHOP_NAME'];
            if (!isset($by_shop[$shop])) {
                $by_shop[$shop] = ['items' => [], 'subtotal' => 0.0];
            }
            $by_shop[$shop]['items'][] = $row;
            $by_shop[$shop]['subtotal'] += $qty * $price;
            $grand_total += $qty * $price;
        }
        oci_free_statement($stmt);
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
            <div class="cfo-flash cfo-flash-success" style="display:none"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="cfo-flash cfo-flash-error" style="display:none"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <?php if (!$is_logged_in && empty($by_shop)): ?>
            <div style="text-align:center;padding:4rem 1rem;color:#888;">
                <span class="material-icons" style="font-size:3rem;display:block;margin-bottom:1rem;">shopping_cart</span>
                <p>Your cart is empty.</p>
                <a href="category.php" class="btn btn-primary" style="margin-top:1rem;display:inline-block;">SHOP NOW</a>
            </div>
        <?php elseif (empty($by_shop)): ?>
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
                    $max_qty = (int)$item['STOCK_QUANTITY'];
                    if (!empty($item['MAX_ORDER']) && (int)$item['MAX_ORDER'] > 0) {
                        $max_qty = min($max_qty, (int)$item['MAX_ORDER']);
                    }
                    $min_qty = (!empty($item['MIN_ORDER']) && (int)$item['MIN_ORDER'] > 1) ? (int)$item['MIN_ORDER'] : 1;
                ?>
                <div class="cart-item-row" data-item-id="<?php echo (int)$item['CART_ITEM_ID']; ?>"
                     data-shop="<?php echo htmlspecialchars($shop_name); ?>">
                    <div class="cart-item-img">
                        <?php render_product_image(
                            $item,
                            'width:100%;height:100%;object-fit:cover;',
                            'width:100%;height:100%;background:#f3f3f3;display:flex;align-items:center;justify-content:center;',
                            'color:#ccc;'
                        ); ?>
                    </div>
                    <div class="cart-item-info">
                        <h4 class="cart-item-name">
                            <a href="product.php?id=<?php echo (int)$item['PRODUCT_ID']; ?>" style="color:inherit;text-decoration:none;">
                                <?php echo htmlspecialchars($item['PRODUCT_NAME']); ?>
                            </a>
                        </h4>
                        <div class="cart-qty-wrapper">
                            <div class="qty-stepper"
                                 data-item-id="<?php echo (int)$item['CART_ITEM_ID']; ?>"
                                 data-price="<?php echo number_format((float)$item['PRICE'], 2, '.', ''); ?>"
                                 data-min="<?php echo $min_qty; ?>"
                                 data-max="<?php echo $max_qty; ?>"
                                 data-shop="<?php echo htmlspecialchars($shop_name); ?>"
                                 data-guest="<?php echo $is_logged_in ? '0' : '1'; ?>">
                                <button type="button" class="qty-btn qty-dec" aria-label="Decrease quantity">&#8722;</button>
                                <input type="number"
                                       class="qty-input"
                                       value="<?php echo (int)$item['QUANTITY']; ?>"
                                       min="<?php echo $min_qty; ?>"
                                       max="<?php echo $max_qty; ?>"
                                       aria-label="Quantity">
                                <button type="button" class="qty-btn qty-inc" aria-label="Increase quantity">&#43;</button>
                            </div>
                            <?php if ($is_logged_in): ?>
                            <form method="post" action="remove_cart_item.php" style="display:inline;">
                                <?= csrf_field('customer_cart') ?>
                                <input type="hidden" name="item_id" value="<?php echo (int)$item['CART_ITEM_ID']; ?>">
                                <button type="submit" class="cart-remove-link"
                                        style="background:none;border:none;cursor:pointer;color:#f97316;font-size:inherit;font-family:inherit;">
                                    REMOVE
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="post" action="remove_cart_item.php" style="display:inline;">
                                <?= csrf_field('customer_cart') ?>
                                <input type="hidden" name="product_id" value="<?php echo (int)$item['PRODUCT_ID']; ?>">
                                <input type="hidden" name="guest" value="1">
                                <button type="submit" class="cart-remove-link"
                                        style="background:none;border:none;cursor:pointer;color:#f97316;font-size:inherit;font-family:inherit;">
                                    REMOVE
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-stock-error" id="stock-err-<?php echo (int)$item['CART_ITEM_ID']; ?>" style="display:none;"></div>
                    </div>
                    <div class="cart-item-price" id="line-<?php echo (int)$item['CART_ITEM_ID']; ?>">GBP <?php echo number_format((float)$item['LINE_TOTAL'], 2); ?></div>
                </div>
                <?php endforeach; ?>

                <div class="cart-trader-subtotal">
                    <span class="subtotal-label">SUB-TOTAL (<?php echo htmlspecialchars(strtoupper($shop_name)); ?>):</span>
                    <span class="subtotal-amount" id="subtotal-<?php echo htmlspecialchars(preg_replace('/[^a-zA-Z0-9]/', '_', $shop_name)); ?>">GBP <?php echo number_format($group['subtotal'], 2); ?></span>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="cart-separator"></div>

            <div class="cart-summary">
                <?php if ($is_logged_in): ?>
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

                    <?php if (!$selected_slot): ?>
                    <div class="cart-slot-reminder">
                        <span class="material-icons">schedule</span>
                        <div>
                            <strong>No collection slot selected</strong>
                            <p>Select a slot before checkout. Available slots may fill up.</p>
                            <a href="collection-slot.php">Select a collection slot &rarr;</a>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="cart-slot-reminder">
                        <span class="material-icons">info</span>
                        <div>
                            <strong>Ready to checkout?</strong>
                            <p>Login or create an account to complete your order. Your cart will be saved.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="cart-summary-row cart-grand-total">
                    <span>GRAND TOTAL:</span>
                    <span class="amount" id="cart-grand-total">GBP <?php echo number_format($grand_total, 2); ?></span>
                </div>

                <?php if ($is_logged_in): ?>
                <div class="cart-actions">
                    <button class="btn-slot" onclick="window.location.href='collection-slot.php'">
                        <?php echo $selected_slot ? 'CHANGE COLLECTION SLOT' : 'SELECT COLLECTION SLOT'; ?>
                    </button>
                    <button class="btn-checkout" onclick="window.location.href='checkout.php?mode=cart'">PROCEED TO CHECKOUT</button>
                </div>
                <?php else: ?>
                <div class="cart-actions">
                    <a href="register.php" class="btn-slot" style="text-decoration:none;text-align:center;">CREATE ACCOUNT</a>
                    <a href="login.php" class="btn-checkout" style="text-decoration:none;text-align:center;">LOGIN TO CHECKOUT</a>
                </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    'use strict';

    const DEBOUNCE_MS = 600;
    let timers = {};

    function recalcShopSubtotal(shopKey) {
        let sum = 0;
        document.querySelectorAll('.qty-stepper[data-shop="' + shopKey + '"]').forEach(function (s) {
            const iid   = s.dataset.itemId;
            const price = parseFloat(s.dataset.price);
            const qty   = parseInt(s.querySelector('.qty-input').value, 10) || 1;
            sum += qty * price;
            const lineEl = document.getElementById('line-' + iid);
            if (lineEl) lineEl.textContent = 'GBP ' + (qty * price).toFixed(2);
        });
        const subId = 'subtotal-' + shopKey.replace(/[^a-zA-Z0-9]/g, '_');
        const subEl = document.getElementById(subId);
        if (subEl) subEl.textContent = 'GBP ' + sum.toFixed(2);
    }

    function recalcGrandTotal() {
        let grand = 0;
        document.querySelectorAll('.subtotal-amount').forEach(function (el) {
            const val = parseFloat(el.textContent.replace('GBP ', '').replace(/,/g, '')) || 0;
            grand += val;
        });
        const el = document.getElementById('cart-grand-total');
        if (el) el.textContent = 'GBP ' + grand.toFixed(2);
    }

    function showStockError(iid, msg) {
        const el = document.getElementById('stock-err-' + iid);
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function clearStockError(iid) {
        const el = document.getElementById('stock-err-' + iid);
        if (el) { el.textContent = ''; el.style.display = 'none'; }
    }

    function syncStepperButtons(stepper) {
        const input = stepper.querySelector('.qty-input');
        const dec   = stepper.querySelector('.qty-dec');
        const inc   = stepper.querySelector('.qty-inc');
        const qty   = parseInt(input.value, 10) || 1;
        const min   = parseInt(stepper.dataset.min, 10) || 1;
        const max   = parseInt(stepper.dataset.max, 10) || 9999;
        dec.disabled = qty <= min;
        inc.disabled = qty >= max;
    }

    function sendQtyUpdate(stepper, qty) {
        const iid     = stepper.dataset.itemId;
        const shopKey = stepper.dataset.shop;
        const isGuest = stepper.dataset.guest === '1';

        const formData = new FormData();
        formData.append('csrf_context', 'customer_cart');
        formData.append('csrf_token', '<?php echo htmlspecialchars(csrf_token('customer_cart'), ENT_QUOTES, 'UTF-8'); ?>');
        formData.append('item_id', iid);
        formData.append('qty', qty);
        if (isGuest) {
            formData.append('guest', '1');
        }

        fetch('update_cart_qty.php', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    clearStockError(iid);
                    const lineEl = document.getElementById('line-' + iid);
                    if (lineEl) lineEl.textContent = 'GBP ' + data.line_total;
                    recalcShopSubtotal(shopKey);
                    recalcGrandTotal();
                } else {
                    showStockError(iid, data.error || 'Update failed');
                    if (data.max_qty !== undefined) {
                        const input = stepper.querySelector('.qty-input');
                        input.value = data.max_qty;
                        syncStepperButtons(stepper);
                        recalcShopSubtotal(shopKey);
                        recalcGrandTotal();
                    }
                }
            })
            .catch(function () {
                showStockError(iid, 'Network error — try again');
            });
    }

    function debouncedUpdate(stepper, qty) {
        const iid = stepper.dataset.itemId;
        clearTimeout(timers[iid]);
        timers[iid] = setTimeout(function () {
            sendQtyUpdate(stepper, qty);
        }, DEBOUNCE_MS);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.qty-btn');
        if (!btn) return;

        const stepper = btn.closest('.qty-stepper');
        const input   = stepper.querySelector('.qty-input');
        const min     = parseInt(stepper.dataset.min, 10) || 1;
        const max     = parseInt(stepper.dataset.max, 10) || 9999;
        let qty = parseInt(input.value, 10) || min;

        if (btn.classList.contains('qty-dec')) qty = Math.max(min, qty - 1);
        if (btn.classList.contains('qty-inc')) qty = Math.min(max, qty + 1);

        input.value = qty;
        syncStepperButtons(stepper);
        clearStockError(stepper.dataset.itemId);
        recalcShopSubtotal(stepper.dataset.shop);
        recalcGrandTotal();
        debouncedUpdate(stepper, qty);
    });

    document.addEventListener('change', function (e) {
        const input = e.target.closest('.qty-input');
        if (!input) return;

        const stepper = input.closest('.qty-stepper');
        const min     = parseInt(stepper.dataset.min, 10) || 1;
        const max     = parseInt(stepper.dataset.max, 10) || 9999;
        let qty = parseInt(input.value, 10) || min;
        qty = Math.max(min, Math.min(max, qty));
        input.value = qty;

        syncStepperButtons(stepper);
        clearStockError(stepper.dataset.itemId);
        recalcShopSubtotal(stepper.dataset.shop);
        recalcGrandTotal();
        debouncedUpdate(stepper, qty);
    });

    document.querySelectorAll('.qty-stepper').forEach(syncStepperButtons);

    // ── AJAX remove cart item ────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cart-remove-link');
        if (!btn) return;

        var form = btn.closest('form');
        if (!form) return;

        e.preventDefault();

        var row        = btn.closest('.cart-item-row');
        var shopBlock  = btn.closest('.cart-trader-block');
        var shopKey    = row ? row.dataset.shop : null;

        btn.textContent = 'Removing...';
        btn.style.opacity = '0.5';
        btn.disabled = true;

        if (row) {
            row.style.transition = 'opacity 0.22s ease';
            row.style.opacity    = '0.35';
        }

        fetch('remove_cart_item.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    new FormData(form)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                if (row) { row.style.opacity = '1'; }
                btn.textContent   = 'REMOVE';
                btn.style.opacity = '1';
                btn.disabled      = false;
                if (window.CFO_TOAST) { CFO_TOAST.show(data.error || 'Could not remove item.', 'error'); }
                return;
            }

            if (typeof window.updateCartBadge === 'function' && data.cart_count !== undefined) {
                window.updateCartBadge(data.cart_count);
            }

            if (!row) { window.location.reload(); return; }

            // Collapse the row out
            row.style.overflow   = 'hidden';
            row.style.height     = row.offsetHeight + 'px';
            row.style.transition = 'opacity 0.22s ease, height 0.28s ease, margin 0.28s ease, padding 0.28s ease';

            requestAnimationFrame(function () {
                row.style.opacity    = '0';
                row.style.height     = '0';
                row.style.marginTop  = '0';
                row.style.marginBottom = '0';
                row.style.padding    = '0';
            });

            setTimeout(function () {
                row.remove();

                if (!shopBlock) { recalcGrandTotal(); return; }

                var remaining = shopBlock.querySelectorAll('.cart-item-row');
                if (remaining.length === 0) {
                    shopBlock.style.transition = 'opacity 0.2s ease';
                    shopBlock.style.opacity    = '0';
                    setTimeout(function () {
                        shopBlock.remove();
                        recalcGrandTotal();
                        if (document.querySelectorAll('.cart-trader-block').length === 0) {
                            window.location.reload();
                        }
                    }, 220);
                } else {
                    recalcShopSubtotal(shopKey);
                    recalcGrandTotal();
                }
            }, 320);
        })
        .catch(function () {
            if (row) { row.style.opacity = '1'; }
            btn.textContent   = 'REMOVE';
            btn.style.opacity = '1';
            btn.disabled      = false;
            if (window.CFO_TOAST) { CFO_TOAST.show('Network error — try again.', 'error'); }
        });
    });
})();
</script>

<?php include 'footer.php'; ?>
