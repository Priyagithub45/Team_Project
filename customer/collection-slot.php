<?php
include '../db.php';
include 'auth_check.php';

// Handle slot selection POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot_id = filter_input(INPUT_POST, 'slot_id', FILTER_VALIDATE_INT);
    $is_buy_now_checkout = !empty($_SESSION['buy_now_item']) && (($_SESSION['checkout_mode'] ?? '') === 'buy_now');

    if ($slot_id && $slot_id > 0) {
        $slot_sql = "SELECT SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION,
                            (20 - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID)) AS REMAINING
                     FROM COLLECTION_SLOT cs
                     WHERE SLOT_ID = :p_sid
                       AND COLLECTION_DATE >= SYSDATE + 1";
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
                $_SESSION['checkout_notice'] = '';
            } else {
                $_SESSION['cart_error'] = 'Selected collection slot is no longer available. Please choose another slot.';
            }
        }
    }
    header('Location: ' . ($is_buy_now_checkout ? 'checkout.php?mode=buy_now' : 'cart.php'));
    exit;
}

// Query future slots with capacity remaining
$sql = "SELECT SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION,
               (20 - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = cs.SLOT_ID)) AS REMAINING
        FROM COLLECTION_SLOT cs
        WHERE COLLECTION_DATE >= SYSDATE + 1
        ORDER BY COLLECTION_DATE, COLLECTION_TIME";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

// Group by date
$by_date = [];
while ($row = oci_fetch_assoc($stmt)) {
    // COLLECTION_DATE comes as 'YYYY-MM-DD HH24:MI:SS' due to NLS_DATE_FORMAT
    $date_key = substr($row['COLLECTION_DATE'], 0, 10); // 'YYYY-MM-DD'
    if (!isset($by_date[$date_key])) {
        $by_date[$date_key] = [];
    }
    $by_date[$date_key][] = $row;
}
oci_free_statement($stmt);

$selected_slot_id = $_SESSION['selected_slot_id'] ?? null;

$page_title = 'Select Collection Slot - Cleckhuddesfax Online Mart';
include 'header.php';

// Helper: format date key to readable label
function fmt_date(string $d): string {
    $ts = strtotime($d);
    return date('l, d M Y', $ts); // e.g. "Wednesday, 07 May 2026"
}

// Helper: derive slot label from time string
function slot_label(string $t): string {
    $hour = (int)$t;
    if ($hour < 13)  return 'Morning Collection';
    if ($hour < 16)  return 'Afternoon Collection';
    return 'Evening Collection';
}
?>

<section class="slot-page">
    <div class="container">
        <div class="slot-header">
            <h1>SELECT COLLECTION SLOT</h1>
            <p class="slot-subtitle">Choose a convenient time for your pickup. Max 20 orders per slot. Must be at least 24 hours from now.</p>
        </div>

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
                    $time_str  = htmlspecialchars($slot['COLLECTION_TIME']);
                    $label     = slot_label($slot['COLLECTION_TIME']);
                ?>
                <form method="post" action="collection-slot.php">
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
                        <div style="font-size:0.75rem;color:#888;margin-top:0.5rem;">
                            <?php echo $remaining; ?> spot<?php echo $remaining !== 1 ? 's' : ''; ?> remaining
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
