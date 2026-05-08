<?php
/**
 * verify_otp.php
 * Confirms the email OTP, then creates the customer account.
 */

include '../db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$pending = $_SESSION['pending_registration'] ?? null;

if (!$pending) {
    $_SESSION['register_errors'] = ['Please complete the registration form first.'];
    header('Location: register.php');
    exit;
}

$errors = $_SESSION['otp_errors'] ?? [];
$success = $_SESSION['otp_success'] ?? '';
unset($_SESSION['otp_errors'], $_SESSION['otp_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    $errors = [];

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        $errors[] = 'Please enter the 6-digit OTP from your email.';
    } elseif (time() > (int)$pending['otp_expires_at']) {
        unset($_SESSION['pending_registration']);
        $_SESSION['register_errors'] = ['Your OTP expired. Please register again to receive a new code.'];
        header('Location: register.php');
        exit;
    } elseif (!password_verify($otp, $pending['otp_hash'])) {
        $errors[] = 'The OTP you entered is incorrect.';
    }

    if (empty($errors)) {
        $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM SYSTEM_USER WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:e))");
        oci_bind_by_name($stmt, ':e', $pending['email']);
        if (!oci_execute($stmt)) {
            $err = oci_error($stmt);
            $errors[] = 'Could not verify email availability: ' . ($err['message'] ?? 'unknown error');
        } else {
            $row = oci_fetch_assoc($stmt);
            if ((int)$row['CNT'] > 0) {
                $errors[] = 'An account with this email already exists.';
            }
        }
        oci_free_statement($stmt);
    }

    if (empty($errors)) {
        $stmt = oci_parse($conn, 'SELECT seq_system_user.NEXTVAL AS N FROM dual');
        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $err = oci_error($stmt);
            oci_rollback($conn);
            $errors[] = 'Could not prepare your account: ' . ($err['message'] ?? 'unknown error');
        } else {
            $row = oci_fetch_assoc($stmt);
            $new_user_id = (int)$row['N'];
        }
        oci_free_statement($stmt);
    }

    if (empty($errors)) {
        $stmt = oci_parse($conn, "
            INSERT INTO SYSTEM_USER (USER_ID, NAME, EMAIL, PASSWORD, PHONE_NO, ADDRESS, STATUS, CREATED_AT)
            VALUES (:u, :n, :e, :p, :ph, :a, 'Active', SYSDATE)
        ");
        oci_bind_by_name($stmt, ':u', $new_user_id);
        oci_bind_by_name($stmt, ':n', $pending['name']);
        oci_bind_by_name($stmt, ':e', $pending['email']);
        oci_bind_by_name($stmt, ':p', $pending['password_hash']);
        oci_bind_by_name($stmt, ':ph', $pending['phone']);
        oci_bind_by_name($stmt, ':a', $pending['address']);

        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $err = oci_error($stmt);
            oci_rollback($conn);
            $errors[] = 'Could not create account: ' . ($err['message'] ?? 'unknown error');
        }
        oci_free_statement($stmt);
    }

    if (empty($errors)) {
        $stmt = oci_parse($conn, 'INSERT INTO CUSTOMER (USER_ID, LOYALTY_POINTS) VALUES (:u, 0)');
        oci_bind_by_name($stmt, ':u', $new_user_id);

        if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
            $err = oci_error($stmt);
            oci_rollback($conn);
            $errors[] = 'Could not create customer profile: ' . ($err['message'] ?? 'unknown error');
        }
        oci_free_statement($stmt);
    }

    if (empty($errors)) {
        if (!oci_commit($conn)) {
            $err = oci_error($conn);
            oci_rollback($conn);
            $errors[] = 'Could not finish registration: ' . ($err['message'] ?? 'unknown error');
        } else {
            unset($_SESSION['pending_registration']);
            session_regenerate_id(true);
            $_SESSION['register_success'] = 'Email verified. Your account is ready. Please log in.';
            header('Location: login.php');
            exit;
        }
    }

    $_SESSION['otp_errors'] = $errors;
    header('Location: verify_otp.php');
    exit;
}

$page_title = 'Verify Email - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<style>
.otp-page {
    max-width: 440px;
    margin: 3rem auto;
    padding: 0 1rem;
    font-family: 'Poppins', sans-serif;
}
.otp-card {
    background: #fff;
    padding: 2.5rem 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.otp-card h1 {
    color: #003366;
    margin: 0 0 0.5rem;
    font-size: 1.6rem;
    text-align: center;
    letter-spacing: 1px;
}
.otp-subtitle {
    color: #666;
    text-align: center;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.otp-error,
.otp-success {
    padding: 0.8rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}
.otp-error {
    background: #fdecea;
    color: #b71c1c;
    border-left: 4px solid #b71c1c;
}
.otp-success {
    background: #e8f5e9;
    color: #1b5e20;
    border-left: 4px solid #1b5e20;
}
.otp-input {
    width: 100%;
    padding: 0.85rem;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 1.2rem;
    letter-spacing: 0;
    text-align: center;
    box-sizing: border-box;
}
.otp-input:focus {
    outline: none;
    border-color: #ff7a00;
    box-shadow: 0 0 0 3px rgba(255,122,0,0.15);
}
.otp-button {
    width: 100%;
    padding: 0.85rem;
    background: #ff7a00;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1rem;
}
.otp-link {
    display: block;
    margin-top: 1rem;
    text-align: center;
    color: #ff7a00;
    font-weight: 600;
    text-decoration: none;
}
</style>

<section class="otp-page">
    <div class="otp-card">
        <h1>VERIFY EMAIL</h1>
        <div class="otp-subtitle">
            Enter the 6-digit OTP sent to <?php echo htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8'); ?>.
        </div>

        <?php if ($success): ?>
            <div class="otp-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="otp-error">
                <ul style="margin:0; padding-left:20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="verify_otp.php" autocomplete="off">
            <input
                type="text"
                class="otp-input"
                name="otp"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                placeholder="000000"
                required
            >
            <button type="submit" class="otp-button">VERIFY OTP</button>
        </form>

        <a href="register.php?restart_otp=1" class="otp-link">Use a different email</a>
    </div>
</section>

<?php include 'footer.php'; ?>
