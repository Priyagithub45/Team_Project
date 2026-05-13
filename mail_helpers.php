<?php

function cfo_mail_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function cfo_money($value): string
{
    return 'GBP ' . number_format((float)$value, 2);
}

function cfo_format_mail_date($value, string $format, string $fallback = 'N/A'): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }

    $time = strtotime($raw);
    if ($time === false) {
        $time = strtotime(substr($raw, 0, 10));
    }

    return $time === false ? $fallback : date($format, $time);
}

function cfo_app_url(string $path): string
{
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/CFO/index.php'));
    $script_dir = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
    $app_root = preg_replace('#/(customer|trader|admin)$#', '', $script_dir) ?: '';

    return $scheme . '://' . $host . rtrim($app_root, '/') . '/' . ltrim($path, '/');
}

function cfo_send_html_mail(string $to, string $subject, string $html_body, string $text_body = ''): bool
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[MAIL] Invalid recipient email: ' . $to);
        return false;
    }

    $from_email = trim((string)(ini_get('sendmail_from') ?: 'no-reply@localhost'));
    $from_name = 'Cleckhuddesfax Online Mart';
    $boundary = 'cfo_mail_' . bin2hex(random_bytes(12));
    if ($text_body === '') {
        $text_body = trim((string)preg_replace('/[ \t]+/', ' ', strip_tags($html_body)));
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion(),
    ];

    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $text_body . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html_body . "\r\n\r\n"
        . '--' . $boundary . "--\r\n";

    if (!mail($to, $subject, $body, implode("\r\n", $headers))) {
        error_log('[MAIL] Failed sending "' . $subject . '" to ' . $to);
        return false;
    }

    return true;
}

function cfo_fetch_order_mail_data($conn, int $order_id): ?array
{
    $sql_order = "SELECT o.ORDER_ID,
                         o.ORDER_DATE,
                         o.TOTAL_AMOUNT,
                         o.STATUS,
                         su.NAME,
                         su.EMAIL,
                         su.PHONE_NO,
                         cs.COLLECTION_DATE,
                         cs.COLLECTION_TIME,
                         cs.LOCATION,
                         p.PAYMENT_DATE,
                         p.AMOUNT AS PAYMENT_AMOUNT,
                         p.PAYMENT_STATUS,
                         pm.METHOD_NAME
                  FROM ORDERS o
                  JOIN SYSTEM_USER su ON su.USER_ID = o.CUSTOMER_ID
                  LEFT JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
                  LEFT JOIN PAYMENT p ON p.ORDER_ID = o.ORDER_ID
                  LEFT JOIN PAYMENT_METHOD pm ON pm.METHOD_ID = p.METHOD_ID
                  WHERE o.ORDER_ID = :order_id";

    $stmt = oci_parse($conn, $sql_order);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[MAIL ORDER PARSE] ' . ($err['message'] ?? 'unknown error'));
        return null;
    }

    oci_bind_by_name($stmt, ':order_id', $order_id);
    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[MAIL ORDER QUERY] ' . ($err['message'] ?? 'unknown error'));
        oci_free_statement($stmt);
        return null;
    }

    $order = oci_fetch_assoc($stmt) ?: null;
    oci_free_statement($stmt);

    if (!$order) {
        return null;
    }

    $sql_items = "SELECT oi.QUANTITY,
                         oi.PRICE,
                         (oi.QUANTITY * oi.PRICE) AS LINE_TOTAL,
                         p.PRODUCT_NAME,
                         s.SHOP_NAME
                  FROM ORDER_ITEM oi
                  JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
                  JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
                  WHERE oi.ORDER_ID = :order_id
                  ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";

    $stmt = oci_parse($conn, $sql_items);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[MAIL ORDER ITEMS PARSE] ' . ($err['message'] ?? 'unknown error'));
        return ['order' => $order, 'items' => []];
    }

    oci_bind_by_name($stmt, ':order_id', $order_id);
    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[MAIL ORDER ITEMS QUERY] ' . ($err['message'] ?? 'unknown error'));
        oci_free_statement($stmt);
        return ['order' => $order, 'items' => []];
    }

    $items = [];
    while ($item = oci_fetch_assoc($stmt)) {
        $items[] = $item;
    }
    oci_free_statement($stmt);

    return ['order' => $order, 'items' => $items];
}

function cfo_order_confirmation_html(array $order, array $items): string
{
    $order_id = (int)$order['ORDER_ID'];
    $customer_name = cfo_mail_escape((string)($order['NAME'] ?? 'Customer'));
    $order_date = cfo_format_mail_date($order['ORDER_DATE'] ?? '', 'F j, Y, g:i A');
    $collection_date = cfo_format_mail_date($order['COLLECTION_DATE'] ?? '', 'l, d M Y');
    $collection_time = cfo_mail_escape((string)($order['COLLECTION_TIME'] ?? 'N/A'));
    $collection_location = cfo_mail_escape((string)($order['LOCATION'] ?? 'N/A'));
    $payment_method = cfo_mail_escape((string)($order['METHOD_NAME'] ?: 'On collection'));
    $payment_status = cfo_mail_escape((string)($order['PAYMENT_STATUS'] ?: 'Pending'));
    $payment_amount = cfo_money($order['PAYMENT_AMOUNT'] ?? $order['TOTAL_AMOUNT'] ?? 0);
    $order_total = cfo_money($order['TOTAL_AMOUNT'] ?? 0);
    $invoice_url = cfo_app_url('customer/invoice.php?order_id=' . rawurlencode((string)$order_id));

    $item_rows = '';
    foreach ($items as $item) {
        $item_rows .= '<tr>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;">'
            . '<strong>' . cfo_mail_escape((string)($item['SHOP_NAME'] ?? 'Trader')) . '</strong><br>'
            . '<span style="color:#4b5563;">' . cfo_mail_escape((string)($item['PRODUCT_NAME'] ?? 'Product')) . '</span>'
            . '</td>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;">' . (int)($item['QUANTITY'] ?? 0) . '</td>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;">' . cfo_money($item['PRICE'] ?? 0) . '</td>'
            . '<td style="padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;"><strong>' . cfo_money($item['LINE_TOTAL'] ?? 0) . '</strong></td>'
            . '</tr>';
    }

    if ($item_rows === '') {
        $item_rows = '<tr><td colspan="4" style="padding:14px;text-align:center;color:#6b7280;">No order items found.</td></tr>';
    }

    return '<!doctype html><html><body style="margin:0;background:#f8f6f2;font-family:Arial,sans-serif;color:#1c2b41;">'
        . '<div style="max-width:720px;margin:0 auto;padding:24px;">'
        . '<div style="background:#1c2b41;color:#fff;padding:24px;border-bottom:4px solid #ed5c2b;">'
        . '<h1 style="margin:0;font-size:24px;">Order confirmed</h1>'
        . '<p style="margin:8px 0 0;color:rgba(255,255,255,0.78);">Thanks ' . $customer_name . ', your order has been received.</p>'
        . '</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;">'
        . '<p style="margin:0 0 18px;"><strong>Order:</strong> #ORD-' . $order_id . '<br><strong>Order date:</strong> ' . cfo_mail_escape($order_date) . '<br><strong>Total:</strong> ' . $order_total . '</p>'
        . '<h2 style="font-size:16px;margin:24px 0 8px;color:#ed5c2b;">Collection details</h2>'
        . '<p style="margin:0 0 18px;"><strong>Date:</strong> ' . cfo_mail_escape($collection_date) . '<br><strong>Time:</strong> ' . $collection_time . '<br><strong>Collection point:</strong> ' . $collection_location . '</p>'
        . '<h2 style="font-size:16px;margin:24px 0 8px;color:#ed5c2b;">Payment details</h2>'
        . '<p style="margin:0 0 18px;"><strong>Method:</strong> ' . $payment_method . '<br><strong>Status:</strong> ' . $payment_status . '<br><strong>Amount:</strong> ' . $payment_amount . '</p>'
        . '<h2 style="font-size:16px;margin:24px 0 8px;color:#ed5c2b;">Items</h2>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
        . '<thead><tr style="background:#f3f4f6;"><th style="padding:10px;text-align:left;">Product</th><th style="padding:10px;text-align:center;">Qty</th><th style="padding:10px;text-align:right;">Unit</th><th style="padding:10px;text-align:right;">Subtotal</th></tr></thead>'
        . '<tbody>' . $item_rows . '</tbody></table>'
        . '<p style="margin:24px 0 0;"><a href="' . cfo_mail_escape($invoice_url) . '" style="display:inline-block;background:#ed5c2b;color:#fff;text-decoration:none;padding:12px 16px;font-weight:bold;">View invoice</a></p>'
        . '</div>'
        . '<p style="font-size:12px;color:#6b7280;margin:14px 0 0;">This is an automatic order confirmation from Cleckhuddesfax Online Mart.</p>'
        . '</div></body></html>';
}

function cfo_order_confirmation_text(array $order, array $items): string
{
    $order_id = (int)$order['ORDER_ID'];
    $lines = [
        'Order confirmed - #ORD-' . $order_id,
        '',
        'Order date: ' . cfo_format_mail_date($order['ORDER_DATE'] ?? '', 'F j, Y, g:i A'),
        'Collection date: ' . cfo_format_mail_date($order['COLLECTION_DATE'] ?? '', 'l, d M Y'),
        'Collection time: ' . (string)($order['COLLECTION_TIME'] ?? 'N/A'),
        'Collection point: ' . (string)($order['LOCATION'] ?? 'N/A'),
        'Payment method: ' . (string)($order['METHOD_NAME'] ?: 'On collection'),
        'Payment status: ' . (string)($order['PAYMENT_STATUS'] ?: 'Pending'),
        'Total: ' . cfo_money($order['TOTAL_AMOUNT'] ?? 0),
        '',
        'Items:',
    ];

    foreach ($items as $item) {
        $lines[] = '- ' . (string)($item['SHOP_NAME'] ?? 'Trader') . ': '
            . (string)($item['PRODUCT_NAME'] ?? 'Product') . ' x'
            . (int)($item['QUANTITY'] ?? 0) . ' - '
            . cfo_money($item['LINE_TOTAL'] ?? 0);
    }

    $lines[] = '';
    $lines[] = 'Invoice: ' . cfo_app_url('customer/invoice.php?order_id=' . rawurlencode((string)$order_id));

    return implode("\n", $lines);
}

function send_order_confirmation_email($conn, int $order_id): bool
{
    $data = cfo_fetch_order_mail_data($conn, $order_id);
    if (!$data) {
        error_log('[MAIL ORDER] Could not load order #' . $order_id . ' for confirmation email.');
        return false;
    }

    $order = $data['order'];
    $items = $data['items'];
    $recipient = (string)($order['EMAIL'] ?? '');
    $subject = 'Order #ORD-' . $order_id . ' confirmation - Cleckhuddesfax Online Mart';
    $html = cfo_order_confirmation_html($order, $items);
    $text = cfo_order_confirmation_text($order, $items);

    return cfo_send_html_mail($recipient, $subject, $html, $text);
}

function send_trader_approval_email(string $recipient, string $owner_name, string $business_name, string $login_email, string $temporary_password): bool
{
    $login_url = cfo_app_url('trader/login.php');
    $subject = 'Your trader account has been approved - Cleckhuddesfax Online Mart';
    $safe_owner = cfo_mail_escape($owner_name);
    $safe_business = cfo_mail_escape($business_name);
    $safe_login = cfo_mail_escape($login_email);
    $safe_password = cfo_mail_escape($temporary_password);
    $safe_login_url = cfo_mail_escape($login_url);

    $html = '<!doctype html><html><body style="margin:0;background:#f8f6f2;font-family:Arial,sans-serif;color:#1c2b41;">'
        . '<div style="max-width:680px;margin:0 auto;padding:24px;">'
        . '<div style="background:#1c2b41;color:#fff;padding:24px;border-bottom:4px solid #ed5c2b;">'
        . '<h1 style="margin:0;font-size:24px;">Trader account approved</h1>'
        . '<p style="margin:8px 0 0;color:rgba(255,255,255,0.78);">Welcome to Cleckhuddesfax Online Mart.</p>'
        . '</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;">'
        . '<p>Hello ' . $safe_owner . ',</p>'
        . '<p>Your application for <strong>' . $safe_business . '</strong> has been approved. You can now sign in to the trader portal.</p>'
        . '<div style="background:#f3f4f6;border:1px solid #e5e7eb;padding:16px;margin:18px 0;">'
        . '<p style="margin:0 0 8px;"><strong>Login email:</strong> ' . $safe_login . '</p>'
        . '<p style="margin:0;"><strong>Temporary password:</strong> ' . $safe_password . '</p>'
        . '</div>'
        . '<p style="margin:0 0 18px;">Please change this password after signing in.</p>'
        . '<p><a href="' . $safe_login_url . '" style="display:inline-block;background:#ed5c2b;color:#fff;text-decoration:none;padding:12px 16px;font-weight:bold;">Open trader login</a></p>'
        . '</div>'
        . '</div></body></html>';

    $text = "Trader account approved\n\n"
        . "Hello {$owner_name},\n\n"
        . "Your application for {$business_name} has been approved.\n"
        . "Login email: {$login_email}\n"
        . "Temporary password: {$temporary_password}\n\n"
        . "Login: {$login_url}\n\n"
        . "Please change this password after signing in.";

    return cfo_send_html_mail($recipient, $subject, $html, $text);
}

function send_trader_application_verification_email(string $recipient, string $owner_name, string $token): bool
{
    $verify_url = cfo_app_url('trader/verify_application_email.php?token=' . rawurlencode($token));
    $subject = 'Verify your trader application email - Cleckhuddesfax Online Mart';
    $safe_owner = cfo_mail_escape($owner_name);
    $safe_verify_url = cfo_mail_escape($verify_url);

    $html = '<!doctype html><html><body style="margin:0;background:#f8f6f2;font-family:Arial,sans-serif;color:#1c2b41;">'
        . '<div style="max-width:680px;margin:0 auto;padding:24px;">'
        . '<div style="background:#1c2b41;color:#fff;padding:24px;border-bottom:4px solid #ed5c2b;">'
        . '<h1 style="margin:0;font-size:24px;">Verify your email</h1>'
        . '</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;">'
        . '<p>Hello ' . $safe_owner . ',</p>'
        . '<p>Please verify this email address so the admin team can safely process your trader application.</p>'
        . '<p><a href="' . $safe_verify_url . '" style="display:inline-block;background:#ed5c2b;color:#fff;text-decoration:none;padding:12px 16px;font-weight:bold;">Verify email address</a></p>'
        . '<p style="font-size:12px;color:#6b7280;">If the button does not work, open this link: ' . $safe_verify_url . '</p>'
        . '</div>'
        . '</div></body></html>';

    $text = "Verify your trader application email\n\n"
        . "Hello {$owner_name},\n\n"
        . "Please verify this email address so the admin team can process your trader application:\n"
        . "{$verify_url}\n";

    return cfo_send_html_mail($recipient, $subject, $html, $text);
}

function send_trader_application_otp_email(string $recipient, string $owner_name, string $otp): bool
{
    $subject = 'Your trader application OTP - Cleckhuddesfax Online Mart';
    $safe_owner = cfo_mail_escape($owner_name);
    $safe_otp = cfo_mail_escape($otp);

    $html = '<!doctype html><html><body style="margin:0;background:#f8f6f2;font-family:Arial,sans-serif;color:#1c2b41;">'
        . '<div style="max-width:620px;margin:0 auto;padding:24px;">'
        . '<div style="background:#1c2b41;color:#fff;padding:24px;border-bottom:4px solid #ed5c2b;">'
        . '<h1 style="margin:0;font-size:24px;">Verify your trader email</h1>'
        . '</div>'
        . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;">'
        . '<p>Hello ' . $safe_owner . ',</p>'
        . '<p>Use this one-time code to verify the email address on your trader application:</p>'
        . '<div style="font-size:32px;letter-spacing:8px;font-weight:bold;background:#f3f4f6;border:1px solid #e5e7eb;padding:16px;text-align:center;margin:18px 0;">' . $safe_otp . '</div>'
        . '<p>This code expires in 15 minutes.</p>'
        . '</div>'
        . '</div></body></html>';

    $text = "Verify your trader email\n\n"
        . "Hello {$owner_name},\n\n"
        . "Your one-time code is: {$otp}\n"
        . "This code expires in 15 minutes.\n";

    return cfo_send_html_mail($recipient, $subject, $html, $text);
}
