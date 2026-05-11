<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

$flash_success = $_SESSION['trader_flash_success'] ?? '';
unset($_SESSION['trader_flash_success']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function money_value($value): string
{
    return 'GBP ' . number_format((float)$value, 2);
}

function dashboard_scalar($conn, string $sql, int $trader_id, $default = 0)
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER DASHBOARD PARSE] ' . ($err['message'] ?? 'unknown error'));
        return $default;
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);

    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[TRADER DASHBOARD QUERY] ' . ($err['message'] ?? 'unknown error'));
        oci_free_statement($stmt);
        return $default;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return $row ? ($row['DASH_VALUE'] ?? $default) : $default;
}

function dashboard_rows($conn, string $sql, int $trader_id): array
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER DASHBOARD PARSE] ' . ($err['message'] ?? 'unknown error'));
        return [];
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);

    if (!oci_execute($stmt)) {
        $err = oci_error($stmt);
        error_log('[TRADER DASHBOARD QUERY] ' . ($err['message'] ?? 'unknown error'));
        oci_free_statement($stmt);
        return [];
    }

    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }
    oci_free_statement($stmt);

    return $rows;
}

$trader_id = (int)$current_trader_id;
$current_shop = trader_current_shop($conn, $trader_id);
$shop_name = (string)($current_shop['SHOP_NAME'] ?? $current_trader['SHOP_NAME'] ?? $current_trader['BUSINESS_NAME'] ?? 'Your Shop');
$trader_name = (string)($current_trader['NAME'] ?? 'Trader');
$trader_status = strtoupper(trim((string)($current_trader['TRADER_STATUS'] ?? 'ACTIVE')));
if ($trader_status === '') {
    $trader_status = 'ACTIVE';
}
$active_product_filter = trader_product_status_column_exists($conn)
    ? "AND NVL(UPPER(p.STATUS), 'ACTIVE') = 'ACTIVE'"
    : '';

$total_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$active_product_filter}
", $trader_id);

$low_stock_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$active_product_filter}
      AND NVL(p.STOCK_QUANTITY, 0) BETWEEN 1 AND 5
", $trader_id);

$out_of_stock_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$active_product_filter}
      AND NVL(p.STOCK_QUANTITY, 0) <= 0
", $trader_id);

$upcoming_order_count = (int)dashboard_scalar($conn, "
    SELECT COUNT(DISTINCT o.ORDER_ID) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
      AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
", $trader_id);

$week_revenue = dashboard_scalar($conn, "
    SELECT NVL(SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)), 0) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      AND o.ORDER_DATE >= TRUNC(SYSDATE) - 6
      AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')
", $trader_id, 0);

$month_quantity_sold = (int)dashboard_scalar($conn, "
    SELECT NVL(SUM(oi.QUANTITY), 0) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(o.ORDER_DATE, 'MM') = TRUNC(SYSDATE, 'MM')
      AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')
", $trader_id);

$inventory_rows = dashboard_rows($conn, "
    SELECT *
    FROM (
        SELECT p.PRODUCT_ID,
               p.PRODUCT_NAME,
               p.PRICE,
               NVL(p.STOCK_QUANTITY, 0) AS STOCK_QUANTITY,
               c.CATEGORY_NAME
        FROM PRODUCT p
        JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
        LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
        WHERE s.TRADER_ID = :trader_id
          {$active_product_filter}
        ORDER BY p.PRODUCT_ID DESC
    )
    WHERE ROWNUM <= 8
", $trader_id);

$upcoming_rows = dashboard_rows($conn, "
    SELECT *
    FROM (
        SELECT o.ORDER_ID,
               o.CUSTOMER_ID,
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
        WHERE s.TRADER_ID = :trader_id
          AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
          AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
        ORDER BY cs.COLLECTION_DATE, cs.COLLECTION_TIME, o.ORDER_ID, p.PRODUCT_NAME
    )
    WHERE ROWNUM <= 8
", $trader_id);

function stock_badge_class(int $stock): string
{
    if ($stock <= 0) {
        return 'status-out';
    }
    if ($stock <= 5) {
        return 'status-low';
    }
    return 'status-active';
}

function stock_label(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of stock';
    }
    if ($stock <= 5) {
        return 'Low stock';
    }
    return 'Active';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trader Dashboard &mdash; <?= h($shop_name) ?></title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h($shop_name) ?></h2>
    <span class="sidebar-label">Trader Portal</span>
  </div>
  <nav>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php">Profile</a>
  </nav>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Trader Dashboard</div>

<div class="main-wrap dashboard-page">
  <?php if ($flash_success !== ''): ?>
    <div class="alert alert-success"><?= h($flash_success) ?></div>
  <?php endif; ?>

  <section class="dashboard-hero">
    <div>
      <span class="apply-eyebrow">Private Trader Workspace</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Signed in as <?= h($trader_name) ?>. Every figure below is scoped to your trader account only.</p>
    </div>
    <div class="dashboard-hero-meta">
      <span class="status-box status-active"><?= h($trader_status) ?></span>
      <small>Trader ID <?= h((string)$trader_id) ?></small>
    </div>
  </section>

  <section class="dashboard-actions" aria-label="Quick actions">
    <a href="add_product.php">Add Product</a>
    <a href="products.php">Manage Products</a>
    <a href="#collection-orders">Collection Orders</a>
    <a href="reports_daily.php">Daily Report</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php">Edit Profile</a>
  </section>

  <section class="dashboard-metrics" aria-label="Trader dashboard metrics">
    <article class="metric-card">
      <span>Total Products</span>
      <strong><?= h((string)$total_products) ?></strong>
      <small><?= h((string)$out_of_stock_products) ?> out of stock</small>
    </article>
    <article class="metric-card">
      <span>Low Stock</span>
      <strong><?= h((string)$low_stock_products) ?></strong>
      <small>Stock quantity 1 to 5</small>
    </article>
    <article class="metric-card">
      <span>Upcoming Orders</span>
      <strong><?= h((string)$upcoming_order_count) ?></strong>
      <small>Future collection slots</small>
    </article>
    <article class="metric-card">
      <span>This Week Revenue</span>
      <strong><?= h(money_value($week_revenue)) ?></strong>
      <small>Paid and active orders</small>
    </article>
    <article class="metric-card">
      <span>This Month Sold</span>
      <strong><?= h((string)$month_quantity_sold) ?></strong>
      <small>Total item quantity</small>
    </article>
  </section>

  <section class="dashboard-grid">
    <div class="dashboard-panel" id="inventory">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Inventory</span>
          <h2>Recent Products</h2>
        </div>
        <a href="add_product.php" class="panel-link">Add product</a>
      </div>

      <?php if (empty($inventory_rows)): ?>
        <div class="empty-state">No products belong to this trader yet.</div>
      <?php else: ?>
        <div class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventory_rows as $product): ?>
                <?php $stock = (int)$product['STOCK_QUANTITY']; ?>
                <tr>
                  <td><?= h((string)$product['PRODUCT_NAME']) ?></td>
                  <td><?= h((string)($product['CATEGORY_NAME'] ?? '-')) ?></td>
                  <td><?= h(money_value($product['PRICE'] ?? 0)) ?></td>
                  <td><?= h((string)$stock) ?></td>
                  <td><span class="status-box <?= h(stock_badge_class($stock)) ?>"><?= h(stock_label($stock)) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="dashboard-panel" id="collection-orders">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Operations</span>
          <h2>Next Collection Items</h2>
        </div>
        <a href="#collection-orders" class="panel-link">Refresh view</a>
      </div>

      <?php if (empty($upcoming_rows)): ?>
        <div class="empty-state">No upcoming collection orders for your shop.</div>
      <?php else: ?>
        <div class="collection-list">
          <?php foreach ($upcoming_rows as $order): ?>
            <article class="collection-item">
              <div>
                <strong><?= h((string)$order['PRODUCT_NAME']) ?></strong>
                <span>Order #<?= h((string)$order['ORDER_ID']) ?> - Customer #<?= h((string)$order['CUSTOMER_ID']) ?></span>
              </div>
              <div class="collection-meta">
                <b>x<?= h((string)$order['QUANTITY']) ?></b>
                <span><?= h((string)$order['COLLECTION_DATE']) ?>, <?= h((string)$order['COLLECTION_TIME']) ?></span>
                <small><?= h((string)($order['STATUS'] ?? 'Paid')) ?></small>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
