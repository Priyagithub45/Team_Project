# CFO Project — Full Audit Report

**Date:** 2026-05-11  
**Scope:** `trader/` and `customer/` portals  
**Stack:** PHP + Oracle (OCI8) + XAMPP

---

## Table of Contents

1. [Security](#security)
   - [No CSRF Protection](#1-no-csrf-protection)
   - [Legacy Plaintext Password Fallback](#2-legacy-plaintext-password-fallback)
   - [DB Error Leaked to User](#3-db-error-leaked-to-user)
   - [No Login Rate Limiting](#4-no-login-rate-limiting)
   - [No Forgot Password on Customer Login](#5-no-forgot-password-on-customer-login)
   - [No Password Change Feature](#6-no-password-change-feature)
2. [Performance](#performance)
   - [Auth DB Query Every Page Load](#7-auth-db-query-every-page-load)
   - [Schema Column Checks Every Request](#8-schema-column-checks-every-request)
   - [Dashboard Fires 8 Separate DB Queries](#9-dashboard-fires-8-separate-db-queries)
   - [LOCK TABLE Exclusive Mode on Every Order](#10-lock-table-exclusive-mode-on-every-order)
3. [Code Quality](#code-quality)
   - [h() Redefined in Every File](#11-h-redefined-in-every-file)
   - [Customer Profile: No Input Validation](#12-customer-profile-no-input-validation)
   - [Customer Auth Doesn't Verify DB State](#13-customer-auth-doesnt-verify-db-state)
   - [Slot Validation SQL Duplicated](#14-slot-validation-sql-duplicated)
   - [Order Status Hardcoded as Paid](#15-order-status-hardcoded-as-paid)
   - [Status Badge Always status-active](#16-status-badge-always-status-active)
   - [Dashboard Shows Customer ID Not Name](#17-dashboard-shows-customer-id-not-name)
4. [UX / Consistency](#ux--consistency)
   - [Customer Login Has All Inline Styles](#18-customer-login-has-all-inline-styles)
   - [Inline Styles in checkout.php](#19-inline-styles-in-checkoutphp)
   - [Refresh View Anchor Does Nothing](#20-refresh-view-anchor-does-nothing)
   - [No Order Cancellation for Customers](#21-no-order-cancellation-for-customers)
   - [No Out-of-Stock Warning on Product Page](#22-no-out-of-stock-warning-on-product-page)

---

## Security

### 1. No CSRF Protection

**Files affected:** Every form in both portals — `customer/login.php`, `customer/profile.php`, `customer/checkout.php`, `customer/place_order.php`, `trader/profile.php`, `trader/save_profile.php`, `trader/add_product.php`, `trader/edit_product.php`, `trader/delete_product.php`, etc.

**What it could cause:**  
An attacker hosts a page that silently submits a form to your site using the victim's active session. Examples:
- Trick a logged-in trader into deleting all their products via a hidden form on a malicious page.
- Trick a logged-in customer into placing an order or changing their address.
- No user interaction beyond visiting the attacker's link is required.

**How to fix:**

**Step 1** — Generate and store a token when the session starts. Add this to `db.php` (which every page includes):

```php
// db.php — after session_start()
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

**Step 2** — Add a hidden field to every form:

```html
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
```

**Step 3** — Validate on every POST handler at the top of the file, before any logic:

```php
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    if ($token === '' || $session_token === '' || !hash_equals($session_token, $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }
}

// Call at top of every POST-handling file:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}
```

Rotate the token after any sensitive action (password change, order placement):

```php
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
```

---

### 2. Legacy Plaintext Password Fallback

**File:** `trader/login_process.php` lines 70–76

**What it could cause:**  
If any trader account still has a plaintext password in the database (e.g. seeded test accounts), it will be accepted as-is. If the database is ever compromised, those passwords are immediately readable — no cracking required. The fallback also means you can never be sure all accounts are properly hashed.

**How to fix:**

When the plaintext fallback succeeds, immediately rehash and update the DB before continuing:

```php
// After verifying plaintext match succeeds:
if (!$password_ok && $is_legacy_plain_password) {
    $password_ok = hash_equals($stored_password, $password)
        || hash_equals($stored_password_trimmed, $password_trimmed);

    if ($password_ok) {
        // Rehash immediately — migrate away from plaintext
        $new_hash = password_hash($password, PASSWORD_BCRYPT);
        $rehash_stmt = oci_parse($conn, 'UPDATE SYSTEM_USER SET PASSWORD = :pwd WHERE USER_ID = :uid');
        oci_bind_by_name($rehash_stmt, ':pwd', $new_hash);
        $uid = (int)$user['USER_ID'];
        oci_bind_by_name($rehash_stmt, ':uid', $uid);
        oci_execute($rehash_stmt);
        oci_free_statement($rehash_stmt);
        error_log('[TRADER LOGIN] Rehashed legacy plaintext password for user_id=' . $uid);
    }
}
```

Once you confirm all accounts are migrated (check: `SELECT USER_ID FROM SYSTEM_USER WHERE PASSWORD NOT LIKE '$2y$%'`), remove the entire `$is_legacy_plain_password` block.

---

### 3. DB Error Leaked to User

**File:** `customer/profile.php` line 34

```php
// BAD — current code
$profile_error = 'Could not update profile: ' . ($err['message'] ?? 'unknown error');
```

**What it could cause:**  
Oracle error messages include table names, column names, constraint names, and sometimes data values. This gives an attacker a map of your database schema without any special tools. Example leak: `ORA-12899: value too large for column "CFO"."SYSTEM_USER"."PHONE_NO" (actual: 500, maximum: 20)` — attacker now knows the table name, column name, and max length.

**How to fix:**

```php
if (oci_execute($update_stmt)) {
    $update_message = 'Profile updated successfully!';
} else {
    $err = oci_error($update_stmt);
    error_log('[CUSTOMER PROFILE UPDATE] user_id=' . $user_id . ' error: ' . ($err['message'] ?? 'unknown'));
    $profile_error = 'Could not update profile. Please try again.'; // generic only
}
```

Apply the same pattern everywhere `oci_error()` output is shown to users.

---

### 4. No Login Rate Limiting

**Files:** `customer/login_process.php`, `trader/login_process.php`

**What it could cause:**  
An attacker can submit thousands of password attempts per second against any email address with no slowdown or lockout. Given that some accounts may use weak passwords (or legacy plaintext — see #2), credential stuffing and brute-force attacks are entirely unrestricted.

**How to fix (two-layer approach):**

**Layer 1 — PHP session-based throttle** (simple, no extra infrastructure):

```php
// At top of login_process.php, after session is available:
$attempt_key = 'login_attempts_' . preg_replace('/[^a-z0-9]/i', '_', $email);
$attempts = (int)($_SESSION[$attempt_key] ?? 0);

if ($attempts >= 5) {
    $lockout_until = $_SESSION[$attempt_key . '_until'] ?? 0;
    if (time() < $lockout_until) {
        $_SESSION['trader_login_errors'] = ['Too many failed attempts. Please wait before trying again.'];
        header('Location: login.php');
        exit;
    }
    // Lockout expired — reset
    $_SESSION[$attempt_key] = 0;
}

// On failed login, before redirecting:
$_SESSION[$attempt_key] = ($attempts + 1);
if ($_SESSION[$attempt_key] >= 5) {
    $_SESSION[$attempt_key . '_until'] = time() + 300; // 5 min lockout
}
```

**Layer 2 — Add `sleep(1)` on failed attempt** to slow down automation:

```php
// On password mismatch:
sleep(1);
$_SESSION['trader_login_errors'] = [$generic_error];
header('Location: login.php');
exit;
```

**Layer 3 (recommended for production)** — Track attempts in the DB by IP or email with a `LOGIN_ATTEMPT` table and enforce a hard lockout with admin visibility.

---

### 5. No Forgot Password on Customer Login

**File:** `customer/login.php`

**What it could cause:**  
Customers who forget their password have no self-service recovery option. They are permanently locked out of their account, leading to support burden or account abandonment. The trader portal already has `forgot_password.php` — the customer side is simply missing it.

**How to fix:**

Add a reset link to the customer login form:

```php
// In customer/login.php, below the form closing tag:
<div style="text-align:center; margin-top:12px; font-size:0.9rem;">
    <a href="forgot_password.php" style="color:#ED5C2B;">Forgot your password?</a>
</div>
```

Then create `customer/forgot_password.php` following the same pattern as `trader/forgot_password.php`:
1. Accept email input
2. Look up `SYSTEM_USER` where `ROLE = 'customer'` (or via `CUSTOMER` join)
3. Generate a secure token: `bin2hex(random_bytes(32))`
4. Store token + expiry in a `PASSWORD_RESET` table (create if not exists)
5. Email the link using PHP `mail()` or a library
6. On token redemption, validate expiry (15–60 min), accept new password, hash it, update DB, invalidate token

---

### 6. No Password Change Feature

**Files:** Neither `customer/profile.php` nor `trader/profile.php` have this.

**What it could cause:**  
Users cannot rotate compromised credentials without admin intervention. If a password is leaked (phishing, reuse on another breached site), the account stays compromised indefinitely. Also blocks security hygiene for legitimate users.

**How to fix:**

Add a password change section to both profile pages. In the POST handler:

```php
// customer/profile.php POST handler addition:
if (isset($_POST['change_password'])) {
    $current_pw  = $_POST['current_password'] ?? '';
    $new_pw      = $_POST['new_password'] ?? '';
    $confirm_pw  = $_POST['confirm_password'] ?? '';

    // Fetch current hash
    $stmt = oci_parse($conn, 'SELECT PASSWORD FROM SYSTEM_USER WHERE USER_ID = :uid');
    oci_bind_by_name($stmt, ':uid', $user_id);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$row || !password_verify($current_pw, $row['PASSWORD'])) {
        $profile_error = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 8) {
        $profile_error = 'New password must be at least 8 characters.';
    } elseif ($new_pw !== $confirm_pw) {
        $profile_error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = oci_parse($conn, 'UPDATE SYSTEM_USER SET PASSWORD = :pwd WHERE USER_ID = :uid');
        oci_bind_by_name($stmt, ':pwd', $hash);
        oci_bind_by_name($stmt, ':uid', $user_id);
        oci_execute($stmt);
        oci_free_statement($stmt);
        session_regenerate_id(true);
        $update_message = 'Password changed successfully.';
    }
}
```

In the form, add three `<input type="password">` fields: current, new, confirm.

---

## Performance

### 7. Auth DB Query Every Page Load

**File:** `trader/auth_check.php` lines 17–58

**What it could cause:**  
Every trader page (dashboard, products, reports, profile, etc.) runs a 3-table JOIN against Oracle on every single request. Under moderate load (e.g. 5 traders each navigating 10 pages), that's 50 unnecessary DB queries. Latency compounds on every page load. Oracle connections are not cheap.

**How to fix:**

Cache the trader record in the session with a short TTL. Only re-query when the cache is stale:

```php
// In trader/auth_check.php, replace the always-query approach:

$cache_ttl = 300; // 5 minutes
$needs_refresh = empty($_SESSION['trader_cache'])
    || (time() - ($_SESSION['trader_cache_at'] ?? 0)) > $cache_ttl;

if ($needs_refresh) {
    // Run existing $auth_sql query here...
    $current_trader = oci_fetch_assoc($auth_stmt);
    oci_free_statement($auth_stmt);

    if (!$current_trader) {
        // ... existing redirect logic
    }

    $_SESSION['trader_cache']    = $current_trader;
    $_SESSION['trader_cache_at'] = time();
} else {
    $current_trader = $_SESSION['trader_cache'];
}

// Status checks remain the same...
```

On profile save or logout, invalidate: `unset($_SESSION['trader_cache'], $_SESSION['trader_cache_at']);`

---

### 8. Schema Column Checks Every Request

**Files:** `trader/profile_helpers.php` (both functions), `customer/category.php` (`category_column_exists()`)

**What it could cause:**  
`USER_TAB_COLUMNS` queries run on every page load that includes these helpers. After migrations 007 and 009 are applied, these checks are permanently `true` but still hit the DB every request. This is wasted I/O with zero benefit post-migration.

**How to fix (short term)** — Cache in session:

```php
function trader_shop_image_column_exists($conn): bool
{
    if (isset($_SESSION['_col_shop_image_exists'])) {
        return (bool)$_SESSION['_col_shop_image_exists'];
    }

    $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM USER_TAB_COLUMNS
                              WHERE TABLE_NAME = 'SHOP' AND COLUMN_NAME = 'IMAGE_PATH'");
    if (!$stmt || !oci_execute($stmt)) {
        return false;
    }
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $exists = ((int)($row['CNT'] ?? 0) > 0);
    $_SESSION['_col_shop_image_exists'] = $exists;
    return $exists;
}
```

**How to fix (long term)** — Once both migrations are confirmed applied to all environments, delete these functions entirely and inline the columns directly in queries. Replace all `if ($col_exists)` branches with the "exists" branch only.

---

### 9. Dashboard Fires 8 Separate DB Queries

**File:** `trader/dashboard.php` lines 80–179

**What it could cause:**  
8 round-trips to Oracle per dashboard page load. Each query waits for the previous to return. On slow network or loaded DB, this stacks latency — a dashboard that should render in 100ms can take 800ms+. It also wastes connection time.

**How to fix:**

Consolidate the 6 scalar metrics into one query using conditional aggregation:

```sql
SELECT
    COUNT(DISTINCT CASE WHEN NVL(p.STOCK_QUANTITY, 0) > 0 THEN p.PRODUCT_ID END) AS TOTAL_PRODUCTS,
    COUNT(DISTINCT CASE WHEN NVL(p.STOCK_QUANTITY, 0) BETWEEN 1 AND 5 THEN p.PRODUCT_ID END) AS LOW_STOCK,
    COUNT(DISTINCT CASE WHEN NVL(p.STOCK_QUANTITY, 0) <= 0 THEN p.PRODUCT_ID END) AS OUT_OF_STOCK
FROM PRODUCT p
JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
WHERE s.TRADER_ID = :trader_id
```

Run revenue and order count in a second query, inventory rows in a third, upcoming orders in a fourth. That is 4 queries instead of 8.

---

### 10. LOCK TABLE Exclusive Mode on Every Order

**File:** `customer/place_order.php` lines 244–252

```php
// Current code — serialises ALL checkouts system-wide
$stmt = oci_parse($conn, 'LOCK TABLE ORDER_ITEM IN EXCLUSIVE MODE');
```

**What it could cause:**  
`IN EXCLUSIVE MODE` on `ORDER_ITEM` means only **one order** can be inserting at a time across the entire system. Every other checkout blocks and waits. Under any real concurrent load, checkout becomes a queue. One slow transaction (network hiccup, slow client) blocks every other user from completing purchase.

**How to fix:**

Create an Oracle sequence for `ORDER_ITEM_ID`:

```sql
-- Run once in SQL*Plus or your migration script:
CREATE SEQUENCE ORDER_ITEM_SEQ
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;
```

Then replace the table lock and MAX(ID) approach in `place_order.php`:

```php
// Remove these lines entirely:
// $stmt = oci_parse($conn, 'LOCK TABLE ORDER_ITEM IN EXCLUSIVE MODE');
// $stmt = oci_parse($conn, 'SELECT NVL(MAX(ORDER_ITEM_ID), 0) AS MAX_ID FROM ORDER_ITEM');
// $next_order_item_id = (int)$row['MAX_ID'] + 1;

// Replace INSERT with sequence:
$stmt = oci_parse($conn, "INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, PRICE)
                          VALUES (ORDER_ITEM_SEQ.NEXTVAL, :p_oid, :p_pid, :p_qty, :p_price)");
// Remove :p_iid bind — sequence handles ID generation
```

Checkouts now run in parallel with no lock contention.

---

## Code Quality

### 11. h() Redefined in Every File

**Files:** `trader/dashboard.php`, `trader/profile.php`, `trader/reports_monthly_sales.php`, `trader/reports_weekly_finance.php`, `trader/reports_daily.php`, `customer/category.php`, `customer/save_profile.php`, and more.

**What it could cause:**  
If the escaping logic ever needs to change (e.g. switching encoding, adding additional sanitisation), it must be updated in every file individually. Easy to miss one. Also a PHP fatal error if any file includes another that already defined `h()`.

**How to fix:**

Create `shared/helpers.php` (or add to an existing shared include):

```php
<?php
// shared/helpers.php

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
```

In `db.php` (included by everything), add:

```php
require_once __DIR__ . '/shared/helpers.php';
```

Delete the per-file `h()` definitions.

---

### 12. Customer Profile: No Input Validation

**File:** `customer/profile.php` lines 18–20

```php
$new_phone   = trim($_POST['phone'] ?? '');
$new_address = trim($_POST['address'] ?? '');
// No length or format checks before UPDATE
```

**What it could cause:**  
A 10,000-character phone number or address goes straight into the DB. Oracle will throw an error (which is then leaked — see #3). Malformed data can break display logic elsewhere. Consistent with how trader profile validates, but customer profile skips it entirely.

**How to fix:**

```php
$errors = [];

$new_phone   = trim($_POST['phone'] ?? '');
$new_address = trim($_POST['address'] ?? '');

if ($new_phone !== '' && !preg_match('/^[0-9\+\-\s\(\)]{5,20}$/', $new_phone)) {
    $errors[] = 'Phone number must be 5–20 characters, digits and common symbols only.';
}
if (strlen($new_phone) > 20) {
    $errors[] = 'Phone number must be 20 characters or fewer.';
}
if (strlen($new_address) > 200) {
    $errors[] = 'Address must be 200 characters or fewer.';
}

if (empty($errors)) {
    // Run UPDATE
} else {
    $profile_error = implode(' ', $errors);
}
```

---

### 13. Customer Auth Doesn't Verify DB State

**File:** `customer/auth_check.php` lines 11–18

```php
// Current — trusts session only
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    // redirect
}
```

**What it could cause:**  
If an admin suspends a customer account in the DB, that customer's active session continues to work indefinitely until they log out or the session expires. They can keep placing orders on a suspended account. The trader portal re-queries the DB on every page and would block a suspended trader immediately — customers have no equivalent protection.

**How to fix:**

Apply the same session-cached DB check pattern as trader auth (see fix #7), but scoped to customers:

```php
$cache_ttl   = 300;
$needs_check = empty($_SESSION['customer_cache'])
    || (time() - ($_SESSION['customer_cache_at'] ?? 0)) > $cache_ttl;

if ($needs_check) {
    $uid  = (int)$_SESSION['user_id'];
    $stmt = oci_parse($conn, "SELECT su.STATUS AS USER_STATUS
                              FROM SYSTEM_USER su
                              JOIN CUSTOMER c ON c.USER_ID = su.USER_ID
                              WHERE su.USER_ID = :uid");
    oci_bind_by_name($stmt, ':uid', $uid);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if (!$row || !in_array(strtoupper(trim($row['USER_STATUS'] ?? 'ACTIVE')), ['ACTIVE', 'APPROVED'], true)) {
        session_destroy();
        session_start();
        $_SESSION['login_errors'] = ['Your account has been suspended. Please contact support.'];
        header('Location: login.php');
        exit;
    }

    $_SESSION['customer_cache']    = true;
    $_SESSION['customer_cache_at'] = time();
}
```

---

### 14. Slot Validation SQL Duplicated

**Files:** `customer/checkout.php` lines 26–29, `customer/place_order.php` lines 94–101, `customer/collection-slot.php`

**What it could cause:**  
The `$allowed_slot_rules_sql` string and the time expression `REPLACE(REPLACE(...))` exist in multiple files. If business rules change (e.g. adding Saturday slots, changing capacity from 20 to 30), each file must be updated independently. Missing one means checkout and order placement enforce different rules — orders can be placed for slots the UI already rejected, or vice versa.

**How to fix:**

Create `customer/slot_helpers.php`:

```php
<?php
// customer/slot_helpers.php

function slot_time_expr(string $alias = ''): string
{
    $col = $alias !== '' ? "$alias.COLLECTION_TIME" : 'COLLECTION_TIME';
    return "REPLACE(REPLACE($col, ' ', ''), ':00', '')";
}

function slot_allowed_rules_sql(string $alias = 'cs'): string
{
    $time_expr = slot_time_expr($alias);
    return "
        {$alias}.COLLECTION_DATE >= SYSDATE + 1
        AND TO_CHAR({$alias}.COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
        AND {$time_expr} IN ('10-13','13-16','16-19')
        AND (20 - (SELECT COUNT(*) FROM ORDERS WHERE SLOT_ID = {$alias}.SLOT_ID)) > 0
    ";
}

function slot_time_label(string $t): string
{
    $slot = str_replace([' ', ':00'], '', $t);
    $map  = ['10-13' => '10:00–13:00', '13-16' => '13:00–16:00', '16-19' => '16:00–19:00'];
    return $map[$slot] ?? $t;
}
```

Include it in `checkout.php`, `place_order.php`, and `collection-slot.php`, then call `slot_allowed_rules_sql()` instead of the inline string.

---

### 15. Order Status Hardcoded as 'Paid'

**File:** `customer/place_order.php` line 229

```php
VALUES (ORDER_SEQ.NEXTVAL, :p_uid, :p_sid, :p_total, SYSDATE, 'Paid')
```

**What it could cause:**  
Cash orders (no PayPal) are immediately marked `'Paid'` before any money changes hands. From the trader's view, every order looks paid — there is no way to distinguish between "paid online" and "payment pending on collection." If a customer no-shows, the order already shows as paid in reports. This inflates trader revenue figures.

**How to fix:**

Use a status based on payment method:

```php
$order_status = ($method_id !== null && $is_paypal_return) ? 'Paid' : 'Pending';

$stmt = oci_parse($conn, "INSERT INTO ORDERS (ORDER_ID, CUSTOMER_ID, SLOT_ID, TOTAL_AMOUNT, ORDER_DATE, STATUS)
                          VALUES (ORDER_SEQ.NEXTVAL, :p_uid, :p_sid, :p_total, SYSDATE, :p_status)
                          RETURNING ORDER_ID INTO :p_oid");
oci_bind_by_name($stmt, ':p_status', $order_status);
// ... rest of binds
```

Also update the trader report filter to include `'PENDING'` in the "upcoming orders" count where appropriate, and exclude it from revenue totals.

---

### 16. Status Badge Always status-active

**File:** `trader/profile.php` lines 84–85

```php
<span class="status-box status-active">User: <?= h($user_status) ?></span>
<span class="status-box status-active">Trader: <?= h($trader_status) ?></span>
```

**What it could cause:**  
A suspended trader's profile page shows a green "ACTIVE" badge regardless of their real status. This is misleading — the trader cannot tell from the UI whether their account is actually active or not.

**How to fix:**

```php
<?php
function status_class(string $status): string
{
    return in_array(strtoupper($status), ['ACTIVE', 'APPROVED'], true)
        ? 'status-active'
        : 'status-out';
}
?>

<span class="status-box <?= h(status_class($user_status)) ?>">User: <?= h($user_status) ?></span>
<span class="status-box <?= h(status_class($trader_status)) ?>">Trader: <?= h($trader_status) ?></span>
```

Apply the same fix to `trader/dashboard.php` hero status badge.

---

### 17. Dashboard Shows Customer ID Not Name

**File:** `trader/dashboard.php` line 347

```php
<span>Order #<?= h((string)$order['ORDER_ID']) ?> - Customer #<?= h((string)$order['CUSTOMER_ID']) ?></span>
```

**What it could cause:**  
Traders cannot identify who ordered what at a glance. They see "Customer #4" instead of a name, forcing them to look up orders manually. Also, leaking internal DB IDs through the UI is unnecessary.

**How to fix:**

Join `SYSTEM_USER` in the `$upcoming_rows` query in `dashboard.php`:

```sql
SELECT o.ORDER_ID,
       su.NAME AS CUSTOMER_NAME,         -- add this
       p.PRODUCT_NAME,
       oi.QUANTITY,
       TO_CHAR(cs.COLLECTION_DATE, 'YYYY-MM-DD') AS COLLECTION_DATE,
       cs.COLLECTION_TIME,
       o.STATUS
FROM ORDERS o
JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
JOIN SYSTEM_USER su ON su.USER_ID = o.CUSTOMER_ID   -- add this
WHERE s.TRADER_ID = :trader_id
  AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
  AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
ORDER BY cs.COLLECTION_DATE, cs.COLLECTION_TIME, o.ORDER_ID, p.PRODUCT_NAME
```

Then in the template:

```php
<span>Order #<?= h((string)$order['ORDER_ID']) ?> – <?= h((string)$order['CUSTOMER_NAME']) ?></span>
```

---

## UX / Consistency

### 18. Customer Login Has All Inline Styles

**File:** `customer/login.php` lines 30–68

**What it could cause:**  
The customer CSS (`assets/css/style.css`) defines `.form-page`, `.container-narrow`, `.btn-primary`, `.btn-outline`, and `.auth-error` — none of these are used on the login page. Every style is hardcoded as `style="..."`. This means:
- Login page looks visually inconsistent with the rest of the site.
- Any global style change (font, border radius, color) won't apply to login.
- Harder to maintain — CSS changes require editing PHP files.

**How to fix:**

Replace inline styles with existing CSS classes. The login form should use:

```php
<section class="form-page">
    <div class="container container-narrow" style="max-width:420px;">
        <h2 style="text-align:center; margin-bottom:24px;">Login</h2>

        <?php if ($success): ?>
            <div class="auth-error" style="background:#d1fae5;color:#065f46;border-color:#6ee7b7;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="auth-error">
                <ul><?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login_process.php">
            <!-- fields using .form-group, .btn-primary etc. -->
        </form>
    </div>
</section>
```

If `.auth-form` or `.form-group` classes don't exist yet, add them to `style.css` under the **AUTH PAGES** section that already exists (section 11 in the CSS table of contents).

---

### 19. Inline Styles in checkout.php

**File:** `customer/checkout.php` — alert divs, table heading, payment select, buttons

**What it could cause:**  
Same problem as #18. Checkout is a critical conversion page. Any visual regressions (colours, spacing) require hunting through inline styles rather than updating a stylesheet. Also, error/notice alerts use raw hex colours instead of the design token variables already defined (`--clr-orange`, etc.).

**How to fix:**

Add utility classes to `style.css`:

```css
/* In style.css, under INVOICE PAGE section or a new CHECKOUT section */
.alert-error   { background: #fee2e2; color: #991b1b; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
.alert-success { background: #d1fae5; color: #065f46; padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
.payment-select { width: 100%; padding: .6rem; border: 1px solid var(--clr-border); border-radius: 4px; font-family: inherit; }
```

Replace inline-style divs in `checkout.php` with class attributes.

---

### 20. Refresh View Anchor Does Nothing

**File:** `trader/dashboard.php` line 337

```php
<a href="#collection-orders" class="panel-link">Refresh view</a>
```

**What it could cause:**  
Clicking "Refresh view" just scrolls to the section anchor — it does not reload data. A trader preparing orders who wants to see the latest state must manually refresh the whole page. It creates a false expectation that data has been updated.

**How to fix (simple):**

Change to a page reload that anchors to the section:

```php
<a href="dashboard.php#collection-orders" class="panel-link">Refresh</a>
```

**How to fix (better):**

Replace with a lightweight JS fetch that reloads just the collection orders panel:

```html
<button class="panel-link" id="refresh-orders-btn">Refresh</button>
<script>
document.getElementById('refresh-orders-btn').addEventListener('click', function() {
    window.location.href = 'dashboard.php#collection-orders';
});
</script>
```

Or extract the collection orders panel into `dashboard_orders_fragment.php` and use `fetch()` + `innerHTML` to update only that panel without a full page reload.

---

### 21. No Order Cancellation for Customers

**File:** `customer/profile.php` — order history table has "View Invoice" only

**What it could cause:**  
A customer who orders by mistake has no self-service way to cancel. They must contact someone (no contact mechanism is obvious on the portal). Orders in `'Paid'` status with a future collection date sit in the trader's queue needlessly, inflating upcoming order counts and potentially holding stock.

**How to fix:**

Add a cancel button visible only for cancellable orders (e.g. status `Pending` or `Paid`, collection date > today):

```php
// In the order table action column:
$is_cancellable = in_array($order['ORDER_STATUS'], ['Pending', 'Paid'], true)
    && strtotime(substr($order['COLLECTION_DATE'] ?? '', 0, 10)) > time();

if ($is_cancellable): ?>
    <form method="post" action="cancel_order.php" style="display:inline;"
          onsubmit="return confirm('Cancel this order?')">
        <input type="hidden" name="order_id" value="<?= (int)$order['ORDER_ID'] ?>">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <button type="submit" class="order-action-link" style="color:#dc2626;background:none;border:none;cursor:pointer;">
            Cancel
        </button>
    </form>
<?php endif;
```

Create `customer/cancel_order.php` that:
1. Verifies CSRF
2. Confirms the order belongs to `$_SESSION['user_id']`
3. Checks it is still in a cancellable status
4. Updates `STATUS = 'Cancelled'`
5. Restores stock quantities for each `ORDER_ITEM`
6. Redirects back to profile with a flash message

---

### 22. No Out-of-Stock Warning on Product Page

**Files:** `customer/product.php`, `customer/add_to_cart.php`, `customer/buy_now.php`

**What it could cause:**  
A customer views a product, clicks "Add to Cart" or "Buy Now," proceeds all the way through slot selection and checkout, then hits an error in `place_order.php` when the Oracle trigger rejects the insert due to zero stock. This is a poor experience — the customer wasted time selecting a slot and reaching checkout only to be rejected at the final step.

**How to fix:**

In `customer/product.php`, fetch `STOCK_QUANTITY` alongside product details:

```php
$stmt = oci_parse($conn, "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE,
                                  p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER,
                                  s.SHOP_NAME
                          FROM PRODUCT p
                          JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
                          WHERE p.PRODUCT_ID = :pid");
```

Then in the template, show stock state:

```php
<?php if ((int)$product['STOCK_QUANTITY'] <= 0): ?>
    <p class="out-of-stock-label">Out of stock</p>
    <button disabled class="btn btn-primary">Add to Cart</button>
<?php else: ?>
    <form method="post" action="add_to_cart.php">
        <!-- normal form -->
    </form>
<?php endif; ?>
```

Also add a low-stock warning:

```php
<?php if ((int)$product['STOCK_QUANTITY'] > 0 && (int)$product['STOCK_QUANTITY'] <= 5): ?>
    <p class="low-stock-label">Only <?= (int)$product['STOCK_QUANTITY'] ?> left</p>
<?php endif; ?>
```

Add `.out-of-stock-label` and `.low-stock-label` to `style.css` with red/amber colours respectively.

---

## Priority Order

| Priority | Issue | Effort |
|----------|-------|--------|
| Critical | #1 CSRF protection | Medium |
| Critical | #2 Legacy plaintext password rehash | Low |
| Critical | #10 LOCK TABLE → sequence | Low |
| High | #3 DB error leak | Low |
| High | #4 Rate limiting | Medium |
| High | #13 Customer auth DB check | Medium |
| High | #12 Customer profile validation | Low |
| Medium | #7 Auth cache | Medium |
| Medium | #8 Schema check cache | Low |
| Medium | #15 Order status logic | Medium |
| Medium | #21 Order cancellation | High |
| Medium | #22 Out-of-stock on product page | Low |
| Low | #9 Dashboard query consolidation | Medium |
| Low | #11 h() shared helper | Low |
| Low | #14 Slot SQL shared helper | Low |
| Low | #16 Status badge class | Low |
| Low | #17 Customer name in dashboard | Low |
| Low | #18 Customer login CSS | Low |
| Low | #19 Checkout inline styles | Low |
| Low | #20 Refresh anchor fix | Low |
| Low | #5 Customer forgot password | High |
| Low | #6 Password change | High |
