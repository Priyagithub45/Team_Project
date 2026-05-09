<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$product_id) {
    trader_flash_set('error', 'Invalid product selected.');
    header('Location: products.php');
    exit;
}

$product = trader_fetch_owned_product($conn, (int)$product_id, $current_trader_id);
if (!$product) {
    trader_flash_set('error', 'Product not found for this trader account.');
    header('Location: products.php');
    exit;
}

$categories = trader_categories($conn);
$errors = trader_product_errors_get();
$old = trader_old_get();
$data = array_merge([
    'product_name' => $product['PRODUCT_NAME'] ?? '',
    'description' => $product['DESCRIPTION'] ?? '',
    'price' => $product['PRICE'] ?? '',
    'stock_quantity' => $product['STOCK_QUANTITY'] ?? '',
    'expiry_date' => $product['EXPIRY_DATE'] ?? '',
    'quantity_per_item' => $product['QUANTITY_PER_ITEM'] ?? '',
    'min_order' => $product['MIN_ORDER'] ?? '',
    'max_order' => $product['MAX_ORDER'] ?? '',
    'allergy_info' => $product['ALLERGY_INFO'] ?? '',
    'category_id' => $product['CATEGORY_ID'] ?? '',
    'status' => $product['STATUS'] ?? 'ACTIVE',
], $old);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Product &mdash; Trader Portal</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h((string)($current_trader['SHOP_NAME'] ?? $current_trader['BUSINESS_NAME'] ?? 'Your Shop')) ?></h2>
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
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Edit Product</div>

<div class="main-wrap form-page">
  <div class="form-card product-form-card">
    <div class="application-card-heading">
      <span class="apply-eyebrow">Inventory</span>
      <h2>Edit Product</h2>
      <p>Updates are allowed only for products owned by your shop.</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= h((string)$error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="update_product.php" enctype="multipart/form-data" class="application-form">
      <input type="hidden" name="product_id" value="<?= h((string)$product_id) ?>">

      <div class="form-row">
        <div class="field">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" maxlength="100" required value="<?= h((string)$data['product_name']) ?>">
        </div>
        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($categories as $category): ?>
              <?php $selected = (string)$data['category_id'] === (string)$category['CATEGORY_ID'] ? ' selected' : ''; ?>
              <option value="<?= h((string)$category['CATEGORY_ID']) ?>"<?= $selected ?>><?= h((string)$category['CATEGORY_NAME']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="price">Price</label>
          <input type="number" id="price" name="price" step="0.01" min="0.01" required value="<?= h((string)$data['price']) ?>">
        </div>
        <div class="field">
          <label for="stock_quantity">Stock Quantity</label>
          <input type="number" id="stock_quantity" name="stock_quantity" min="0" required value="<?= h((string)$data['stock_quantity']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="quantity_per_item">Quantity Per Item</label>
          <input type="number" id="quantity_per_item" name="quantity_per_item" min="0" value="<?= h((string)$data['quantity_per_item']) ?>">
        </div>
        <div class="field">
          <label for="expiry_date">Expiry Date</label>
          <input type="date" id="expiry_date" name="expiry_date" value="<?= h((string)$data['expiry_date']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="min_order">Minimum Order</label>
          <input type="number" id="min_order" name="min_order" min="0" value="<?= h((string)$data['min_order']) ?>">
        </div>
        <div class="field">
          <label for="max_order">Maximum Order</label>
          <input type="number" id="max_order" name="max_order" min="0" value="<?= h((string)$data['max_order']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="200"><?= h((string)$data['description']) ?></textarea>
        </div>
        <div class="field">
          <label for="allergy_info">Allergy Information</label>
          <textarea id="allergy_info" name="allergy_info" maxlength="200"><?= h((string)$data['allergy_info']) ?></textarea>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="image">Replace Product Image</label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="field">
          <label for="status">Status</label>
          <?php $status = strtoupper((string)$data['status']); ?>
          <select id="status" name="status">
            <option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option>
            <option value="INACTIVE"<?= $status === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option>
            <option value="DISCONTINUED"<?= $status === 'DISCONTINUED' ? ' selected' : '' ?>>Discontinued</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update Product</button>
        <a href="products.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
