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
$shop_context = trader_shop_context($conn, $current_trader_id, false);
$shops = $shop_context['shops'];
$account_name = trader_account_label($current_trader);
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
    'shop_id' => $product['SHOP_ID'] ?? '',
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

<div class="header">Edit Product</div>

<div class="main-wrap form-page">
  <div class="form-card product-form-card">
    <div class="application-card-heading">
      <span class="apply-eyebrow">Inventory</span>
      <h2>Edit Product</h2>
      <p>Updates are allowed only for products owned by one of your shops.</p>
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

    <p class="form-required-legend"><span class="required-star" aria-hidden="true">*</span> Required field</p>

    <form method="post" action="update_product.php" enctype="multipart/form-data" class="application-form" novalidate>
      <input type="hidden" name="product_id" value="<?= h((string)$product_id) ?>">

      <div class="form-row">
        <div class="field<?= isset($errors['shop_id']) ? ' has-error' : '' ?>">
          <label for="shop_id">Shop <span class="required-star" aria-hidden="true">*</span></label>
          <select id="shop_id" name="shop_id" required
                  <?= isset($errors['shop_id']) ? 'aria-invalid="true" aria-describedby="err_shop_id"' : '' ?>>
            <option value="">Select shop</option>
            <?php foreach ($shops as $owned_shop): ?>
              <?php $selected = (string)$data['shop_id'] === (string)$owned_shop['SHOP_ID'] ? ' selected' : ''; ?>
              <option value="<?= h((string)$owned_shop['SHOP_ID']) ?>"<?= $selected ?>><?= h((string)$owned_shop['SHOP_NAME']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['shop_id'])): ?>
            <span class="field-error" id="err_shop_id" role="alert"><?= h((string)$errors['shop_id']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['product_name']) ? ' has-error' : '' ?>">
          <label for="product_name">Product Name <span class="required-star" aria-hidden="true">*</span></label>
          <input type="text" id="product_name" name="product_name" maxlength="100" required
                 <?= isset($errors['product_name']) ? 'aria-invalid="true" aria-describedby="err_product_name"' : '' ?>
                 value="<?= h((string)$data['product_name']) ?>">
          <?php if (isset($errors['product_name'])): ?>
            <span class="field-error" id="err_product_name" role="alert"><?= h((string)$errors['product_name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
          <label for="category_id">Category <span class="required-star" aria-hidden="true">*</span></label>
          <select id="category_id" name="category_id" required
                  <?= isset($errors['category_id']) ? 'aria-invalid="true" aria-describedby="err_category_id"' : '' ?>>
            <option value="">Select category</option>
            <?php foreach ($categories as $category): ?>
              <?php $selected = (string)$data['category_id'] === (string)$category['CATEGORY_ID'] ? ' selected' : ''; ?>
              <option value="<?= h((string)$category['CATEGORY_ID']) ?>"<?= $selected ?>><?= h((string)$category['CATEGORY_NAME']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category_id'])): ?>
            <span class="field-error" id="err_category_id" role="alert"><?= h((string)$errors['category_id']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['price']) ? ' has-error' : '' ?>">
          <label for="price">Price (GBP) <span class="required-star" aria-hidden="true">*</span></label>
          <input type="number" id="price" name="price" step="0.01" min="0.01" required
                 <?= isset($errors['price']) ? 'aria-invalid="true" aria-describedby="err_price"' : '' ?>
                 value="<?= h((string)$data['price']) ?>">
          <?php if (isset($errors['price'])): ?>
            <span class="field-error" id="err_price" role="alert"><?= h((string)$errors['price']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['stock_quantity']) ? ' has-error' : '' ?>">
          <label for="stock_quantity">Stock Quantity <span class="required-star" aria-hidden="true">*</span></label>
          <input type="number" id="stock_quantity" name="stock_quantity" min="0" required
                 <?= isset($errors['stock_quantity']) ? 'aria-invalid="true" aria-describedby="err_stock_quantity"' : '' ?>
                 value="<?= h((string)$data['stock_quantity']) ?>">
          <?php if (isset($errors['stock_quantity'])): ?>
            <span class="field-error" id="err_stock_quantity" role="alert"><?= h((string)$errors['stock_quantity']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['quantity_per_item']) ? ' has-error' : '' ?>">
          <label for="quantity_per_item">Quantity Per Item <span class="field-optional">(optional)</span></label>
          <input type="number" id="quantity_per_item" name="quantity_per_item" min="0"
                 <?= isset($errors['quantity_per_item']) ? 'aria-invalid="true" aria-describedby="err_quantity_per_item"' : '' ?>
                 value="<?= h((string)$data['quantity_per_item']) ?>">
          <?php if (isset($errors['quantity_per_item'])): ?>
            <span class="field-error" id="err_quantity_per_item" role="alert"><?= h((string)$errors['quantity_per_item']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['expiry_date']) ? ' has-error' : '' ?>">
          <label for="expiry_date">Expiry Date <span class="field-optional">(optional)</span></label>
          <input type="date" id="expiry_date" name="expiry_date"
                 <?= isset($errors['expiry_date']) ? 'aria-invalid="true" aria-describedby="err_expiry_date"' : '' ?>
                 value="<?= h((string)$data['expiry_date']) ?>">
          <?php if (isset($errors['expiry_date'])): ?>
            <span class="field-error" id="err_expiry_date" role="alert"><?= h((string)$errors['expiry_date']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['min_order']) ? ' has-error' : '' ?>">
          <label for="min_order">Minimum Order <span class="field-optional">(optional)</span></label>
          <input type="number" id="min_order" name="min_order" min="0"
                 <?= isset($errors['min_order']) ? 'aria-invalid="true" aria-describedby="err_min_order"' : '' ?>
                 value="<?= h((string)$data['min_order']) ?>">
          <?php if (isset($errors['min_order'])): ?>
            <span class="field-error" id="err_min_order" role="alert"><?= h((string)$errors['min_order']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['max_order']) ? ' has-error' : '' ?>">
          <label for="max_order">Maximum Order <span class="field-optional">(optional)</span></label>
          <input type="number" id="max_order" name="max_order" min="0"
                 <?= isset($errors['max_order']) ? 'aria-invalid="true" aria-describedby="err_max_order"' : '' ?>
                 value="<?= h((string)$data['max_order']) ?>">
          <?php if (isset($errors['max_order'])): ?>
            <span class="field-error" id="err_max_order" role="alert"><?= h((string)$errors['max_order']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
          <label for="description">Description <span class="field-optional">(optional)</span></label>
          <textarea id="description" name="description" maxlength="200"
                    <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="err_description"' : '' ?>><?= h((string)$data['description']) ?></textarea>
          <?php if (isset($errors['description'])): ?>
            <span class="field-error" id="err_description" role="alert"><?= h((string)$errors['description']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['allergy_info']) ? ' has-error' : '' ?>">
          <label for="allergy_info">Allergy Information <span class="field-optional">(optional)</span></label>
          <textarea id="allergy_info" name="allergy_info" maxlength="200"
                    <?= isset($errors['allergy_info']) ? 'aria-invalid="true" aria-describedby="err_allergy_info"' : '' ?>><?= h((string)$data['allergy_info']) ?></textarea>
          <?php if (isset($errors['allergy_info'])): ?>
            <span class="field-error" id="err_allergy_info" role="alert"><?= h((string)$errors['allergy_info']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="image">Replace Product Image <span class="field-optional">(optional)</span></label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
          <span class="field-note">JPG, PNG, or WEBP &mdash; max 2 MB. Leave blank to keep the current image.</span>
        </div>
        <div class="field<?= isset($errors['status']) ? ' has-error' : '' ?>">
          <label for="status">Status <span class="required-star" aria-hidden="true">*</span></label>
          <?php $status = strtoupper((string)$data['status']); ?>
          <select id="status" name="status"
                  <?= isset($errors['status']) ? 'aria-invalid="true" aria-describedby="err_status"' : '' ?>>
            <option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option>
            <option value="INACTIVE"<?= $status === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option>
            <option value="DISCONTINUED"<?= $status === 'DISCONTINUED' ? ' selected' : '' ?>>Discontinued</option>
          </select>
          <?php if (isset($errors['status'])): ?>
            <span class="field-error" id="err_status" role="alert"><?= h((string)$errors['status']) ?></span>
          <?php endif; ?>
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
