<?php
require_once 'db.php';
require_once 'mail_helpers.php';

function dispatch_allowed(): bool
{
    $token = trim((string)getenv('CFO_MAIL_DISPATCH_TOKEN'));
    if ($token !== '') {
        $provided = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        return hash_equals($token, $provided);
    }

    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    return in_array($remote, ['127.0.0.1', '::1'], true) || PHP_SAPI === 'cli';
}

function dispatch_mark_mail($conn, int $outbox_id, bool $sent, string $error = ''): void
{
    $status = $sent ? 'SENT' : 'FAILED';
    $sql = $sent
        ? "UPDATE CFO_MAIL_OUTBOX
           SET STATUS = :status,
               ATTEMPTS = ATTEMPTS + 1,
               ERROR_MESSAGE = NULL,
               SENT_AT = SYSTIMESTAMP
           WHERE OUTBOX_ID = :outbox_id"
        : "UPDATE CFO_MAIL_OUTBOX
           SET STATUS = :status,
               ATTEMPTS = ATTEMPTS + 1,
               ERROR_MESSAGE = SUBSTR(:error_message, 1, 1000)
           WHERE OUTBOX_ID = :outbox_id";

    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return;
    }

    oci_bind_by_name($stmt, ':status', $status);
    oci_bind_by_name($stmt, ':outbox_id', $outbox_id);
    if (!$sent) {
        oci_bind_by_name($stmt, ':error_message', $error);
    }
    oci_execute($stmt, OCI_NO_AUTO_COMMIT);
    oci_free_statement($stmt);
    oci_commit($conn);
}

function dispatch_trader_approval_mail($conn, array $mail): bool
{
    $application_id = (int)($mail['RELATED_ID'] ?? 0);
    if ($application_id < 1) {
        throw new RuntimeException('Missing trader application id.');
    }

    $stmt = oci_parse(
        $conn,
        "SELECT ta.APPLICATION_ID,
                ta.OWNER_NAME,
                ta.EMAIL,
                ta.PROPOSED_SHOP_NAME,
                ta.APPROVED_USER_ID,
                su.EMAIL AS LOGIN_EMAIL
         FROM TRADER_APPLICATION ta
         LEFT JOIN SYSTEM_USER su ON su.USER_ID = ta.APPROVED_USER_ID
         WHERE ta.APPLICATION_ID = :application_id"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare trader application lookup.');
    }

    oci_bind_by_name($stmt, ':application_id', $application_id);
    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        throw new RuntimeException('Could not load trader application.');
    }

    $application = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    if (!$application) {
        throw new RuntimeException('Trader application not found.');
    }

    $login_email = (string)($application['LOGIN_EMAIL'] ?: $application['EMAIL']);
    return send_trader_approval_email(
        (string)$application['EMAIL'],
        (string)$application['OWNER_NAME'],
        (string)$application['PROPOSED_SHOP_NAME'],
        $login_email,
        'Trader@123'
    );
}

if (!dispatch_allowed()) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$limit = 20;
$stmt = oci_parse(
    $conn,
    "SELECT OUTBOX_ID,
            MAIL_TYPE,
            RELATED_ID,
            RECIPIENT_EMAIL,
            SUBJECT
     FROM CFO_MAIL_OUTBOX
     WHERE STATUS IN ('PENDING', 'FAILED')
       AND ATTEMPTS < 5
     ORDER BY CREATED_AT
     FETCH FIRST {$limit} ROWS ONLY"
);

if (!$stmt || !oci_execute($stmt)) {
    http_response_code(500);
    echo "Could not load mail outbox.\n";
    exit;
}

$processed = 0;
$sent = 0;
$failed = 0;

while ($mail = oci_fetch_assoc($stmt)) {
    $processed++;
    $outbox_id = (int)$mail['OUTBOX_ID'];

    try {
        if ((string)$mail['MAIL_TYPE'] !== 'TRADER_APPROVAL') {
            throw new RuntimeException('Unsupported mail type: ' . (string)$mail['MAIL_TYPE']);
        }

        if (dispatch_trader_approval_mail($conn, $mail)) {
            dispatch_mark_mail($conn, $outbox_id, true);
            $sent++;
        } else {
            dispatch_mark_mail($conn, $outbox_id, false, 'mail() returned false.');
            $failed++;
        }
    } catch (Throwable $e) {
        dispatch_mark_mail($conn, $outbox_id, false, $e->getMessage());
        $failed++;
    }
}
oci_free_statement($stmt);

header('Content-Type: text/plain; charset=UTF-8');
echo "Processed: {$processed}\n";
echo "Sent: {$sent}\n";
echo "Failed: {$failed}\n";
