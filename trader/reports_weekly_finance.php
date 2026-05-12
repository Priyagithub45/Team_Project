<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function valid_report_date(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    return $fallback;
}

function money_value($value): string
{
    return 'GBP ' . number_format((float)$value, 2);
}

$default_end = date('Y-m-d');
$default_start = date('Y-m-d', strtotime('-6 days'));
$start_date = valid_report_date($_GET['start'] ?? null, $default_start);
$end_date = valid_report_date($_GET['end'] ?? null, $default_end);
$shop_context = trader_shop_context($conn, $current_trader_id, true);
$shop_filter_sql = trader_shop_filter_sql($shop_context);

if (strtotime($start_date) > strtotime($end_date)) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$report_order_status_sql = "AND UPPER(NVL(o.STATUS, 'PENDING')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')";

$summary_sql = "
    SELECT p.PRODUCT_ID,
           p.PRODUCT_NAME,
           COUNT(DISTINCT o.ORDER_ID) AS ORDER_COUNT,
           SUM(oi.QUANTITY) AS TOTAL_QUANTITY,
           SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)) AS GROSS_REVENUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND o.ORDER_DATE >= TO_DATE(:start_date, 'YYYY-MM-DD')
      AND o.ORDER_DATE < TO_DATE(:end_date, 'YYYY-MM-DD') + 1
      {$report_order_status_sql}
    GROUP BY p.PRODUCT_ID, p.PRODUCT_NAME
    ORDER BY GROSS_REVENUE DESC, p.PRODUCT_NAME
";

$stmt = oci_parse($conn, $summary_sql);
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($stmt, ':start_date', $start_date);
oci_bind_by_name($stmt, ':end_date', $end_date);
trader_bind_shop_filter($stmt, $shop_context);
oci_execute($stmt);

$summary_rows = [];
while ($row = oci_fetch_assoc($stmt)) {
    $summary_rows[] = $row;
}
oci_free_statement($stmt);

$detail_sql = "
    SELECT o.ORDER_ID,
           o.CUSTOMER_ID,
           TO_CHAR(o.ORDER_DATE, 'YYYY-MM-DD') AS ORDER_DATE,
           p.PRODUCT_NAME,
           oi.QUANTITY,
           NVL(oi.PRICE, p.PRICE) AS UNIT_PRICE,
           (oi.QUANTITY * NVL(oi.PRICE, p.PRICE)) AS LINE_TOTAL,
           o.STATUS
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND o.ORDER_DATE >= TO_DATE(:start_date, 'YYYY-MM-DD')
      AND o.ORDER_DATE < TO_DATE(:end_date, 'YYYY-MM-DD') + 1
      {$report_order_status_sql}
    ORDER BY o.ORDER_DATE DESC, o.ORDER_ID DESC, p.PRODUCT_NAME
";

$detail_stmt = oci_parse($conn, $detail_sql);
oci_bind_by_name($detail_stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($detail_stmt, ':start_date', $start_date);
oci_bind_by_name($detail_stmt, ':end_date', $end_date);
trader_bind_shop_filter($detail_stmt, $shop_context);
oci_execute($detail_stmt);

$detail_rows = [];
while ($row = oci_fetch_assoc($detail_stmt)) {
    $detail_rows[] = $row;
}
oci_free_statement($detail_stmt);

$orders_seen = [];
$gross_revenue = 0.0;
$total_quantity = 0;
foreach ($detail_rows as $row) {
    $orders_seen[(string)$row['ORDER_ID']] = true;
    $gross_revenue += (float)$row['LINE_TOTAL'];
    $total_quantity += (int)$row['QUANTITY'];
}

$shop_name = trader_shop_context_label($shop_context, 'All shops');
$account_name = trader_account_label($current_trader);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Weekly Finance &mdash; Trader Portal</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h($account_name) ?></h2>
    <span class="sidebar-label">Trader Portal</span>
  </div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php" class="active">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php">Profile</a>
  </nav>
  <?php trader_render_shop_switcher($shop_context); ?>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Weekly Finance Report</div>

<div class="main-wrap dashboard-page">
  <section class="dashboard-hero report-hero">
    <div>
      <span class="apply-eyebrow">Trader Finance</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Revenue is calculated from your own shops only. Use the selector to split totals by shop.</p>
    </div>
    <div class="dashboard-hero-meta">
      <a href="reports_daily.php" class="btn btn-primary">Daily Orders</a>
      <small><?= h(date('d M Y', strtotime($start_date))) ?> to <?= h(date('d M Y', strtotime($end_date))) ?></small>
    </div>
  </section>

  <form method="get" action="reports_weekly_finance.php" class="report-filter-bar finance-filter-bar">
    <div class="field">
      <label for="start">Start Date</label>
      <input type="date" id="start" name="start" value="<?= h($start_date) ?>">
    </div>
    <div class="field">
      <label for="end">End Date</label>
      <input type="date" id="end" name="end" value="<?= h($end_date) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Apply Filter</button>
    <a href="reports_weekly_finance.php" class="btn btn-ghost">Last 7 Days</a>
  </form>

  <section class="dashboard-metrics report-metrics" aria-label="Weekly finance totals">
    <article class="metric-card">
      <span>Gross Revenue</span>
      <strong><?= h(money_value($gross_revenue)) ?></strong>
      <small>Sum of your product lines</small>
    </article>
    <article class="metric-card">
      <span>Orders</span>
      <strong><?= h((string)count($orders_seen)) ?></strong>
      <small>Distinct paid orders</small>
    </article>
    <article class="metric-card">
      <span>Items Sold</span>
      <strong><?= h((string)$total_quantity) ?></strong>
      <small>Total quantity sold</small>
    </article>
    <article class="metric-card">
      <span>Products</span>
      <strong><?= h((string)count($summary_rows)) ?></strong>
      <small>Products with sales</small>
    </article>
  </section>

  <section class="dashboard-grid report-two-column-grid">
    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Summary</span>
          <h2>Revenue by Product</h2>
        </div>
      </div>

      <?php if (empty($summary_rows)): ?>
        <div class="empty-state">No paid finance rows found for this date range.</div>
      <?php else: ?>
        <div class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Orders</th>
                <th>Quantity</th>
                <th>Gross Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($summary_rows as $row): ?>
                <tr>
                  <td><?= h((string)$row['PRODUCT_NAME']) ?></td>
                  <td><?= h((string)$row['ORDER_COUNT']) ?></td>
                  <td><?= h((string)$row['TOTAL_QUANTITY']) ?></td>
                  <td><strong><?= h(money_value($row['GROSS_REVENUE'])) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Breakdown</span>
          <h2>Order Line Detail</h2>
        </div>
      </div>

      <?php if (empty($detail_rows)): ?>
        <div class="empty-state">No order lines to show.</div>
      <?php else: ?>
        <div class="finance-line-list">
          <?php foreach ($detail_rows as $row): ?>
            <article>
              <div>
                <strong><?= h((string)$row['PRODUCT_NAME']) ?></strong>
                <span>Order #<?= h((string)$row['ORDER_ID']) ?> - Customer #<?= h((string)$row['CUSTOMER_ID']) ?></span>
              </div>
              <div>
                <b><?= h(money_value($row['LINE_TOTAL'])) ?></b>
                <span><?= h((string)$row['ORDER_DATE']) ?>, x<?= h((string)$row['QUANTITY']) ?></span>
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
