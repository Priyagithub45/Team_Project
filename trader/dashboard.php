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

function dashboard_scalar($conn, string $sql, int $trader_id, ?int $selected_shop_id = null, $default = 0)
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER DASHBOARD PARSE] ' . ($err['message'] ?? 'unknown error'));
        return $default;
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if ($selected_shop_id !== null) {
        oci_bind_by_name($stmt, ':selected_shop_id', $selected_shop_id);
    }

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

function dashboard_rows($conn, string $sql, int $trader_id, ?int $selected_shop_id = null): array
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $err = oci_error($conn);
        error_log('[TRADER DASHBOARD PARSE] ' . ($err['message'] ?? 'unknown error'));
        return [];
    }

    oci_bind_by_name($stmt, ':trader_id', $trader_id);
    if ($selected_shop_id !== null) {
        oci_bind_by_name($stmt, ':selected_shop_id', $selected_shop_id);
    }

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
$shop_context = trader_shop_context($conn, $trader_id, true);
$selected_shop_id = $shop_context['selected_shop_id'];
$shop_filter_sql = trader_shop_filter_sql($shop_context);
$shop_name = trader_shop_context_label($shop_context, 'All shops');
$account_name = trader_account_label($current_trader);
$trader_name = (string)($current_trader['NAME'] ?? 'Trader');
$trader_status = strtoupper(trim((string)($current_trader['TRADER_STATUS'] ?? 'ACTIVE')));
if ($trader_status === '') {
    $trader_status = 'ACTIVE';
}
$active_product_filter = trader_product_status_column_exists($conn)
    ? "AND NVL(UPPER(p.STATUS), 'ACTIVE') = 'ACTIVE'"
    : '';
$paid_order_status_sql = "AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')";

$total_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      {$active_product_filter}
", $trader_id, $selected_shop_id);

$low_stock_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      {$active_product_filter}
      AND NVL(p.STOCK_QUANTITY, 0) BETWEEN 1 AND 5
", $trader_id, $selected_shop_id);

$out_of_stock_products = (int)dashboard_scalar($conn, "
    SELECT COUNT(*) AS DASH_VALUE
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      {$active_product_filter}
      AND NVL(p.STOCK_QUANTITY, 0) <= 0
", $trader_id, $selected_shop_id);

$upcoming_order_count = (int)dashboard_scalar($conn, "
    SELECT COUNT(DISTINCT o.ORDER_ID) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
      AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
", $trader_id, $selected_shop_id);

$week_revenue = dashboard_scalar($conn, "
    SELECT NVL(SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)), 0) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND o.ORDER_DATE >= TRUNC(SYSDATE) - 6
      AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')
", $trader_id, $selected_shop_id, 0);

$month_quantity_sold = (int)dashboard_scalar($conn, "
    SELECT NVL(SUM(oi.QUANTITY), 0) AS DASH_VALUE
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND TRUNC(o.ORDER_DATE, 'MM') = TRUNC(SYSDATE, 'MM')
      AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')
", $trader_id, $selected_shop_id);

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
          {$shop_filter_sql}
          {$active_product_filter}
        ORDER BY p.PRODUCT_ID DESC
    )
    WHERE ROWNUM <= 8
", $trader_id, $selected_shop_id);

$upcoming_rows = dashboard_rows($conn, "
    SELECT *
    FROM (
        SELECT o.ORDER_ID,
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
          {$shop_filter_sql}
          AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
          AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
        ORDER BY cs.COLLECTION_DATE, cs.COLLECTION_TIME, o.ORDER_ID, p.PRODUCT_NAME
    )
    WHERE ROWNUM <= 8
", $trader_id, $selected_shop_id);

$product_revenue_rows = dashboard_rows($conn, "
    SELECT *
    FROM (
        SELECT p.PRODUCT_NAME,
               NVL(SUM(oi.QUANTITY * NVL(oi.PRICE, p.PRICE)), 0) AS TOTAL_REVENUE,
               NVL(SUM(oi.QUANTITY), 0) AS TOTAL_QUANTITY,
               COUNT(DISTINCT o.ORDER_ID) AS TOTAL_ORDERS
        FROM ORDERS o
        JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
        JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
        JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
        WHERE s.TRADER_ID = :trader_id
          {$shop_filter_sql}
          {$paid_order_status_sql}
        GROUP BY p.PRODUCT_NAME
        ORDER BY TOTAL_REVENUE DESC, p.PRODUCT_NAME
    )
    WHERE ROWNUM <= 11
", $trader_id, $selected_shop_id);

$max_product_revenue = 0.0;
foreach ($product_revenue_rows as $row) {
    $max_product_revenue = max($max_product_revenue, (float)($row['TOTAL_REVENUE'] ?? 0));
}

$slot_time_expr = "REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')";
$slot_count_rows = dashboard_rows($conn, "
    SELECT {$slot_time_expr} AS SLOT_KEY,
           COUNT(DISTINCT o.ORDER_ID) AS ORDER_COUNT
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      AND TO_CHAR(cs.COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
      AND {$slot_time_expr} IN ('10-13','13-16','16-19')
      AND TRUNC(cs.COLLECTION_DATE) >= TRUNC(SYSDATE)
      AND UPPER(NVL(o.STATUS, 'PAID')) <> 'CANCELLED'
    GROUP BY {$slot_time_expr}
", $trader_id, $selected_shop_id);

$collection_slot_chart_rows = [
    '10-13' => ['label' => '10-13', 'count' => 0],
    '13-16' => ['label' => '13-16', 'count' => 0],
    '16-19' => ['label' => '16-19', 'count' => 0],
];
foreach ($slot_count_rows as $row) {
    $slot_key = (string)($row['SLOT_KEY'] ?? '');
    if (isset($collection_slot_chart_rows[$slot_key])) {
        $collection_slot_chart_rows[$slot_key]['count'] = (int)($row['ORDER_COUNT'] ?? 0);
    }
}
$max_slot_count = 0;
foreach ($collection_slot_chart_rows as $row) {
    $max_slot_count = max($max_slot_count, (int)$row['count']);
}
$slot_axis_max = max(5, (int)ceil($max_slot_count / 5) * 5);

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

function chart_label(string $value, int $max_length = 24): string
{
    $value = trim($value);
    if (strlen($value) <= $max_length) {
        return $value;
    }

    return substr($value, 0, max(1, $max_length - 3)) . '...';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trader Dashboard &mdash; <?= h($account_name) ?></title>
  <link rel="stylesheet" href="trader.css?v=dashboard-charts-3">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h($account_name) ?></h2>
    <span class="sidebar-label">Trader Portal</span>
  </div>
  <nav>
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="reviews.php">Reviews</a>
    <a href="profile.php">Profile</a>
  </nav>
  <?php trader_render_shop_switcher($shop_context); ?>
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
      <p>Signed in as <?= h($trader_name) ?>. Use the shop selector to view all shops or one shop at a time.</p>
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
    <a href="reviews.php">Reviews</a>
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

  <section class="dashboard-charts" aria-label="Trader visual reports">
    <div class="dashboard-panel chart-panel product-revenue-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Visual Report</span>
          <h2>Product Revenue Analysis</h2>
        </div>
        <a href="reports_monthly_sales.php" class="panel-link">Monthly report</a>
      </div>

      <?php if (empty($product_revenue_rows) || $max_product_revenue <= 0): ?>
        <div class="empty-state">No paid product revenue is available yet.</div>
      <?php else: ?>
        <?php
          $row_count = count($product_revenue_rows);
          $svg_height = 54 + ($row_count * 27);
          $plot_x = 178;
          $plot_width = 520;
          $bar_height = 16;
        ?>
        <svg class="db-svg-chart product-revenue-svg" viewBox="0 0 740 <?= h((string)$svg_height) ?>" role="img" aria-labelledby="productRevenueTitle productRevenueDesc">
          <title id="productRevenueTitle">Product revenue analysis</title>
          <desc id="productRevenueDesc">Revenue grouped by product from paid and active orders in the database.</desc>
          <rect x="0" y="0" width="740" height="<?= h((string)$svg_height) ?>" fill="#ffffff"></rect>
          <?php for ($i = 0; $i <= 5; $i++): ?>
            <?php $grid_x = $plot_x + (($plot_width / 5) * $i); ?>
            <line x1="<?= h(number_format($grid_x, 2, '.', '')) ?>" y1="12" x2="<?= h(number_format($grid_x, 2, '.', '')) ?>" y2="<?= h((string)($svg_height - 28)) ?>" stroke="#e5e7eb" stroke-width="1"></line>
          <?php endfor; ?>
          <?php foreach ($product_revenue_rows as $index => $row): ?>
            <?php
              $revenue = (float)($row['TOTAL_REVENUE'] ?? 0);
              $bar_width = $max_product_revenue > 0 ? max(3, ($revenue / $max_product_revenue) * $plot_width) : 0;
              $y = 18 + ($index * 27);
              $value_x = min($plot_x + $bar_width - 8, $plot_x + $plot_width - 8);
            ?>
            <text x="<?= h((string)($plot_x - 10)) ?>" y="<?= h((string)($y + 12)) ?>" text-anchor="end" font-size="11" font-weight="700" fill="#475569">
              <title><?= h((string)$row['PRODUCT_NAME']) ?></title><?= h(chart_label((string)$row['PRODUCT_NAME'], 22)) ?>
            </text>
            <rect x="<?= h((string)$plot_x) ?>" y="<?= h((string)$y) ?>" width="<?= h(number_format($plot_width, 2, '.', '')) ?>" height="<?= h((string)$bar_height) ?>" fill="#f8fafc"></rect>
            <rect x="<?= h((string)$plot_x) ?>" y="<?= h((string)$y) ?>" width="<?= h(number_format($bar_width, 2, '.', '')) ?>" height="<?= h((string)$bar_height) ?>" fill="#2f9ed8"></rect>
            <text x="<?= h(number_format($value_x, 2, '.', '')) ?>" y="<?= h((string)($y + 12)) ?>" text-anchor="end" font-size="10" font-weight="800" fill="#0f2533"><?= h(number_format($revenue, 0)) ?></text>
          <?php endforeach; ?>
          <line x1="<?= h((string)$plot_x) ?>" y1="<?= h((string)($svg_height - 28)) ?>" x2="<?= h((string)($plot_x + $plot_width)) ?>" y2="<?= h((string)($svg_height - 28)) ?>" stroke="#cbd5e1" stroke-width="1"></line>
          <text x="<?= h((string)$plot_x) ?>" y="<?= h((string)($svg_height - 9)) ?>" font-size="10" font-weight="700" fill="#64748b">GBP 0</text>
          <text x="<?= h((string)($plot_x + $plot_width)) ?>" y="<?= h((string)($svg_height - 9)) ?>" text-anchor="end" font-size="10" font-weight="700" fill="#64748b"><?= h(money_value($max_product_revenue)) ?></text>
        </svg>
      <?php endif; ?>
    </div>

    <div class="dashboard-panel chart-panel collection-slot-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Operations</span>
          <h2>Collection Slot</h2>
        </div>
        <a href="reports_daily.php" class="panel-link">Daily report</a>
      </div>

      <svg class="db-svg-chart collection-slot-svg" viewBox="0 0 740 330" role="img" aria-labelledby="collectionSlotTitle collectionSlotDesc">
        <title id="collectionSlotTitle">Collection slot order counts</title>
        <desc id="collectionSlotDesc">Upcoming distinct order counts grouped by collection slot from the database.</desc>
        <rect x="0" y="0" width="740" height="330" fill="#ffffff"></rect>
        <?php
          $slot_plot_x = 58;
          $slot_plot_y = 24;
          $slot_plot_width = 640;
          $slot_plot_height = 238;
          $axis_values = [$slot_axis_max, (int)round($slot_axis_max * 0.75), (int)round($slot_axis_max * 0.5), (int)round($slot_axis_max * 0.25), 0];
        ?>
        <?php foreach ($axis_values as $i => $axis_value): ?>
          <?php $line_y = $slot_plot_y + (($slot_plot_height / 4) * $i); ?>
          <text x="40" y="<?= h((string)($line_y + 4)) ?>" text-anchor="end" font-size="13" font-weight="700" fill="#64748b"><?= h((string)$axis_value) ?></text>
          <line x1="<?= h((string)$slot_plot_x) ?>" y1="<?= h(number_format($line_y, 2, '.', '')) ?>" x2="<?= h((string)($slot_plot_x + $slot_plot_width)) ?>" y2="<?= h(number_format($line_y, 2, '.', '')) ?>" stroke="#e5e7eb" stroke-width="1"></line>
        <?php endforeach; ?>
        <line x1="<?= h((string)$slot_plot_x) ?>" y1="<?= h((string)($slot_plot_y + $slot_plot_height)) ?>" x2="<?= h((string)($slot_plot_x + $slot_plot_width)) ?>" y2="<?= h((string)($slot_plot_y + $slot_plot_height)) ?>" stroke="#94a3b8" stroke-width="1"></line>
        <?php $slot_index = 0; foreach ($collection_slot_chart_rows as $slot): ?>
          <?php
            $slot_count = (int)$slot['count'];
            $bar_width = 112;
            $bar_x = $slot_plot_x + 72 + ($slot_index * 202);
            $bar_height = $slot_axis_max > 0 ? (($slot_count / $slot_axis_max) * $slot_plot_height) : 0;
            $bar_y = $slot_plot_y + $slot_plot_height - $bar_height;
          ?>
          <rect x="<?= h((string)$bar_x) ?>" y="<?= h(number_format($bar_y, 2, '.', '')) ?>" width="<?= h((string)$bar_width) ?>" height="<?= h(number_format($bar_height, 2, '.', '')) ?>" fill="#0f5f08"></rect>
          <?php if ($slot_count > 0): ?>
            <text x="<?= h((string)($bar_x + ($bar_width / 2))) ?>" y="<?= h(number_format(max($slot_plot_y + 16, $bar_y - 8), 2, '.', '')) ?>" text-anchor="middle" font-size="14" font-weight="900" fill="#1c2b41"><?= h((string)$slot_count) ?></text>
          <?php endif; ?>
          <text x="<?= h((string)($bar_x + ($bar_width / 2))) ?>" y="302" text-anchor="middle" font-size="14" font-weight="800" fill="#64748b"><?= h($slot['label']) ?></text>
        <?php $slot_index++; endforeach; ?>
      </svg>
    </div>
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
                <span>Order #<?= h((string)$order['ORDER_ID']) ?></span>
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
