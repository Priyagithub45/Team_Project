<?php
/**
 * Customer Profile Page - Cleckhuddesfax Online Mart
 * 
 * This page shows the logged-in customer's account details
 * and their order history from the database.
 */

// -------------------------------------------------------
// Start session — needed to check if the user is logged in
// -------------------------------------------------------
include '../db.php';
include 'auth_check.php';

// -------------------------------------------------------
// STEP 1: Get the logged-in user's ID from the session
// -------------------------------------------------------
$user_id = $_SESSION['user_id'];

// -------------------------------------------------------
// STEP 2: Fetch the user's details from SYSTEM_USER table
// -------------------------------------------------------
$user_sql  = "SELECT NAME, EMAIL, PHONE_NO, ADDRESS FROM SYSTEM_USER WHERE USER_ID = :p_uid";
$user_stmt = oci_parse($conn, $user_sql);
oci_bind_by_name($user_stmt, ':p_uid', $user_id);
oci_execute($user_stmt);
$user_data = oci_fetch_array($user_stmt, OCI_ASSOC + OCI_RETURN_NULLS);
oci_free_statement($user_stmt);

// -------------------------------------------------------
// STEP 3: Fetch the customer's loyalty points from CUSTOMER table
// -------------------------------------------------------
$loyalty_sql  = "SELECT LOYALTY_POINTS FROM CUSTOMER WHERE USER_ID = :p_uid";
$loyalty_stmt = oci_parse($conn, $loyalty_sql);
oci_bind_by_name($loyalty_stmt, ':p_uid', $user_id);
oci_execute($loyalty_stmt);
$loyalty_data = oci_fetch_array($loyalty_stmt, OCI_ASSOC + OCI_RETURN_NULLS);
oci_free_statement($loyalty_stmt);

// -------------------------------------------------------
// STEP 4: Fetch the customer's order history from ORDERS table
// -------------------------------------------------------
$orders_sql  = "SELECT ORDER_ID, ORDER_DATE, TOTAL_AMOUNT, STATUS 
                FROM ORDERS 
                WHERE CUSTOMER_ID = :p_uid 
                ORDER BY ORDER_DATE DESC";
$orders_stmt = oci_parse($conn, $orders_sql);
oci_bind_by_name($orders_stmt, ':p_uid', $user_id);
oci_execute($orders_stmt);

// -------------------------------------------------------
// Handle profile update form submission
// -------------------------------------------------------
$update_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_phone   = trim($_POST['phone']);
    $new_address = trim($_POST['address']);

    $update_sql  = "UPDATE SYSTEM_USER SET PHONE_NO = :phone, ADDRESS = :address WHERE USER_ID = :p_uid";
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ':phone',   $new_phone);
    oci_bind_by_name($update_stmt, ':address', $new_address);
    oci_bind_by_name($update_stmt, ':p_uid',     $user_id);
    oci_execute($update_stmt);
    oci_free_statement($update_stmt);

    $update_message = "Profile updated successfully!";

    // Re-fetch updated data to display
    $user_stmt2 = oci_parse($conn, $user_sql);
    oci_bind_by_name($user_stmt2, ':p_uid', $user_id);
    oci_execute($user_stmt2);
    $user_data = oci_fetch_array($user_stmt2, OCI_ASSOC + OCI_RETURN_NULLS);
    oci_free_statement($user_stmt2);
}

// Show the HTML page now
include 'header.php';
?>

<!-- Profile Section -->
<section class="profile-page">
    <div class="container container-narrow">

        <div class="profile-section-header">
            <h2>ACCOUNT INFORMATION</h2>
            <?php if ($loyalty_data): ?>
                <!-- Show loyalty points if they exist -->
                <span style="color: #f97316; font-weight: 600;">
                    ⭐ Loyalty Points: <?php echo htmlspecialchars($loyalty_data['LOYALTY_POINTS'] ?? '0'); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($update_message)): ?>
            <!-- Show success message after profile update -->
            <div class="auth-error" style="background: #d1fae5; color: #065f46; border-color: #6ee7b7;">
                <?php echo htmlspecialchars($update_message); ?>
            </div>
        <?php endif; ?>

        <!-- Profile update form — submits to this same page -->
        <form class="profile-form" method="POST" action="profile.php">
            <input type="hidden" name="update_profile" value="1">
            <div class="profile-grid">
                <div class="profile-form-group">
                    <label>FULL NAME</label>
                    <!-- Show the name from the database; it's read-only -->
                    <input type="text" value="<?php echo htmlspecialchars($user_data['NAME'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-form-group">
                    <label>EMAIL ADDRESS</label>
                    <!-- Email is read-only; user cannot change it -->
                    <input type="email" value="<?php echo htmlspecialchars($user_data['EMAIL'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-form-group">
                    <label>PHONE NUMBER</label>
                    <!-- Phone is editable -->
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user_data['PHONE_NO'] ?? ''); ?>">
                </div>
            </div>

            <div class="profile-form-group">
                <label>MAILING ADDRESS</label>
                <!-- Address is editable -->
                <textarea name="address" rows="4"><?php echo htmlspecialchars($user_data['ADDRESS'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-update">UPDATE CHANGES</button>
        </form>

        <div class="profile-section-header mt-5">
            <h2>ORDER HISTORY</h2>
        </div>

        <table class="order-table">
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>DATE</th>
                    <th>TOTAL</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Loop through all orders and display each one as a table row
                $order_count = 0;
                while ($order = oci_fetch_array($orders_stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                    $order_count++;
                    // Format the date nicely
                    $order_date = $order['ORDER_DATE'] ? date('M d, Y', strtotime($order['ORDER_DATE'])) : 'N/A';
                    echo '<tr>';
                    echo '<td>#ORD-' . htmlspecialchars($order['ORDER_ID']) . '</td>';
                    echo '<td>' . htmlspecialchars($order_date) . '</td>';
                    echo '<td>£' . number_format($order['TOTAL_AMOUNT'] ?? 0, 2) . '</td>';
                    echo '<td>' . htmlspecialchars($order['STATUS'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }

                // If the customer has no orders yet, show a friendly message
                if ($order_count === 0) {
                    echo '<tr><td colspan="4" style="text-align:center; padding: 2rem; color: #888;">You have not placed any orders yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>

    </div>
</section>

<?php
// Free the orders query and close connection
oci_free_statement($orders_stmt);

include 'footer.php';
?>