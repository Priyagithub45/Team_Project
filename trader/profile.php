<?php
require_once 'auth_check.php';
require_once 'profile_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$profile = trader_fetch_profile($conn, $current_trader_id);
if (!$profile) {
    $profile = $current_trader;
}

$flash = trader_profile_flash_get();
$errors = trader_profile_errors_get();
$old = trader_profile_old_get();

$data = array_merge([
    'name' => $profile['NAME'] ?? '',
    'email' => $profile['EMAIL'] ?? '',
    'phone' => $profile['PHONE_NO'] ?? '',
    'address' => $profile['ADDRESS'] ?? '',
    'shop_name' => $profile['SHOP_NAME'] ?? $profile['BUSINESS_NAME'] ?? '',
    'shop_location' => $profile['SHOP_LOCATION'] ?? '',
    'shop_contact' => $profile['SHOP_CONTACT_NO'] ?? '',
    'shop_description' => $profile['SHOP_DESCRIPTION'] ?? '',
], $old);

$user_status = trim((string)($profile['USER_STATUS'] ?? 'Active')) ?: 'Active';
$trader_status = trim((string)($profile['TRADER_STATUS'] ?? 'ACTIVE')) ?: 'ACTIVE';
$shop_description_supported = trader_shop_description_column_exists($conn);
$shop_image_supported = trader_shop_image_column_exists($conn);
$shop_image_path = trim((string)($profile['SHOP_IMAGE_PATH'] ?? ''));
$initial = strtoupper(substr((string)$data['name'], 0, 1) ?: 'T');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile &mdash; Trader Portal</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h((string)($data['shop_name'] ?: 'Your Shop')) ?></h2>
    <span class="sidebar-label">Trader Portal</span>
  </div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php" class="active">Profile</a>
  </nav>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Profile</div>

<div class="main-wrap form-page">
  <div class="form-card profile-form-card">
    <div class="profile-summary">
      <?php if ($shop_image_supported && $shop_image_path !== ''): ?>
        <div class="profile-shop-img">
          <img src="../<?= h($shop_image_path) ?>" alt="<?= h((string)$data['shop_name']) ?>" width="80" height="80" style="object-fit:cover;display:block;">
        </div>
      <?php else: ?>
        <div class="profile-avatar"><?= h($initial) ?></div>
      <?php endif; ?>
      <div>
        <span class="apply-eyebrow">Trader Profile</span>
        <h2><?= h((string)($data['shop_name'] ?: 'Your Shop')) ?></h2>
        <p><?= h((string)$data['name']) ?> - <?= h((string)$data['email']) ?></p>
      </div>
      <div class="profile-statuses">
        <span class="status-box status-active">User: <?= h($user_status) ?></span>
        <span class="status-box status-active">Trader: <?= h($trader_status) ?></span>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type'] === 'success' ? 'success' : 'error') ?>"><?= h((string)$flash['message']) ?></div>
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

    <form method="post" action="save_profile.php" enctype="multipart/form-data" class="application-form">
      <section class="profile-section">
        <div class="panel-heading">
          <div>
            <span class="apply-eyebrow">Account</span>
            <h2>Personal Details</h2>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="name">Owner / Full Name</label>
            <input type="text" id="name" name="name" maxlength="100" required value="<?= h((string)$data['name']) ?>">
          </div>
          <div class="field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" maxlength="100" required value="<?= h((string)$data['email']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="phone">Phone Number</label>
            <input type="text" id="phone" name="phone" maxlength="20" value="<?= h((string)$data['phone']) ?>">
          </div>
          <div class="field">
            <label for="address">Address</label>
            <textarea id="address" name="address" maxlength="200"><?= h((string)$data['address']) ?></textarea>
          </div>
        </div>
      </section>

      <section class="profile-section">
        <div class="panel-heading">
          <div>
            <span class="apply-eyebrow">Shop</span>
            <h2>Business Details</h2>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="shop_name">Shop Name</label>
            <input type="text" id="shop_name" name="shop_name" maxlength="100" required value="<?= h((string)$data['shop_name']) ?>">
          </div>
          <div class="field">
            <label for="shop_contact">Shop Contact Number</label>
            <input type="text" id="shop_contact" name="shop_contact" maxlength="20" value="<?= h((string)$data['shop_contact']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="shop_location">Shop Location / Address</label>
            <textarea id="shop_location" name="shop_location" maxlength="100"><?= h((string)$data['shop_location']) ?></textarea>
          </div>
          <div class="field">
            <label for="shop_description">Shop Description</label>
            <textarea id="shop_description" name="shop_description" maxlength="500"<?= !$shop_description_supported ? ' disabled' : '' ?>><?= h((string)$data['shop_description']) ?></textarea>
            <?php if (!$shop_description_supported): ?>
              <small class="field-note">Run migration 007_add_shop_description.sql to enable this field.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label for="shop_image">Shop Image</label>
            <?php if ($shop_image_supported && $shop_image_path !== ''): ?>
              <div class="shop-img-thumb">
                <img src="../<?= h($shop_image_path) ?>" alt="<?= h((string)$data['shop_name']) ?>" width="120" height="90" style="object-fit:cover;display:block;">
              </div>
            <?php endif; ?>
            <input type="file" id="shop_image" name="shop_image" accept="image/*">
            <?php if (!$shop_image_supported): ?>
              <small class="field-note">Run migration 009_add_shop_image_path.sql to enable image uploads.</small>
            <?php else: ?>
              <small class="field-note">JPG, PNG, or WEBP &mdash; max 2 MB.</small>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="profile-section locked-section">
        <div class="panel-heading">
          <div>
            <span class="apply-eyebrow">Admin Controlled</span>
            <h2>Locked Fields</h2>
          </div>
        </div>
        <div class="locked-grid">
          <div><span>Trader ID</span><strong><?= h((string)$current_trader_id) ?></strong></div>
          <div><span>Shop ID</span><strong><?= h((string)($profile['SHOP_ID'] ?? '-')) ?></strong></div>
          <div><span>User Status</span><strong><?= h($user_status) ?></strong></div>
          <div><span>Trader Status</span><strong><?= h($trader_status) ?></strong></div>
        </div>
      </section>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Profile</button>
        <a class="btn btn-ghost" href="dashboard.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
