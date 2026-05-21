<?php
include '../db.php';
include 'auth_check.php';
require_once 'collection_slot_rules.php';

$allowed_slot_rules_sql = collection_slot_allowed_sql('cs');
$slot_order_sql = collection_slot_order_sql('cs');
$visible_collection_day_limit = 3;

// Handle slot selection POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post('customer_collection_slot');

    $slot_id = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
    $is_buy_now_checkout = !empty($_SESSION['buy_now_item']) && (($_SESSION['checkout_mode'] ?? '') === 'buy_now');

    if ($slot_id && $slot_id > 0) {
        $slot_sql = "SELECT SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION,
                            (" . COLLECTION_SLOT_MAX_ORDERS . " - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID)) AS REMAINING
                     FROM COLLECTION_SLOT cs
                     WHERE SLOT_ID = :p_sid
                       AND {$allowed_slot_rules_sql}";
        $slot_stmt = oci_parse($conn, $slot_sql);
        oci_bind_by_name($slot_stmt, ':p_sid', $slot_id);
        oci_execute($slot_stmt);
        $slot = oci_fetch_assoc($slot_stmt);
        oci_free_statement($slot_stmt);

        if ($slot && (int)$slot['REMAINING'] > 0) {
            $_SESSION['selected_slot_id'] = (int)$slot_id;

            $date_label = date('l, d M Y', strtotime(substr($slot['COLLECTION_DATE'], 0, 10)));
            $time_label = $slot['COLLECTION_TIME'];
            $location   = $slot['LOCATION'];

            $message = "Collection slot selected: {$date_label} at {$time_label}, {$location}.";
            if ($is_buy_now_checkout) {
                $_SESSION['checkout_notice'] = $message;
            } else {
                $_SESSION['cart_success'] = $message;
            }
        } else {
            unset($_SESSION['selected_slot_id']);
            if ($is_buy_now_checkout) {
                $_SESSION['order_error'] = 'Selected collection slot is no longer available. The slot may be full or less than 24 hours away.';
            } else {
                $_SESSION['cart_error'] = 'Selected collection slot is no longer available. The slot may be full or less than 24 hours away.';
            }
        }
    }
    header('Location: ' . ($is_buy_now_checkout ? 'checkout.php?mode=buy_now' : 'cart.php'));
    exit;
}

// Query valid future slots. Full slots are still shown, but cannot be selected.
$sql = "SELECT SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION,
               (" . COLLECTION_SLOT_MAX_ORDERS . " - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID)) AS REMAINING
        FROM COLLECTION_SLOT cs
        WHERE {$allowed_slot_rules_sql}
        ORDER BY COLLECTION_DATE, {$slot_order_sql}";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

// Group by date, keeping only the nearest eligible collection days.
$by_date = [];
while ($row = oci_fetch_assoc($stmt)) {
    // COLLECTION_DATE comes as 'YYYY-MM-DD HH24:MI:SS' due to NLS_DATE_FORMAT
    $date_key = substr($row['COLLECTION_DATE'], 0, 10); // 'YYYY-MM-DD'
    if (!isset($by_date[$date_key])) {
        if (count($by_date) >= $visible_collection_day_limit) {
            break;
        }
        $by_date[$date_key] = [];
    }
    $by_date[$date_key][] = $row;
}
oci_free_statement($stmt);

$selected_slot_id = $_SESSION['selected_slot_id'] ?? null;
$slot_error = '';
if (!empty($_SESSION['cart_error'])) {
    $slot_error = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
} elseif (!empty($_SESSION['order_error'])) {
    $slot_error = $_SESSION['order_error'];
    unset($_SESSION['order_error']);
}

$page_title = 'Select Collection Slot - Cleckhuddesfax Online Mart';
include 'header.php';

// Helper: format date key to readable label
function fmt_date(string $d): string {
    $ts = strtotime($d);
    return date('l, d M Y', $ts); // e.g. "Wednesday, 07 May 2026"
}

// Helper: derive slot label from time string
function slot_label(string $t): string {
    $slot = str_replace([' ', ':00'], '', $t);
    if ($slot === '10-13') return 'Morning Collection';
    if ($slot === '13-16') return 'Afternoon Collection';
    if ($slot === '16-19') return 'Evening Collection';
    return 'Collection Slot';
}

function slot_time_label(string $t): string {
    $slot = str_replace([' ', ':00'], '', $t);
    if ($slot === '10-13') return '10:00-13:00';
    if ($slot === '13-16') return '13:00-16:00';
    if ($slot === '16-19') return '16:00-19:00';
    return $t;
}
?>

<section class="slot-page">
    <div class="container">
        <div class="slot-header">
            <h1>SELECT COLLECTION SLOT</h1>
            <p class="slot-subtitle">Choose one collection slot before payment. We show the next three eligible collection days only. Collection is available on Wednesday, Thursday, and Friday at 10:00-13:00, 13:00-16:00, or 16:00-19:00, and each slot must start at least 24 hours after you order.</p>
        </div>

        <?php if ($slot_error): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
                <?php echo htmlspecialchars($slot_error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($by_date)): ?>
            <div style="text-align:center;padding:3rem;color:#888;">
                <p>No collection slots available at the moment. Please check back later.</p>
                <a href="cart.php" class="btn btn-primary" style="margin-top:1rem;display:inline-block;">BACK TO CART</a>
            </div>
        <?php else: ?>

            <?php if ($selected_slot_id): ?>
            <div style="background:#d1fae5;color:#065f46;padding:0.75rem 1rem;border-radius:6px;margin-bottom:1.5rem;">
                Slot selected! You can proceed to checkout or change your selection below.
            </div>
            <?php endif; ?>

            <!-- Date Tabs -->
            <div class="slot-tabs">
                <?php $i = 0; foreach ($by_date as $date_key => $slots): ?>
                <div class="slot-tab <?php echo $i === 0 ? 'active' : ''; ?>"
                     onclick="showDate('panel-<?php echo $i; ?>', this)"
                     style="cursor:pointer;">
                    <h3><?php echo date('l', strtotime($date_key)); ?></h3>
                    <small style="font-size:0.75rem;opacity:0.8;"><?php echo date('d M Y', strtotime($date_key)); ?></small>
                </div>
                <?php $i++; endforeach; ?>
            </div>

            <!-- Slot Panels (one per date) -->
            <?php $i = 0; foreach ($by_date as $date_key => $slots): ?>
            <div id="panel-<?php echo $i; ?>" class="slot-cards" style="<?php echo $i !== 0 ? 'display:none;' : ''; ?>">
                <?php foreach ($slots as $slot):
                    $remaining = (int)$slot['REMAINING'];
                    $is_full   = ($remaining <= 0);
                    $is_sel    = ((int)$slot['SLOT_ID'] === (int)$selected_slot_id);
                    $time_str  = htmlspecialchars(slot_time_label($slot['COLLECTION_TIME']));
                    $label     = slot_label($slot['COLLECTION_TIME']);
                    $used      = max(0, COLLECTION_SLOT_MAX_ORDERS - $remaining);
                    $filled_pct = min(100, max(0, (int)round(($used / COLLECTION_SLOT_MAX_ORDERS) * 100)));
                ?>
                <form method="post" action="collection-slot.php">
                    <?= csrf_field('customer_collection_slot') ?>
                    <input type="hidden" name="slot_id" value="<?php echo (int)$slot['SLOT_ID']; ?>">
                    <button type="submit" class="slot-card <?php echo $is_sel ? 'selected' : ''; ?>"
                            <?php echo $is_full ? 'disabled' : ''; ?>
                            style="width:100%;background:none;border:none;cursor:<?php echo $is_full ? 'not-allowed' : 'pointer'; ?>;padding:0;text-align:left;<?php echo $is_full ? 'opacity:0.5;' : ''; ?>">
                        <div class="slot-card-top" style="justify-content:flex-end;">
                            <?php if ($is_full): ?>
                                <span class="slot-badge" style="background:#ef4444;">FULL</span>
                            <?php elseif ($is_sel): ?>
                                <span class="slot-badge" style="background:#22c55e;">SELECTED</span>
                            <?php else: ?>
                                <span class="slot-badge">AVAILABLE</span>
                            <?php endif; ?>
                        </div>
                        <div class="slot-time"><?php echo $time_str; ?></div>
                        <div class="slot-name"><?php echo htmlspecialchars($label); ?></div>
                        <?php if (!$is_full): ?>
                        <div class="slot-availability">
                            <span><?php echo $remaining; ?> spot<?php echo $remaining !== 1 ? 's' : ''; ?> left</span>
                            <span><?php echo $used; ?>/<?php echo COLLECTION_SLOT_MAX_ORDERS; ?> booked</span>
                        </div>
                        <div class="progress-bar-bg" aria-hidden="true">
                            <div class="progress-bar-fill" style="width:<?php echo $filled_pct; ?>%;"></div>
                        </div>
                        <?php else: ?>
                        <div class="slot-availability">
                            <span>Slot is full</span>
                            <span><?php echo COLLECTION_SLOT_MAX_ORDERS; ?>/<?php echo COLLECTION_SLOT_MAX_ORDERS; ?> booked</span>
                        </div>
                        <?php endif; ?>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php $i++; endforeach; ?>

            <!-- Confirmation Bar -->
            <div class="confirmation-bar">
                <div class="conf-info">
                    <div class="info-icon">i</div>
                    <div class="conf-text">
                        <?php if ($selected_slot_id): ?>
                            <p><strong>Slot selected.</strong> Proceed to checkout when ready.</p>
                        <?php else: ?>
                            <p><strong>Click a slot card to select it.</strong></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="conf-actions">
                    <button class="btn-confirm" onclick="window.location.href='cart.php'">BACK TO CART</button>
                    <?php if ($selected_slot_id): ?>
                    <button class="btn-confirm" style="margin-left:0.5rem;" onclick="window.location.href='checkout.php'">PROCEED TO CHECKOUT</button>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</section>

<script>
function showDate(panelId, tabEl) {
    document.querySelectorAll('.slot-cards').forEach(function(p) { p.style.display = 'none'; });
    document.querySelectorAll('.slot-tab').forEach(function(t) { t.classList.remove('active'); });
    document.getElementById(panelId).style.display = '';
    tabEl.classList.add('active');
}
</script>

<?php include 'footer.php'; ?>
