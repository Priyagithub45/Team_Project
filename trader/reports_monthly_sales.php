<?php
require_once 'auth_check.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function valid_report_month(?string $value): string
{
    $value = trim((string)$value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}$/', $value)) {
        return $value;
    }

    return date('Y-m');
}

function money_value($value): string
{
    return 'GBP ' . number_format((float)$value, 2);
}

function polar_point(float $cx, float $cy, float $radius, float $angle): array
{
    $radians = deg2rad($angle - 90);
    return [
        $cx + ($radius * cos($radians)),
        $cy + ($radius * sin($radians)),
    ];
}

function donut_segment_path(float $cx, float $cy, float $outer, float $inner, float $start, float $end): string
{
    if (($end - $start) >= 359.99) {
        $end = $start + 359.99;
    }

    [$outer_start_x, $outer_start_y] = polar_point($cx, $cy, $outer, $start);
    [$outer_end_x, $outer_end_y] = polar_point($cx, $cy, $outer, $end);
    [$inner_end_x, $inner_end_y] = polar_point($cx, $cy, $inner, $end);
    [$inner_start_x, $inner_start_y] = polar_point($cx, $cy, $inner, $start);
    $large_arc = ($end - $start) > 180 ? 1 : 0;

    return sprintf(
        'M %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 0 %.3f %.3f Z',
        $outer_start_x,
        $outer_start_y,
        $outer,
        $outer,
        $large_arc,
        $outer_end_x,
        $outer_end_y,
        $inner_end_x,
        $inner_end_y,
        $inner,
        $inner,
        $large_arc,
        $inner_start_x,
        $inner_start_y
    );
}

$selected_month = valid_report_month($_GET['month'] ?? null);
$sort = strtoupper(trim((string)($_GET['sort'] ?? 'INCOME')));
$sort_options = [
    'NAME' => 'p.PRODUCT_NAME ASC',
    'ORDERS' => 'TOTAL_ORDERS DESC, p.PRODUCT_NAME ASC',
    'INCOME' => 'TOTAL_INCOME DESC, p.PRODUCT_NAME ASC',
];
if (!isset($sort_options[$sort])) {
    $sort = 'INCOME';
}
$order_by = $sort_options[$sort];
$report_order_status_sql = "AND UPPER(NVL(o.STATUS, 'PENDING')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')";

$sales_sql = "
    SELECT p.PRODUCT_ID,
           p.PRODUCT_NAME,
           COUNT(DISTINCT o.ORDER_ID) AS TOTAL_ORDERS,
           SUM(oi.QUANTITY) AS TOTAL_QUANTITY,
           SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)) AS TOTAL_INCOME
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(o.ORDER_DATE, 'MM') = TO_DATE(:selected_month || '-01', 'YYYY-MM-DD')
      {$report_order_status_sql}
    GROUP BY p.PRODUCT_ID, p.PRODUCT_NAME
    ORDER BY {$order_by}
";

$stmt = oci_parse($conn, $sales_sql);
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($stmt, ':selected_month', $selected_month);
oci_execute($stmt);

$sales_rows = [];
while ($row = oci_fetch_assoc($stmt)) {
    $sales_rows[] = $row;
}
oci_free_statement($stmt);

$totals_sql = "
    SELECT COUNT(DISTINCT o.ORDER_ID) AS DISTINCT_ORDERS,
           NVL(SUM(oi.QUANTITY), 0) AS TOTAL_QUANTITY,
           NVL(SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)), 0) AS TOTAL_INCOME
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(o.ORDER_DATE, 'MM') = TO_DATE(:selected_month || '-01', 'YYYY-MM-DD')
      {$report_order_status_sql}
";

$totals_stmt = oci_parse($conn, $totals_sql);
oci_bind_by_name($totals_stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($totals_stmt, ':selected_month', $selected_month);
oci_execute($totals_stmt);
$totals = oci_fetch_assoc($totals_stmt) ?: [];
oci_free_statement($totals_stmt);

$total_orders = (int)($totals['DISTINCT_ORDERS'] ?? 0);
$total_quantity = (int)($totals['TOTAL_QUANTITY'] ?? 0);
$total_income = (float)($totals['TOTAL_INCOME'] ?? 0);
$max_income = 0.0;
foreach ($sales_rows as $row) {
    $income = (float)$row['TOTAL_INCOME'];
    $max_income = max($max_income, $income);
}

$chart_colors = ['#2f9ed8', '#43ad83', '#ffd04a', '#e85b58', '#8752a3', '#35bdb7', '#f47d35', '#ef5b8f', '#18b8cc', '#6f7bd9'];
$chart_rows = $sales_rows;
usort($chart_rows, static fn(array $a, array $b): int => (float)$b['TOTAL_INCOME'] <=> (float)$a['TOTAL_INCOME']);

if (count($chart_rows) > 9) {
    $visible_rows = array_slice($chart_rows, 0, 8);
    $other_rows = array_slice($chart_rows, 8);
    $other_income = array_sum(array_map(static fn(array $row): float => (float)$row['TOTAL_INCOME'], $other_rows));
    $other_quantity = array_sum(array_map(static fn(array $row): int => (int)$row['TOTAL_QUANTITY'], $other_rows));
    $other_orders = array_sum(array_map(static fn(array $row): int => (int)$row['TOTAL_ORDERS'], $other_rows));
    $visible_rows[] = [
        'PRODUCT_NAME' => 'Other products',
        'TOTAL_INCOME' => $other_income,
        'TOTAL_QUANTITY' => $other_quantity,
        'TOTAL_ORDERS' => $other_orders,
    ];
    $chart_rows = $visible_rows;
}

$chart_segments = [];
$chart_start = 0.0;
foreach ($chart_rows as $index => $row) {
    $income = (float)$row['TOTAL_INCOME'];
    if ($income <= 0 || $total_income <= 0) {
        continue;
    }

    $angle = ($income / $total_income) * 360;
    $chart_segments[] = [
        'label' => (string)$row['PRODUCT_NAME'],
        'income' => $income,
        'quantity' => (int)$row['TOTAL_QUANTITY'],
        'orders' => (int)$row['TOTAL_ORDERS'],
        'percentage' => ($income / $total_income) * 100,
        'color' => $chart_colors[$index % count($chart_colors)],
        'path' => donut_segment_path(150, 150, 118, 62, $chart_start, $chart_start + $angle),
    ];
    $chart_start += $angle;
}

$shop_name = (string)($current_trader['SHOP_NAME'] ?? $current_trader['BUSINESS_NAME'] ?? 'Your Shop');
$month_label = date('F Y', strtotime($selected_month . '-01'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Monthly Sales &mdash; Trader Portal</title>
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
    <a href="dashboard.php">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php" class="active">Monthly Sales</a>
    <a href="profile.php">Profile</a>
  </nav>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Monthly Sales Report</div>

<div class="main-wrap dashboard-page">
  <section class="dashboard-hero report-hero">
    <div>
      <span class="apply-eyebrow">Product Performance</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Monthly totals are calculated directly from your ORDER_ITEM rows, grouped by your own products.</p>
    </div>
    <div class="dashboard-hero-meta">
      <a href="reports_weekly_finance.php" class="btn btn-primary">Weekly Finance</a>
      <small><?= h($month_label) ?></small>
    </div>
  </section>

  <form method="get" action="reports_monthly_sales.php" class="report-filter-bar finance-filter-bar">
    <div class="field">
      <label for="month">Month</label>
      <input type="month" id="month" name="month" value="<?= h($selected_month) ?>">
    </div>
    <div class="field">
      <label for="sort">Sort By</label>
      <select id="sort" name="sort">
        <option value="INCOME"<?= $sort === 'INCOME' ? ' selected' : '' ?>>Total Income</option>
        <option value="ORDERS"<?= $sort === 'ORDERS' ? ' selected' : '' ?>>Total Orders</option>
        <option value="NAME"<?= $sort === 'NAME' ? ' selected' : '' ?>>Product Name</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply Filter</button>
    <a href="reports_monthly_sales.php" class="btn btn-ghost">This Month</a>
  </form>

  <section class="dashboard-metrics report-metrics" aria-label="Monthly sales totals">
    <article class="metric-card">
      <span>Total Income</span>
      <strong><?= h(money_value($total_income)) ?></strong>
      <small>All paid product lines</small>
    </article>
    <article class="metric-card">
      <span>Total Orders</span>
      <strong><?= h((string)$total_orders) ?></strong>
      <small>Distinct customer orders</small>
    </article>
    <article class="metric-card">
      <span>Quantity Sold</span>
      <strong><?= h((string)$total_quantity) ?></strong>
      <small>Total units sold</small>
    </article>
    <article class="metric-card">
      <span>Products</span>
      <strong><?= h((string)count($sales_rows)) ?></strong>
      <small>Products sold this month</small>
    </article>
  </section>

  <section class="dashboard-panel chart-panel">
    <div class="panel-heading">
      <div>
        <span class="apply-eyebrow">Visual Report</span>
        <h2>Monthly Revenue Distribution</h2>
      </div>
      <a href="reports_monthly_sales.php?month=<?= h(rawurlencode($selected_month)) ?>&sort=INCOME" class="panel-link">Sort by income</a>
    </div>

    <?php if (empty($chart_segments)): ?>
      <div class="empty-state">No paid product revenue is available for this month.</div>
    <?php else: ?>
      <div class="revenue-chart-wrap">
        <div class="donut-chart-card">
          <svg class="donut-chart" viewBox="0 0 300 300" role="img" aria-labelledby="monthlyRevenueTitle monthlyRevenueDesc">
            <title id="monthlyRevenueTitle">Monthly revenue distribution for <?= h($shop_name) ?></title>
            <desc id="monthlyRevenueDesc">Product revenue share for <?= h($month_label) ?></desc>
            <circle cx="150" cy="150" r="118" fill="#f8f6f2"></circle>
            <?php foreach ($chart_segments as $segment): ?>
              <path d="<?= h($segment['path']) ?>" fill="<?= h($segment['color']) ?>">
                <title><?= h($segment['label']) ?> - <?= h(money_value($segment['income'])) ?></title>
              </path>
            <?php endforeach; ?>
            <circle cx="150" cy="150" r="57" fill="#ffffff"></circle>
            <text x="150" y="142" text-anchor="middle" class="donut-total-label">Total</text>
            <text x="150" y="166" text-anchor="middle" class="donut-total-value"><?= h(money_value($total_income)) ?></text>
          </svg>
        </div>

        <div class="chart-legend-list">
          <?php foreach ($chart_segments as $segment): ?>
            <article>
              <span class="chart-swatch" style="background: <?= h($segment['color']) ?>"></span>
              <div>
                <strong><?= h($segment['label']) ?></strong>
                <small><?= h(money_value($segment['income'])) ?> &middot; <?= h(number_format($segment['percentage'], 1)) ?>%</small>
                <small class="chart-legend-units"><?= h((string)$segment['quantity']) ?> units &middot; <?= h((string)$segment['orders']) ?> orders</small>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section class="dashboard-grid report-two-column-grid">
    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Monthly Report</span>
          <h2>Product Sales</h2>
        </div>
      </div>

      <?php if (empty($sales_rows)): ?>
        <div class="empty-state">No paid sales rows found for this month.</div>
      <?php else: ?>
        <div class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Total Orders</th>
                <th>Total Quantity</th>
                <th>Total Income</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sales_rows as $row): ?>
                <tr>
                  <td><?= h((string)$row['PRODUCT_NAME']) ?></td>
                  <td><?= h((string)$row['TOTAL_ORDERS']) ?></td>
                  <td><?= h((string)$row['TOTAL_QUANTITY']) ?></td>
                  <td><strong><?= h(money_value($row['TOTAL_INCOME'])) ?></strong></td>
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
          <span class="apply-eyebrow">Top Products</span>
          <h2>Income Ranking</h2>
        </div>
      </div>

      <?php if (empty($sales_rows)): ?>
        <div class="empty-state">No ranking to show yet.</div>
      <?php else: ?>
        <div class="sales-ranking-list">
          <?php foreach ($sales_rows as $row): ?>
            <?php
              $income = (float)$row['TOTAL_INCOME'];
              $width = $max_income > 0 ? max(8, (int)round(($income / $max_income) * 100)) : 0;
            ?>
            <article>
              <div class="sales-ranking-top">
                <strong><?= h((string)$row['PRODUCT_NAME']) ?></strong>
                <span><?= h(money_value($income)) ?></span>
              </div>
              <div class="sales-bar" aria-hidden="true"><span style="width: <?= h((string)$width) ?>%"></span></div>
              <small><?= h((string)$row['TOTAL_ORDERS']) ?> orders, <?= h((string)$row['TOTAL_QUANTITY']) ?> units</small>
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
