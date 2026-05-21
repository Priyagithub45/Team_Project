<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$categories = trader_categories($conn);
$errors = trader_product_errors_get();
$old = trader_old_get();
$shop_context = trader_shop_context($conn, $current_trader_id, false);
$shop = $shop_context['selected_shop'];
$shops = $shop_context['shops'];
$selected_shop_id = (string)($old['shop_id'] ?? ($shop_context['selected_shop_id'] ?? ''));
$account_name = trader_account_label($current_trader);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Product &mdash; Trader Portal</title>
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
    <a href="add_product.php" class="active">Add Product</a>
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

<div class="header">Add Product</div>

<div class="main-wrap form-page">
  <div class="form-card product-form-card">
    <div class="application-card-heading">
      <span class="apply-eyebrow">Inventory</span>
      <h2>Add Product</h2>
      <p>Choose which shop should own this product. Only shops linked to your trader account are available.</p>
    </div>

    <?php if (!$shop): ?>
      <div class="alert alert-error">No shop is linked to this trader account. Please ask admin to create your shop before adding products.</div>
    <?php endif; ?>

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

    <form method="post" action="save_product.php" enctype="multipart/form-data" class="application-form" novalidate>
      <?= csrf_field('trader_product_save') ?>
      <div class="form-row">
        <div class="field<?= isset($errors['shop_id']) ? ' has-error' : '' ?>">
          <label for="shop_id">Shop <span class="required-star" aria-hidden="true">*</span></label>
          <select id="shop_id" name="shop_id" required<?= empty($shops) ? ' disabled' : '' ?>
                  <?= isset($errors['shop_id']) ? 'aria-invalid="true" aria-describedby="err_shop_id"' : '' ?>>
            <option value="">Select shop</option>
            <?php foreach ($shops as $owned_shop): ?>
              <?php $selected = $selected_shop_id === (string)$owned_shop['SHOP_ID'] ? ' selected' : ''; ?>
              <option value="<?= h((string)$owned_shop['SHOP_ID']) ?>"<?= $selected ?>><?= h((string)$owned_shop['SHOP_NAME']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['shop_id'])): ?>
            <span class="field-error" id="err_shop_id" role="alert"><?= h((string)$errors['shop_id']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['product_name']) ? ' has-error' : '' ?>">
          <label for="product_name">Product Name <span class="required-star" aria-hidden="true">*</span></label>
          <input type="text" id="product_name" name="product_name" maxlength="100" required
                 <?= isset($errors['product_name']) ? 'aria-invalid="true" aria-describedby="err_product_name"' : '' ?>
                 value="<?= h((string)($old['product_name'] ?? '')) ?>">
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
              <?php $selected = (string)($old['category_id'] ?? '') === (string)$category['CATEGORY_ID'] ? ' selected' : ''; ?>
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
                 value="<?= h((string)($old['price'] ?? '')) ?>">
          <?php if (isset($errors['price'])): ?>
            <span class="field-error" id="err_price" role="alert"><?= h((string)$errors['price']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['discount_rate']) ? ' has-error' : '' ?>">
          <label for="discount_rate">Discount (%) <span class="field-optional">(optional)</span></label>
          <input type="number" id="discount_rate" name="discount_rate" step="0.01" min="0" max="99.99"
                 <?= isset($errors['discount_rate']) ? 'aria-invalid="true" aria-describedby="err_discount_rate"' : '' ?>
                 value="<?= h((string)($old['discount_rate'] ?? '')) ?>">
          <span class="field-note">Enter 0 or leave blank for no discount.</span>
          <?php if (isset($errors['discount_rate'])): ?>
            <span class="field-error" id="err_discount_rate" role="alert"><?= h((string)$errors['discount_rate']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['stock_quantity']) ? ' has-error' : '' ?>">
          <label for="stock_quantity">Stock Quantity <span class="required-star" aria-hidden="true">*</span></label>
          <input type="number" id="stock_quantity" name="stock_quantity" min="0" required
                 <?= isset($errors['stock_quantity']) ? 'aria-invalid="true" aria-describedby="err_stock_quantity"' : '' ?>
                 value="<?= h((string)($old['stock_quantity'] ?? '')) ?>">
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
                 value="<?= h((string)($old['quantity_per_item'] ?? '')) ?>">
          <?php if (isset($errors['quantity_per_item'])): ?>
            <span class="field-error" id="err_quantity_per_item" role="alert"><?= h((string)$errors['quantity_per_item']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['expiry_date']) ? ' has-error' : '' ?>">
          <label for="expiry_date">Expiry Date <span class="field-optional">(optional)</span></label>
          <input type="date" id="expiry_date" name="expiry_date"
                 <?= isset($errors['expiry_date']) ? 'aria-invalid="true" aria-describedby="err_expiry_date"' : '' ?>
                 value="<?= h((string)($old['expiry_date'] ?? '')) ?>">
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
                 value="<?= h((string)($old['min_order'] ?? '')) ?>">
          <?php if (isset($errors['min_order'])): ?>
            <span class="field-error" id="err_min_order" role="alert"><?= h((string)$errors['min_order']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['max_order']) ? ' has-error' : '' ?>">
          <label for="max_order">Maximum Order <span class="field-optional">(optional)</span></label>
          <input type="number" id="max_order" name="max_order" min="0"
                 <?= isset($errors['max_order']) ? 'aria-invalid="true" aria-describedby="err_max_order"' : '' ?>
                 value="<?= h((string)($old['max_order'] ?? '')) ?>">
          <?php if (isset($errors['max_order'])): ?>
            <span class="field-error" id="err_max_order" role="alert"><?= h((string)$errors['max_order']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
          <label for="description">Description <span class="field-optional">(optional)</span></label>
          <textarea id="description" name="description" maxlength="200"
                    <?= isset($errors['description']) ? 'aria-invalid="true" aria-describedby="err_description"' : '' ?>><?= h((string)($old['description'] ?? '')) ?></textarea>
          <?php if (isset($errors['description'])): ?>
            <span class="field-error" id="err_description" role="alert"><?= h((string)$errors['description']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['allergy_info']) ? ' has-error' : '' ?>">
          <label for="allergy_info">Allergy Information <span class="field-optional">(optional)</span></label>
          <textarea id="allergy_info" name="allergy_info" maxlength="200"
                    <?= isset($errors['allergy_info']) ? 'aria-invalid="true" aria-describedby="err_allergy_info"' : '' ?>><?= h((string)($old['allergy_info'] ?? '')) ?></textarea>
          <?php if (isset($errors['allergy_info'])): ?>
            <span class="field-error" id="err_allergy_info" role="alert"><?= h((string)$errors['allergy_info']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['image']) ? ' has-error' : '' ?>">
          <label for="image">Product Image <span class="required-star" aria-hidden="true">*</span></label>
          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" required
                 <?= isset($errors['image']) ? 'aria-invalid="true" aria-describedby="err_image"' : '' ?>>
          <span class="field-note">JPG, PNG, or WEBP &mdash; max 2 MB.</span>
          <?php if (isset($errors['image'])): ?>
            <span class="field-error" id="err_image" role="alert"><?= h((string)$errors['image']) ?></span>
          <?php endif; ?>
        </div>
        <div class="field<?= isset($errors['status']) ? ' has-error' : '' ?>">
          <label for="status">Status <span class="required-star" aria-hidden="true">*</span></label>
          <select id="status" name="status"
                  <?= isset($errors['status']) ? 'aria-invalid="true" aria-describedby="err_status"' : '' ?>>
            <?php $status = strtoupper((string)($old['status'] ?? 'ACTIVE')); ?>
            <option value="ACTIVE"<?= $status === 'ACTIVE' ? ' selected' : '' ?>>Active</option>
            <option value="INACTIVE"<?= $status === 'INACTIVE' ? ' selected' : '' ?>>Inactive</option>
          </select>
          <?php if (isset($errors['status'])): ?>
            <span class="field-error" id="err_status" role="alert"><?= h((string)$errors['status']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"<?= !$shop ? ' disabled' : '' ?>>Save Product</button>
        <a href="products.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
