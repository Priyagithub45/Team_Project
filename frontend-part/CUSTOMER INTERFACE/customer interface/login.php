<?php
/**
 * Login Page - Cleckhuddesfax Online Mart
 * 
 * This page handles two things:
 * 1. SHOW the login form (when user visits the page)
 * 2. PROCESS the form (when user clicks the Login button)
 */

// -------------------------------------------------------
// Start a PHP Session
// A session is like a "memory" for the website.
// It remembers who is logged in as they move between pages.
// session_start() MUST be called before anything else.
// -------------------------------------------------------
session_start();

// -------------------------------------------------------
// If the user is already logged in, send them to their profile.
// No need to see the login page again.
// -------------------------------------------------------
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

// -------------------------------------------------------
// Connect to the Oracle database
// db.php contains the connection code we created
// -------------------------------------------------------
include 'db.php';

// This variable will hold any error message to show the user
$error_message = "";

// -------------------------------------------------------
// CHECK IF THE LOGIN FORM WAS SUBMITTED
// $_SERVER['REQUEST_METHOD'] is "POST" when the user clicks the Login button
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------------------------------------------------------
    // STEP 1: Get the data the user typed into the form
    // trim() removes any extra spaces from the start/end
    // -------------------------------------------------------
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']); // either "customer" or "trader"

    // -------------------------------------------------------
    // STEP 2: Basic validation — make sure fields are not empty
    // -------------------------------------------------------
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";

    } else {
        // -------------------------------------------------------
        // STEP 3: Search the SYSTEM_USER table for this email
        // We use a "prepared statement" (:email) to safely
        // insert the user's input. This prevents SQL Injection attacks.
        // -------------------------------------------------------
        $sql = "SELECT USER_ID, NAME, EMAIL, PASSWORD, STATUS 
                FROM SYSTEM_USER 
                WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email))";

        // Prepare the SQL query
        $stmt = oci_parse($conn, $sql);

        // Bind the user's email to the :email placeholder
        oci_bind_by_name($stmt, ':email', $email);

        // Run the query
        oci_execute($stmt);

        // -------------------------------------------------------
        // STEP 4: Fetch the result (the user row, if found)
        // OCI_ASSOC means we get the results as an array (e.g. $row['NAME'])
        // -------------------------------------------------------
        $user = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS);

        if (!$user) {
            // No user found with that email address
            $error_message = "Invalid email or password. Please try again.";

        } elseif (strtolower($user['STATUS']) !== 'active') {
            // The account exists but has been deactivated by admin
            $error_message = "Your account is inactive. Please contact support.";

        } elseif (trim($user['PASSWORD']) !== $password) {
            // The password does not match what is stored in the database
            // NOTE: In a real system, passwords should be hashed (encrypted).
            // For now, we compare them directly as the database stores plain text.
            $error_message = "Invalid email or password. Please try again.";

        } else {
            // -------------------------------------------------------
            // STEP 5: Password matched! Now check if the user's role
            // matches the tab they selected (Customer or Trader).
            // We check if they exist in the CUSTOMER or TRADER table.
            // -------------------------------------------------------
            $user_id = $user['USER_ID'];
            $role_valid = false;

            if ($role === 'customer') {
                // Check if this user_id exists in the CUSTOMER table
                $role_sql = "SELECT USER_ID FROM CUSTOMER WHERE USER_ID = :uid";
            } else {
                // Check if this user_id exists in the TRADER table
                $role_sql = "SELECT USER_ID FROM TRADER WHERE USER_ID = :uid";
            }

            $role_stmt = oci_parse($conn, $role_sql);
            oci_bind_by_name($role_stmt, ':uid', $user_id);
            oci_execute($role_stmt);
            $role_row = oci_fetch_array($role_stmt, OCI_ASSOC);

            if ($role_row) {
                $role_valid = true; // User exists in the correct role table
            }

            if (!$role_valid) {
                $error_message = "You do not have a '{$role}' account. Please select the correct tab.";
            } else {
                // -------------------------------------------------------
                // STEP 6: Login is successful!
                // Save the user's information into the Session.
                // This is how all other pages will know who is logged in.
                // -------------------------------------------------------
                $_SESSION['user_id']   = $user['USER_ID'];   // Save the user ID
                $_SESSION['user_name'] = $user['NAME'];       // Save their name
                $_SESSION['user_email']= $user['EMAIL'];      // Save their email
                $_SESSION['user_role'] = $role;               // Save their role (customer/trader)

                // -------------------------------------------------------
                // STEP 7: Redirect to the profile page after login
                // header() sends the user to a new page
                // exit() stops any further code from running
                // -------------------------------------------------------
                header("Location: profile.php");
                exit();
            }
        }

        // Free the query resources from memory
        oci_free_statement($stmt);
    }

    // Close the database connection
    oci_close($conn);
}

// -------------------------------------------------------
// Now include the header and show the login form HTML
// -------------------------------------------------------
include 'header.php';
?>

    <!-- Login Section -->
    <section class="auth-page">
        <div class="auth-card">
            <h1>LOGIN</h1>
            <div class="auth-subtitle">Sign in to your account</div>

            <?php
            // If there is an error message, display it in a red box
            if (!empty($error_message)) {
                echo '<div class="auth-error">' . htmlspecialchars($error_message) . '</div>';
            }
            ?>

            <div class="auth-tabs">
                <div class="auth-tab active" id="tab-customer">CUSTOMER</div>
                <div class="auth-tab" id="tab-trader">TRADER</div>
            </div>

            <!-- The form POSTs to the same page (login.php) -->
            <form action="login.php" method="POST" id="login-form">
                <!-- Hidden field that stores whether user selected Customer or Trader -->
                <input type="hidden" name="role" id="role-input" value="customer">

                <div class="auth-form-group">
                    <div class="auth-label">EMAIL ADDRESS</div>
                    <!-- name="email" must match $_POST['email'] in the PHP above -->
                    <input type="email" class="auth-input" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="auth-form-group">
                    <div class="auth-label">PASSWORD</div>
                    <!-- name="password" must match $_POST['password'] in the PHP above -->
                    <input type="password" class="auth-input" name="password" required>
                </div>
                
                <div class="auth-checkbox-group">
                    <input type="checkbox" id="remember-me">
                    <label for="remember-me">REMEMBER ME</label>
                </div>
                
                <button type="submit" class="btn-auth">LOGIN</button>
            </form>
        </div>
        
        <div class="auth-footer">
            <span class="auth-footer-text">NEW TO CLECKHUDDESFAX?</span>
            <a href="register.php" class="auth-footer-link">REGISTER AN ACCOUNT</a>
        </div>
    </section>

<?php include 'footer.php'; ?>
