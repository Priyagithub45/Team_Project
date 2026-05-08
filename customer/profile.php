<?php
/**
 * Customer Profile Page - Cleckhuddesfax Online Mart
 *
 * Shows the logged-in customer's account details and only that customer's
 * order history.
 */

include '../db.php';
include 'auth_check.php';

$user_id = (string)(int)$_SESSION['user_id'];
$update_message = '';
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_phone = trim($_POST['phone'] ?? '');
    $new_address = trim($_POST['address'] ?? '');

    $update_sql = "UPDATE SYSTEM_USER
                   SET PHONE_NO = :phone,
                       ADDRESS = :address
                   WHERE USER_ID = :p_uid";
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ':phone', $new_phone);
    oci_bind_by_name($update_stmt, ':address', $new_address);
    oci_bind_by_name($update_stmt, ':p_uid', $user_id);

    if (oci_execute($update_stmt)) {
        $update_message = 'Profile updated successfully!';
    } else {
        $err = oci_error($update_stmt);
        $profile_error = 'Could not update profile: ' . ($err['message'] ?? 'unknown error');
    }
    oci_free_statement($update_stmt);
}

$user_sql = "SELECT NAME, EMAIL, PHONE_NO, ADDRESS
             FROM SYSTEM_USER
             WHERE USER_ID = :p_uid";
$user_stmt = oci_parse($conn, $user_sql);
oci_bind_by_name($user_stmt, ':p_uid', $user_id);
oci_execute($user_stmt);
$user_data = oci_fetch_assoc($user_stmt) ?: [];
oci_free_statement($user_stmt);

$loyalty_sql = "SELECT LOYALTY_POINTS
                FROM CUSTOMER
                WHERE USER_ID = :p_uid";
$loyalty_stmt = oci_parse($conn, $loyalty_sql);
oci_bind_by_name($loyalty_stmt, ':p_uid', $user_id);
oci_execute($loyalty_stmt);
$loyalty_data = oci_fetch_assoc($loyalty_stmt) ?: ['LOYALTY_POINTS' => 0];
oci_free_statement($loyalty_stmt);

$orders = [];
$orders_error = '';
$orders_sql = "SELECT o.ORDER_ID,
                      o.ORDER_DATE,
                      NVL(o.TOTAL_AMOUNT, 0) AS TOTAL_AMOUNT,
                      NVL(o.STATUS, 'Pending') AS ORDER_STATUS,
                      cs.COLLECTION_DATE,
                      cs.COLLECTION_TIME,
                      cs.LOCATION,
                      NVL(p.PAYMENT_STATUS, 'Pending') AS PAYMENT_STATUS,
                      NVL(pm.METHOD_NAME, 'On collection') AS METHOD_NAME,
                      (SELECT COUNT(*)
                         FROM ORDER_ITEM oi
                        WHERE oi.ORDER_ID = o.ORDER_ID) AS ITEM_COUNT
               FROM ORDERS o
               LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
               LEFT JOIN PAYMENT p ON p.ORDER_ID = o.ORDER_ID
               LEFT JOIN PAYMENT_METHOD pm ON pm.METHOD_ID = p.METHOD_ID
               WHERE o.CUSTOMER_ID = :p_uid
               ORDER BY o.ORDER_DATE DESC, o.ORDER_ID DESC";
$orders_stmt = oci_parse($conn, $orders_sql);
oci_bind_by_name($orders_stmt, ':p_uid', $user_id);

if (oci_execute($orders_stmt)) {
    while ($order = oci_fetch_assoc($orders_stmt)) {
        $orders[] = $order;
    }
} else {
    $err = oci_error($orders_stmt);
    $orders_error = 'Could not load order history: ' . ($err['message'] ?? 'unknown error');
}
oci_free_statement($orders_stmt);

function profile_date(?string $value, string $format): string
{
    if (!$value) {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

$page_title = 'Profile - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="profile-page">
    <div class="container container-narrow">

        <div class="profile-section-header">
            <h2>ACCOUNT INFORMATION</h2>
            <span class="profile-loyalty">
                Loyalty Points: <?php echo htmlspecialchars($loyalty_data['LOYALTY_POINTS'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <?php if ($update_message): ?>
            <div class="auth-error" style="background:#d1fae5;color:#065f46;border-color:#6ee7b7;">
                <?php echo htmlspecialchars($update_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($profile_error): ?>
            <div class="auth-error">
                <?php echo htmlspecialchars($profile_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form class="profile-form" method="post" action="profile.php">
            <input type="hidden" name="update_profile" value="1">
            <div class="profile-grid">
                <div class="profile-form-group">
                    <label>FULL NAME</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['NAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="profile-form-group">
                    <label>EMAIL ADDRESS</label>
                    <input type="email" value="<?php echo htmlspecialchars($user_data['EMAIL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                </div>
                <div class="profile-form-group">
                    <label>PHONE NUMBER</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['PHONE_NO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="profile-form-group">
                <label>MAILING ADDRESS</label>
                <textarea name="address" rows="4"><?php echo htmlspecialchars($user_data['ADDRESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button type="submit" class="btn-update">UPDATE CHANGES</button>
        </form>

        <div class="profile-section-header mt-5">
            <h2>ORDER HISTORY</h2>
            <span class="profile-order-count">
                <?php echo count($orders); ?> order<?php echo count($orders) === 1 ? '' : 's'; ?>
            </span>
        </div>

        <?php if ($orders_error): ?>
            <div class="auth-error">
                <?php echo htmlspecialchars($orders_error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php elseif (empty($orders)): ?>
            <div class="profile-empty-orders">
                <h3>No orders yet</h3>
                <p>Your completed checkouts will appear here.</p>
                <a href="category.php" class="btn btn-primary">SHOP NOW</a>
            </div>
        <?php else: ?>
            <div class="order-table-wrap">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>ORDER</th>
                            <th>DATE</th>
                            <th>ITEMS</th>
                            <th>COLLECTION</th>
                            <th>PAYMENT</th>
                            <th>TOTAL</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $order_id = (int)$order['ORDER_ID'];
                            $collection_date = $order['COLLECTION_DATE']
                                ? profile_date(substr($order['COLLECTION_DATE'], 0, 10), 'd M Y')
                                : 'N/A';
                            $collection_time = $order['COLLECTION_TIME'] ?: '';
                            ?>
                            <tr>
                                <td>#ORD-<?php echo $order_id; ?></td>
                                <td><?php echo htmlspecialchars(profile_date($order['ORDER_DATE'] ?? null, 'd M Y'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int)($order['ITEM_COUNT'] ?? 0); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(trim($collection_date . ' ' . $collection_time), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($order['METHOD_NAME'] ?? 'On collection', ENT_QUOTES, 'UTF-8'); ?><br>
                                    <span class="payment-status">
                                        <?php echo htmlspecialchars($order['PAYMENT_STATUS'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>&pound;<?php echo number_format((float)($order['TOTAL_AMOUNT'] ?? 0), 2); ?></td>
                                <td>
                                    <span class="order-status-pill">
                                        <?php echo htmlspecialchars($order['ORDER_STATUS'] ?? 'Pending', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="order-action-link" href="invoice.php?order_id=<?php echo $order_id; ?>">View Invoice</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php include 'footer.php'; ?>
