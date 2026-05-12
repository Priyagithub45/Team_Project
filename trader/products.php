<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$flash = trader_flash_get();
$has_status = trader_product_status_column_exists($conn);
$has_image = trader_product_image_column_exists($conn);
$shop_context = trader_shop_context($conn, $current_trader_id, true);
$shop_filter_sql = trader_shop_filter_sql($shop_context);
$shop_name = trader_shop_context_label($shop_context, 'All shops');
$account_name = trader_account_label($current_trader);
$status_select = $has_status ? ", NVL(p.STATUS, 'ACTIVE') AS STATUS" : ", 'ACTIVE' AS STATUS";
$image_select = $has_image ? ", p.IMAGE_PATH" : ", NULL AS IMAGE_PATH";
$status_filter = $has_status ? "AND NVL(UPPER(p.STATUS), 'ACTIVE') <> 'DISCONTINUED'" : '';

$sql = "
    SELECT p.PRODUCT_ID,
           p.PRODUCT_NAME,
           p.PRICE,
           NVL(p.STOCK_QUANTITY, 0) AS STOCK_QUANTITY,
           c.CATEGORY_NAME,
           s.SHOP_NAME
           {$status_select}
           {$image_select}
    FROM PRODUCT p
    JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
    LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
      {$status_filter}
    ORDER BY p.PRODUCT_NAME
";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':trader_id', $current_trader_id);
trader_bind_shop_filter($stmt, $shop_context);
oci_execute($stmt);

$products = [];
while ($row = oci_fetch_assoc($stmt)) {
    $products[] = $row;
}
oci_free_statement($stmt);

function product_image_src_for_trader(array $product): string
{
    $path = trim((string)($product['IMAGE_PATH'] ?? ''));
    if ($path !== '') {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (file_exists(dirname(__DIR__) . '/' . $path)) {
            return '../' . implode('/', array_map('rawurlencode', explode('/', $path)));
        }
    }

    $slug = strtolower(trim((string)($product['PRODUCT_NAME'] ?? '')));
    $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
    $slug = trim((string)$slug, '_');

    if ($slug === '') {
        return '';
    }

    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
        $fallback_path = 'uploads/products/' . $slug . '.' . $extension;
        if (file_exists(dirname(__DIR__) . '/' . $fallback_path)) {
            return '../' . implode('/', array_map('rawurlencode', explode('/', $fallback_path)));
        }
    }

    return '';
}

function product_stock_label(int $stock): string
{
    if ($stock <= 0) {
        return 'Out of stock';
    }
    if ($stock <= 5) {
        return 'Low stock';
    }
    return 'In stock';
}

function product_stock_class(int $stock): string
{
    if ($stock <= 0) {
        return 'status-out';
    }
    if ($stock <= 5) {
        return 'status-low';
    }
    return 'status-active';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Products &mdash; Trader Portal</title>
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
    <a href="products.php" class="active">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php">Profile</a>
  </nav>
  <?php trader_render_shop_switcher($shop_context); ?>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Manage Products</div>

<div class="main-wrap dashboard-page">
  <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type'] === 'success' ? 'success' : 'error') ?>"><?= h((string)$flash['message']) ?></div>
  <?php endif; ?>

  <section class="dashboard-hero">
    <div>
      <span class="apply-eyebrow">Inventory</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Use the shop selector to view all products or one shop's catalogue.</p>
    </div>
    <div class="dashboard-hero-meta">
      <a href="add_product.php" class="btn btn-primary">Add Product</a>
    </div>
  </section>

  <div class="dashboard-panel">
    <div class="panel-heading">
      <div>
        <span class="apply-eyebrow">Products</span>
        <h2>Your Product Catalogue</h2>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-state">You have not added any products yet.</div>
    <?php else: ?>
      <div class="responsive-table product-table">
        <table>
          <thead>
            <tr>
              <th>Image</th>
              <th>Product</th>
              <th>Shop</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stock</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <?php
                $stock = (int)$product['STOCK_QUANTITY'];
                $image_src = product_image_src_for_trader($product);
                $status = strtoupper((string)($product['STATUS'] ?? 'ACTIVE'));
              ?>
              <tr>
                <td>
                  <div class="product-thumb">
                    <?php if ($image_src !== ''): ?>
                      <img src="<?= h($image_src) ?>" alt="<?= h((string)$product['PRODUCT_NAME']) ?>">
                    <?php else: ?>
                      <span>No image</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= h((string)$product['PRODUCT_NAME']) ?></td>
                <td><?= h((string)($product['SHOP_NAME'] ?? '-')) ?></td>
                <td><?= h((string)($product['CATEGORY_NAME'] ?? '-')) ?></td>
                <td>GBP <?= h(number_format((float)$product['PRICE'], 2)) ?></td>
                <td><?= h((string)$stock) ?></td>
                <td>
                  <span class="status-box <?= h($status === 'ACTIVE' ? product_stock_class($stock) : 'status-low') ?>">
                    <?= h($status === 'ACTIVE' ? product_stock_label($stock) : ucfirst(strtolower($status))) ?>
                  </span>
                </td>
                <td>
                  <div class="table-actions">
                    <a href="edit_product.php?id=<?= h((string)$product['PRODUCT_ID']) ?>" class="action-btn btn-edit">Edit</a>
                    <form method="post" action="delete_product.php" onsubmit="return confirm('Inactivate this product?');">
                      <input type="hidden" name="product_id" value="<?= h((string)$product['PRODUCT_ID']) ?>">
                      <button type="submit" class="action-btn btn-delete">Inactivate</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
