<?php
require_once '../db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function trader_application_column_exists($conn, string $column_name): bool
{
    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'TRADER_APPLICATION'
           AND COLUMN_NAME = UPPER(:column_name)"
    );
    if (!$stmt) {
        return false;
    }

    oci_bind_by_name($stmt, ':column_name', $column_name);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return false;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return (int)($row['CNT'] ?? 0) > 0;
}

function verification_message(string $message, string $type = 'error'): array
{
    return ['message' => $message, 'type' => $type];
}

$email = trim((string)($_POST['email'] ?? $_GET['email'] ?? ''));
$otp = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? ''));
$notice = null;

$has_otp = trader_application_column_exists($conn, 'EMAIL_VERIFY_OTP_HASH')
    && trader_application_column_exists($conn, 'EMAIL_VERIFY_OTP_EXPIRES_AT')
    && trader_application_column_exists($conn, 'EMAIL_VERIFY_OTP_ATTEMPTS')
    && trader_application_column_exists($conn, 'EMAIL_VERIFIED_AT');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$has_otp) {
        $notice = verification_message('OTP verification is not enabled yet. Please run migration 018.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $notice = verification_message('Enter the email address used on your trader application.');
    } elseif (strlen($otp) !== 6) {
        $notice = verification_message('Enter the 6-digit OTP sent to your email.');
    } else {
        $stmt = oci_parse(
            $conn,
            "SELECT APPLICATION_ID,
                    OWNER_NAME,
                    EMAIL_VERIFY_OTP_HASH,
                    EMAIL_VERIFY_OTP_ATTEMPTS,
                    CASE
                        WHEN EMAIL_VERIFY_OTP_EXPIRES_AT >= SYSTIMESTAMP THEN 1
                        ELSE 0
                    END AS OTP_ACTIVE
             FROM TRADER_APPLICATION
             WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(:email))
               AND EMAIL_VERIFIED_AT IS NULL
               AND STATUS = 'PENDING'
             ORDER BY APPLICATION_ID DESC
             FETCH FIRST 1 ROW ONLY"
        );

        if (!$stmt) {
            $notice = verification_message('We could not verify the OTP right now. Please try again.');
        } else {
            oci_bind_by_name($stmt, ':email', $email);
            if (!oci_execute($stmt)) {
                oci_free_statement($stmt);
                $notice = verification_message('We could not verify the OTP right now. Please try again.');
            } else {
                $application = oci_fetch_assoc($stmt) ?: null;
                oci_free_statement($stmt);

                if (!$application) {
                    $notice = verification_message('No pending unverified trader application was found for that email.');
                } elseif ((int)($application['EMAIL_VERIFY_OTP_ATTEMPTS'] ?? 0) >= 5) {
                    $notice = verification_message('Too many incorrect attempts. Please submit the trader application again to receive a new OTP.');
                } elseif ((int)($application['OTP_ACTIVE'] ?? 0) !== 1) {
                    $notice = verification_message('This OTP has expired. Please submit the trader application again to receive a new OTP.');
                } elseif (!password_verify($otp, (string)$application['EMAIL_VERIFY_OTP_HASH'])) {
                    $application_id = (int)$application['APPLICATION_ID'];
                    $stmt = oci_parse(
                        $conn,
                        "UPDATE TRADER_APPLICATION
                         SET EMAIL_VERIFY_OTP_ATTEMPTS = NVL(EMAIL_VERIFY_OTP_ATTEMPTS, 0) + 1
                         WHERE APPLICATION_ID = :application_id"
                    );
                    if ($stmt) {
                        oci_bind_by_name($stmt, ':application_id', $application_id);
                        oci_execute($stmt, OCI_NO_AUTO_COMMIT);
                        oci_commit($conn);
                        oci_free_statement($stmt);
                    }
                    $notice = verification_message('The OTP is incorrect. Please check the code and try again.');
                } else {
                    $application_id = (int)$application['APPLICATION_ID'];
                    $stmt = oci_parse(
                        $conn,
                        "UPDATE TRADER_APPLICATION
                         SET EMAIL_VERIFIED_AT = SYSTIMESTAMP,
                             EMAIL_VERIFY_OTP_HASH = NULL,
                             EMAIL_VERIFY_OTP_ATTEMPTS = 0
                         WHERE APPLICATION_ID = :application_id"
                    );
                    if ($stmt) {
                        oci_bind_by_name($stmt, ':application_id', $application_id);
                        if (oci_execute($stmt, OCI_NO_AUTO_COMMIT) && oci_commit($conn)) {
                            $notice = verification_message('Your email has been verified. The admin team can now review your trader application.', 'success');
                        } else {
                            oci_rollback($conn);
                            $notice = verification_message('We could not verify your email right now. Please try again.');
                        }
                        oci_free_statement($stmt);
                    }
                }
            }
        }
    }
}

$success = $_SESSION['trader_application_success'] ?? '';
unset($_SESSION['trader_application_success']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Trader Email &mdash; Cleckhuddesfax Online Mart</title>
    <link rel="stylesheet" href="trader.css">
</head>
<body class="trader-apply-page">
<main class="apply-page-main">
    <div class="apply-container">
        <div class="apply-form-card" style="margin-top:48px;">
            <div class="apply-form-card-header">
                <div>
                    <h2>Verify Trader Email</h2>
                    <p>Enter the 6-digit OTP sent to your application email.</p>
                </div>
            </div>
            <div class="apply-form-card-body">
                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>

                <?php if ($notice): ?>
                    <div class="alert alert-<?= h($notice['type'] === 'success' ? 'success' : 'error') ?>">
                        <?= h($notice['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$notice || $notice['type'] !== 'success'): ?>
                    <form method="post" class="application-form" autocomplete="off">
                        <div class="form-row full">
                            <div class="field">
                                <label for="email">Application Email</label>
                                <input type="email" id="email" name="email" maxlength="100" required value="<?= h($email) ?>">
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="field">
                                <label for="otp">6-digit OTP</label>
                                <input type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Verify Email</button>
                            <a class="btn btn-ghost" href="register.php">Back to Application</a>
                        </div>
                    </form>
                <?php else: ?>
                    <p><a class="btn btn-primary" href="login.php">Back to Trader Login</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>
