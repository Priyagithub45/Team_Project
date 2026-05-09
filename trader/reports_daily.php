<?php
require_once 'auth_check.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function valid_report_date(?string $value): string
{
    $value = trim((string)$value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    return date('Y-m-d');
}

function normalize_slot_time(string $value): string
{
    return str_replace([' ', ':00'], '', trim($value));
}

function slot_label(string $value): string
{
    $slot = normalize_slot_time($value);
    if ($slot === '10-13') {
        return 'Morning 10:00-13:00';
    }
    if ($slot === '13-16') {
        return 'Afternoon 13:00-16:00';
    }
    if ($slot === '16-19') {
        return 'Evening 16:00-19:00';
    }
    return $value;
}

$selected_date = valid_report_date($_GET['date'] ?? null);
$selected_slot = normalize_slot_time((string)($_GET['slot'] ?? ''));
$allowed_slots = ['10-13', '13-16', '16-19'];
if ($selected_slot !== '' && !in_array($selected_slot, $allowed_slots, true)) {
    $selected_slot = '';
}

$slot_time_expr = "REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')";
$slot_order_expr = "CASE {$slot_time_expr}
    WHEN '10-13' THEN 1
    WHEN '13-16' THEN 2
    WHEN '16-19' THEN 3
    ELSE 9
END";
$slot_filter_sql = $selected_slot !== '' ? "AND {$slot_time_expr} = :selected_slot" : '';
$report_order_status_sql = "AND UPPER(NVL(o.STATUS, 'PENDING')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')";

$slot_sql = "
    SELECT DISTINCT cs.COLLECTION_TIME,
           {$slot_time_expr} AS SLOT_KEY,
           {$slot_order_expr} AS SLOT_ORDER
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(cs.COLLECTION_DATE) = TO_DATE(:selected_date, 'YYYY-MM-DD')
      {$report_order_status_sql}
    ORDER BY SLOT_ORDER, cs.COLLECTION_TIME
";

$slot_stmt = oci_parse($conn, $slot_sql);
oci_bind_by_name($slot_stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($slot_stmt, ':selected_date', $selected_date);
oci_execute($slot_stmt);
$available_slots = [];
while ($row = oci_fetch_assoc($slot_stmt)) {
    $available_slots[] = $row;
}
oci_free_statement($slot_stmt);

$detail_sql = "
    SELECT o.ORDER_ID,
           o.CUSTOMER_ID,
           su.NAME AS CUSTOMER_NAME,
           p.PRODUCT_ID,
           p.PRODUCT_NAME,
           oi.QUANTITY,
           oi.PRICE,
           TO_CHAR(cs.COLLECTION_DATE, 'YYYY-MM-DD') AS COLLECTION_DATE,
           cs.COLLECTION_TIME,
           {$slot_time_expr} AS SLOT_KEY,
           o.STATUS,
           s.SHOP_NAME
    FROM ORDERS o
    JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
    JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
    LEFT JOIN SYSTEM_USER su ON su.USER_ID = o.CUSTOMER_ID
    WHERE s.TRADER_ID = :trader_id
      AND TRUNC(cs.COLLECTION_DATE) = TO_DATE(:selected_date, 'YYYY-MM-DD')
      {$slot_filter_sql}
      {$report_order_status_sql}
    ORDER BY {$slot_order_expr}, o.ORDER_ID, p.PRODUCT_NAME
";

$detail_stmt = oci_parse($conn, $detail_sql);
oci_bind_by_name($detail_stmt, ':trader_id', $current_trader_id);
oci_bind_by_name($detail_stmt, ':selected_date', $selected_date);
if ($selected_slot !== '') {
    oci_bind_by_name($detail_stmt, ':selected_slot', $selected_slot);
}
oci_execute($detail_stmt);

$order_items = [];
while ($row = oci_fetch_assoc($detail_stmt)) {
    $order_items[] = $row;
}
oci_free_statement($detail_stmt);

$summary = [];
$orders_seen = [];
foreach ($order_items as $item) {
    $summary_key = ($item['SLOT_KEY'] ?? '') . '|' . ($item['PRODUCT_ID'] ?? '') . '|' . ($item['PRODUCT_NAME'] ?? '');
    if (!isset($summary[$summary_key])) {
        $summary[$summary_key] = [
            'SLOT_KEY' => (string)($item['SLOT_KEY'] ?? ''),
            'COLLECTION_TIME' => (string)($item['COLLECTION_TIME'] ?? ''),
            'PRODUCT_NAME' => (string)($item['PRODUCT_NAME'] ?? ''),
            'TOTAL_QUANTITY' => 0,
            'ORDER_COUNT' => 0,
        ];
    }

    $summary[$summary_key]['TOTAL_QUANTITY'] += (int)$item['QUANTITY'];
    $summary[$summary_key]['ORDER_COUNT']++;
    $orders_seen[(string)$item['ORDER_ID']] = true;
}

$summary_rows = array_values($summary);
usort($summary_rows, static function (array $a, array $b): int {
    $slot_order = ['10-13' => 1, '13-16' => 2, '16-19' => 3];
    $a_order = $slot_order[$a['SLOT_KEY']] ?? 9;
    $b_order = $slot_order[$b['SLOT_KEY']] ?? 9;
    return [$a_order, $a['PRODUCT_NAME']] <=> [$b_order, $b['PRODUCT_NAME']];
});

$shop_name = (string)($current_trader['SHOP_NAME'] ?? $current_trader['BUSINESS_NAME'] ?? 'Your Shop');
$total_quantity = array_sum(array_map(static fn($row) => (int)$row['QUANTITY'], $order_items));
$total_orders = count($orders_seen);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Orders &mdash; Trader Portal</title>
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
    <a href="reports_daily.php" class="active">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php">Profile</a>
  </nav>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Daily Orders Report</div>

<div class="main-wrap dashboard-page">
  <section class="dashboard-hero daily-report-hero">
    <div>
      <span class="apply-eyebrow">Collection Preparation</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Only orders containing products from your shop are shown. Use this page to prepare goods and print collection labels.</p>
    </div>
    <div class="dashboard-hero-meta">
      <button type="button" class="btn btn-primary" onclick="window.print()">Print Labels</button>
      <small><?= h(date('l, d M Y', strtotime($selected_date))) ?></small>
    </div>
  </section>

  <form method="get" action="reports_daily.php" class="report-filter-bar">
    <div class="field">
      <label for="date">Collection Date</label>
      <input type="date" id="date" name="date" value="<?= h($selected_date) ?>">
    </div>
    <div class="field">
      <label for="slot">Collection Slot</label>
      <select id="slot" name="slot">
        <option value="">All slots</option>
        <option value="10-13"<?= $selected_slot === '10-13' ? ' selected' : '' ?>>Morning 10:00-13:00</option>
        <option value="13-16"<?= $selected_slot === '13-16' ? ' selected' : '' ?>>Afternoon 13:00-16:00</option>
        <option value="16-19"<?= $selected_slot === '16-19' ? ' selected' : '' ?>>Evening 16:00-19:00</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply Filter</button>
    <a href="reports_daily.php" class="btn btn-ghost">Today</a>
  </form>

  <section class="dashboard-metrics report-metrics" aria-label="Daily order totals">
    <article class="metric-card">
      <span>Orders</span>
      <strong><?= h((string)$total_orders) ?></strong>
      <small>Distinct customer orders</small>
    </article>
    <article class="metric-card">
      <span>Items</span>
      <strong><?= h((string)$total_quantity) ?></strong>
      <small>Total units to prepare</small>
    </article>
    <article class="metric-card">
      <span>Lines</span>
      <strong><?= h((string)count($order_items)) ?></strong>
      <small>Product order lines</small>
    </article>
    <article class="metric-card">
      <span>Slots</span>
      <strong><?= h((string)count($available_slots)) ?></strong>
      <small>Slots with your orders</small>
    </article>
  </section>

  <section class="dashboard-grid daily-report-grid">
    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Preparation Summary</span>
          <h2>Products to Prepare</h2>
        </div>
      </div>

      <?php if (empty($summary_rows)): ?>
        <div class="empty-state">No collection orders for this date and slot.</div>
      <?php else: ?>
        <div class="responsive-table">
          <table>
            <thead>
              <tr>
                <th>Slot</th>
                <th>Product</th>
                <th>Total Quantity</th>
                <th>Order Lines</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($summary_rows as $row): ?>
                <tr>
                  <td><?= h(slot_label((string)$row['COLLECTION_TIME'])) ?></td>
                  <td><?= h((string)$row['PRODUCT_NAME']) ?></td>
                  <td><strong><?= h((string)$row['TOTAL_QUANTITY']) ?></strong></td>
                  <td><?= h((string)$row['ORDER_COUNT']) ?></td>
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
          <span class="apply-eyebrow">Slots</span>
          <h2>Orders by Slot</h2>
        </div>
      </div>

      <?php if (empty($available_slots)): ?>
        <div class="empty-state">No active slots with your orders for this date.</div>
      <?php else: ?>
        <div class="slot-chip-list">
          <a class="<?= $selected_slot === '' ? 'active' : '' ?>" href="reports_daily.php?date=<?= h(rawurlencode($selected_date)) ?>">All slots</a>
          <?php foreach ($available_slots as $slot): ?>
            <?php $slot_key = normalize_slot_time((string)$slot['COLLECTION_TIME']); ?>
            <a class="<?= $selected_slot === $slot_key ? 'active' : '' ?>" href="reports_daily.php?date=<?= h(rawurlencode($selected_date)) ?>&slot=<?= h(rawurlencode($slot_key)) ?>">
              <?= h(slot_label((string)$slot['COLLECTION_TIME'])) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="dashboard-panel">
    <div class="panel-heading">
      <div>
        <span class="apply-eyebrow">Order Lines</span>
        <h2>Daily Collection Detail</h2>
      </div>
    </div>

    <?php if (empty($order_items)): ?>
      <div class="empty-state">No paid collection items found for your shop.</div>
    <?php else: ?>
      <div class="responsive-table">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer ID</th>
              <th>Customer</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Date</th>
              <th>Slot</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order_items as $item): ?>
              <tr>
                <td>#<?= h((string)$item['ORDER_ID']) ?></td>
                <td><?= h((string)$item['CUSTOMER_ID']) ?></td>
                <td><?= h((string)($item['CUSTOMER_NAME'] ?? '-')) ?></td>
                <td><?= h((string)$item['PRODUCT_NAME']) ?></td>
                <td><strong><?= h((string)$item['QUANTITY']) ?></strong></td>
                <td><?= h((string)$item['COLLECTION_DATE']) ?></td>
                <td><?= h(slot_label((string)$item['COLLECTION_TIME'])) ?></td>
                <td><?= h((string)($item['STATUS'] ?? 'Paid')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="dashboard-panel label-print-section">
    <div class="panel-heading">
      <div>
        <span class="apply-eyebrow">Labels</span>
        <h2>Collection Labels</h2>
      </div>
      <button type="button" class="panel-link print-link-button" onclick="window.print()">Print</button>
    </div>

    <?php if (empty($order_items)): ?>
      <div class="empty-state">No labels to print.</div>
    <?php else: ?>
      <div class="label-grid">
        <?php foreach ($order_items as $item): ?>
          <article class="collection-label">
            <div class="label-top">
              <span>Order #<?= h((string)$item['ORDER_ID']) ?></span>
              <strong>x<?= h((string)$item['QUANTITY']) ?></strong>
            </div>
            <h3><?= h((string)$item['PRODUCT_NAME']) ?></h3>
            <dl>
              <div><dt>Customer</dt><dd>#<?= h((string)$item['CUSTOMER_ID']) ?> <?= h((string)($item['CUSTOMER_NAME'] ?? '')) ?></dd></div>
              <div><dt>Slot</dt><dd><?= h((string)$item['COLLECTION_DATE']) ?>, <?= h(slot_label((string)$item['COLLECTION_TIME'])) ?></dd></div>
              <div><dt>Shop</dt><dd><?= h((string)$item['SHOP_NAME']) ?></dd></div>
            </dl>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
