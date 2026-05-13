<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';
require_once 'profile_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$shop_context = trader_shop_context($conn, $current_trader_id, false);

// Load all shop profiles in one query
$all_profiles = trader_fetch_all_shop_profiles($conn, $current_trader_id);
$base_profile = $all_profiles[0] ?? $current_trader ?? [];

$flash  = trader_profile_flash_get();
$errors = trader_profile_errors_get();
$old    = trader_profile_old_get();

$err_section = $errors['_section'] ?? '';
$err_shop_id = (int)($errors['_shop_id'] ?? 0);
$old_section = $old['_section']    ?? '';
$old_shop_id = (int)($old['shop_id'] ?? 0);

// Personal data — repopulate from $old when the personal form had an error
$personal_data = [
    'name'    => (string)($base_profile['NAME']     ?? ''),
    'email'   => (string)($base_profile['EMAIL']    ?? ''),
    'phone'   => (string)($base_profile['PHONE_NO'] ?? ''),
    'address' => (string)($base_profile['ADDRESS']  ?? ''),
];
if ($old_section === 'personal') {
    foreach (['name', 'email', 'phone', 'address'] as $k) {
        if (isset($old[$k])) {
            $personal_data[$k] = (string)$old[$k];
        }
    }
}

// Personal-form errors (only if the personal section failed)
$personal_errors  = ($err_section === 'personal') ? $errors : [];
$password_errors  = ($err_section === 'password')  ? $errors : [];

$user_status   = trim((string)($base_profile['USER_STATUS']   ?? 'Active')) ?: 'Active';
$trader_status = trim((string)($base_profile['TRADER_STATUS'] ?? 'ACTIVE')) ?: 'ACTIVE';
$account_name  = trader_account_label($current_trader);

$shop_description_supported = trader_shop_description_column_exists($conn);
$shop_image_supported       = trader_shop_image_column_exists($conn);

// Build per-shop data keyed by SHOP_ID
$shops_data = [];
foreach ($all_profiles as $p) {
    if (empty($p['SHOP_ID'])) {
        continue;
    }
    $sid = (int)$p['SHOP_ID'];
    $shops_data[$sid] = [
        'shop_id'          => $sid,
        'shop_name'        => (string)($p['SHOP_NAME']       ?? ''),
        'shop_location'    => (string)($p['SHOP_LOCATION']   ?? ''),
        'shop_contact'     => (string)($p['SHOP_CONTACT_NO'] ?? ''),
        'shop_description' => (string)($p['SHOP_DESCRIPTION'] ?? ''),
        'shop_image_path'  => trim((string)($p['SHOP_IMAGE_PATH'] ?? '')),
    ];
    // Repopulate old values if this is the shop that had an error
    if ($old_section === 'shop' && $old_shop_id === $sid) {
        foreach (['shop_name', 'shop_location', 'shop_contact', 'shop_description'] as $k) {
            if (isset($old[$k])) {
                $shops_data[$sid][$k] = (string)$old[$k];
            }
        }
    }
}

$shop_count = count($shops_data);
$initial    = strtoupper(substr($personal_data['name'], 0, 1) ?: 'T');

// First shop image for hero (if available)
$hero_image = '';
foreach ($shops_data as $sd) {
    if ($sd['shop_image_path'] !== '') {
        $hero_image = $sd['shop_image_path'];
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile &mdash; Trader Portal</title>
  <link rel="stylesheet" href="trader.css?v=profile-compact-20260513">
  <style>
    .profile-v2-hero-avatar,
    .profile-v2-hero-letter {
      width: 48px !important;
      height: 48px !important;
      max-width: 48px !important;
      max-height: 48px !important;
      overflow: hidden !important;
    }

    .profile-v2-hero-img,
    .profile-v2-hero-avatar img {
      width: 48px !important;
      height: 48px !important;
      max-width: 48px !important;
      max-height: 48px !important;
      object-fit: cover !important;
      display: block !important;
    }

    .profile-v2-shop-thumb,
    .profile-v2-shop-letter {
      width: 40px !important;
      height: 40px !important;
      min-width: 40px !important;
      max-width: 40px !important;
      max-height: 40px !important;
    }

    .profile-v2-current-img img {
      width: 96px !important;
      height: 64px !important;
      max-width: 96px !important;
      max-height: 64px !important;
      object-fit: cover !important;
      display: block !important;
    }
  </style>
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
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="profile.php" class="active">Profile</a>
  </nav>
  <?php trader_render_shop_switcher($shop_context); ?>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Profile</div>

<div class="main-wrap profile-v2-wrap">

  <!-- ── Hero banner ─────────────────────────────────────────────────────── -->
  <section class="profile-v2-hero">
    <div class="profile-v2-hero-avatar">
      <?php if ($shop_image_supported && $hero_image !== ''): ?>
        <img src="../<?= h($hero_image) ?>" alt="<?= h($account_name) ?>" class="profile-v2-hero-img" width="48" height="48">
      <?php else: ?>
        <div class="profile-v2-hero-letter"><?= h($initial) ?></div>
      <?php endif; ?>
    </div>
    <div class="profile-v2-hero-info">
      <span class="apply-eyebrow">Trader Account</span>
      <h1 class="profile-v2-hero-name"><?= h($personal_data['name'] ?: $account_name) ?></h1>
      <p class="profile-v2-hero-sub">
        <?= h($account_name) ?>
        <?php if ($personal_data['email'] !== ''): ?>
          <span aria-hidden="true"> / </span><?= h($personal_data['email']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="profile-v2-hero-meta">
      <div class="profile-v2-badge-row">
        <span class="status-box status-active">User: <?= h($user_status) ?></span>
        <span class="status-box status-active">Trader: <?= h($trader_status) ?></span>
      </div>
      <div class="profile-v2-shop-pill">
        <strong><?= $shop_count ?></strong>
        <span><?= $shop_count === 1 ? 'Shop' : 'Shops' ?></span>
      </div>
    </div>
  </section>

  <!-- ── Flash / global errors ───────────────────────────────────────────── -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type'] === 'success' ? 'success' : 'error') ?> profile-v2-alert">
      <?= h((string)$flash['message']) ?>
    </div>
  <?php endif; ?>

  <?php
  $general_error = $errors['_general'] ?? null;
  if ($general_error): ?>
    <div class="alert alert-error profile-v2-alert"><?= h((string)$general_error) ?></div>
  <?php endif; ?>

  <!-- ── Two-column layout ───────────────────────────────────────────────── -->
  <div class="profile-v2-layout">

    <!-- LEFT: Personal details + locked info ──────────────────────────────── -->
    <aside class="profile-v2-personal-col">

      <!-- Personal details form -->
      <div class="profile-v2-card">
        <div class="profile-v2-card-header">
          <span class="apply-eyebrow">Account</span>
          <h2>Personal Details</h2>
        </div>

        <?php if (count($personal_errors) > 1): ?>
          <div class="alert alert-error" style="margin-bottom:18px">
            <ul>
              <?php foreach ($personal_errors as $key => $msg):
                if (substr((string)$key, 0, 1) === '_') { continue; }
              ?>
                <li><?= h((string)$msg) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="save_profile.php" class="application-form" novalidate>
          <?= csrf_field('trader_profile') ?>
          <input type="hidden" name="save_section" value="personal">

          <div class="field<?= isset($personal_errors['name']) ? ' has-error' : '' ?>">
            <label for="p_name">Full Name <span class="required-star" aria-hidden="true">*</span></label>
            <input type="text" id="p_name" name="name" maxlength="100" required
                   <?= isset($personal_errors['name']) ? 'aria-invalid="true" aria-describedby="err_p_name"' : '' ?>
                   value="<?= h($personal_data['name']) ?>">
            <?php if (isset($personal_errors['name'])): ?>
              <span class="field-error" id="err_p_name" role="alert"><?= h((string)$personal_errors['name']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($personal_errors['email']) ? ' has-error' : '' ?>">
            <label for="p_email">Email Address <span class="required-star" aria-hidden="true">*</span></label>
            <input type="email" id="p_email" name="email" maxlength="100" required autocomplete="email"
                   <?= isset($personal_errors['email']) ? 'aria-invalid="true" aria-describedby="err_p_email"' : '' ?>
                   value="<?= h($personal_data['email']) ?>">
            <?php if (isset($personal_errors['email'])): ?>
              <span class="field-error" id="err_p_email" role="alert"><?= h((string)$personal_errors['email']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($personal_errors['phone']) ? ' has-error' : '' ?>">
            <label for="p_phone">Phone <span class="field-optional">(optional)</span></label>
            <input type="text" id="p_phone" name="phone" maxlength="20"
                   <?= isset($personal_errors['phone']) ? 'aria-invalid="true" aria-describedby="err_p_phone"' : '' ?>
                   value="<?= h($personal_data['phone']) ?>">
            <?php if (isset($personal_errors['phone'])): ?>
              <span class="field-error" id="err_p_phone" role="alert"><?= h((string)$personal_errors['phone']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($personal_errors['address']) ? ' has-error' : '' ?>">
            <label for="p_address">Address <span class="field-optional">(optional)</span></label>
            <textarea id="p_address" name="address" maxlength="200"
                      <?= isset($personal_errors['address']) ? 'aria-invalid="true" aria-describedby="err_p_address"' : '' ?>><?= h($personal_data['address']) ?></textarea>
            <?php if (isset($personal_errors['address'])): ?>
              <span class="field-error" id="err_p_address" role="alert"><?= h((string)$personal_errors['address']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-actions" style="margin-top:16px">
            <button type="submit" class="btn btn-primary profile-v2-save-btn">Save Details</button>
          </div>
        </form>
      </div>

      <!-- Change password -->
      <div class="profile-v2-card">
        <div class="profile-v2-card-header">
          <span class="apply-eyebrow">Security</span>
          <h2>Change Password</h2>
        </div>

        <?php if (!empty($password_errors['_general'])): ?>
          <div class="alert alert-error" style="margin-bottom:18px"><?= h((string)$password_errors['_general']) ?></div>
        <?php endif; ?>

        <form method="post" action="save_profile.php" novalidate>
          <?= csrf_field('trader_profile') ?>
          <input type="hidden" name="save_section" value="password">

          <div class="field<?= isset($password_errors['current_password']) ? ' has-error' : '' ?>">
            <label for="pw_current">Current Password <span class="required-star" aria-hidden="true">*</span></label>
            <input type="password" id="pw_current" name="current_password" required autocomplete="current-password">
            <?php if (isset($password_errors['current_password'])): ?>
              <span class="field-error" role="alert"><?= h((string)$password_errors['current_password']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($password_errors['new_password']) ? ' has-error' : '' ?>">
            <label for="pw_new">New Password <span class="required-star" aria-hidden="true">*</span></label>
            <input type="password" id="pw_new" name="new_password" required autocomplete="new-password" minlength="8">
            <?php if (isset($password_errors['new_password'])): ?>
              <span class="field-error" role="alert"><?= h((string)$password_errors['new_password']) ?></span>
            <?php else: ?>
              <span class="field-note">Minimum 8 characters.</span>
            <?php endif; ?>
          </div>

          <div class="field<?= isset($password_errors['confirm_password']) ? ' has-error' : '' ?>">
            <label for="pw_confirm">Confirm New Password <span class="required-star" aria-hidden="true">*</span></label>
            <input type="password" id="pw_confirm" name="confirm_password" required autocomplete="new-password">
            <?php if (isset($password_errors['confirm_password'])): ?>
              <span class="field-error" role="alert"><?= h((string)$password_errors['confirm_password']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-actions" style="margin-top:16px">
            <button type="submit" class="btn btn-primary profile-v2-save-btn">Change Password</button>
          </div>
        </form>
      </div>

      <!-- Locked / read-only info -->
      <div class="profile-v2-card profile-v2-locked-card">
        <div class="profile-v2-card-header">
          <span class="apply-eyebrow">Admin Controlled</span>
          <h2>Account Info</h2>
        </div>
        <div class="profile-v2-info-grid">
          <div class="profile-v2-info-cell">
            <span>Trader ID</span>
            <strong><?= h((string)$current_trader_id) ?></strong>
          </div>
          <div class="profile-v2-info-cell">
            <span>Shops</span>
            <strong><?= $shop_count ?></strong>
          </div>
          <div class="profile-v2-info-cell">
            <span>User Status</span>
            <strong><?= h($user_status) ?></strong>
          </div>
          <div class="profile-v2-info-cell">
            <span>Trader Status</span>
            <strong><?= h($trader_status) ?></strong>
          </div>
        </div>
      </div>

    </aside>

    <!-- RIGHT: Shop cards ─────────────────────────────────────────────────── -->
    <div class="profile-v2-shops-col">
      <div class="profile-v2-section-head">
        <div>
          <span class="apply-eyebrow">Shopfronts</span>
          <h2>Business Profiles</h2>
        </div>
        <span><?= h((string)$shop_count) ?> assigned</span>
      </div>

      <?php if (empty($shops_data)): ?>
        <div class="empty-state" style="padding:40px">
          No shops are assigned to this account yet. Contact an administrator.
        </div>
      <?php else: ?>
        <div class="profile-v2-shops-grid">

          <?php foreach ($shops_data as $sid => $shop):
            $shop_errors = ($err_section === 'shop' && $err_shop_id === $sid) ? $errors : [];
            $shop_letter = strtoupper(substr($shop['shop_name'], 0, 1) ?: 'S');
          ?>
          <div class="profile-v2-shop-card">

            <!-- Shop card header -->
            <div class="profile-v2-shop-header">
              <?php if ($shop_image_supported && $shop['shop_image_path'] !== ''): ?>
                <div class="profile-v2-shop-thumb"
                     style="background-image:url('../<?= h($shop['shop_image_path']) ?>')"></div>
              <?php else: ?>
                <div class="profile-v2-shop-letter"><?= h($shop_letter) ?></div>
              <?php endif; ?>
              <div>
                <span class="profile-v2-shop-kicker">Shop #<?= h((string)$sid) ?></span>
                <h3 class="profile-v2-shop-title"><?= h($shop['shop_name'] ?: 'Unnamed Shop') ?></h3>
              </div>
            </div>

            <!-- Shop form -->
            <?php if (count($shop_errors) > 2): ?>
              <div class="alert alert-error" style="margin:16px 24px 0">
                <ul>
                  <?php foreach ($shop_errors as $key => $msg):
                    if (substr((string)$key, 0, 1) === '_') { continue; }
                  ?>
                    <li><?= h((string)$msg) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="post" action="save_profile.php" enctype="multipart/form-data" class="application-form profile-v2-shop-form" novalidate>
              <?= csrf_field('trader_profile') ?>
              <input type="hidden" name="save_section" value="shop">
              <input type="hidden" name="shop_id" value="<?= h((string)$sid) ?>">

              <div class="form-row">
                <div class="field<?= isset($shop_errors['shop_name']) ? ' has-error' : '' ?>">
                  <label for="sn_<?= $sid ?>">Shop Name <span class="required-star" aria-hidden="true">*</span></label>
                  <input type="text" id="sn_<?= $sid ?>" name="shop_name" maxlength="100" required
                         <?= isset($shop_errors['shop_name']) ? 'aria-invalid="true"' : '' ?>
                         value="<?= h($shop['shop_name']) ?>">
                  <?php if (isset($shop_errors['shop_name'])): ?>
                    <span class="field-error" role="alert"><?= h((string)$shop_errors['shop_name']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="field<?= isset($shop_errors['shop_contact']) ? ' has-error' : '' ?>">
                  <label for="sc_<?= $sid ?>">Contact No. <span class="field-optional">(optional)</span></label>
                  <input type="text" id="sc_<?= $sid ?>" name="shop_contact" maxlength="20"
                         value="<?= h($shop['shop_contact']) ?>">
                  <?php if (isset($shop_errors['shop_contact'])): ?>
                    <span class="field-error" role="alert"><?= h((string)$shop_errors['shop_contact']) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="field<?= isset($shop_errors['shop_location']) ? ' has-error' : '' ?>">
                <label for="sl_<?= $sid ?>">Location / Address <span class="field-optional">(optional)</span></label>
                <textarea id="sl_<?= $sid ?>" name="shop_location" maxlength="100"
                          rows="2"><?= h($shop['shop_location']) ?></textarea>
                <?php if (isset($shop_errors['shop_location'])): ?>
                  <span class="field-error" role="alert"><?= h((string)$shop_errors['shop_location']) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($shop_description_supported): ?>
              <div class="field<?= isset($shop_errors['shop_description']) ? ' has-error' : '' ?>">
                <label for="sd_<?= $sid ?>">Description <span class="field-optional">(optional)</span></label>
                <textarea id="sd_<?= $sid ?>" name="shop_description" maxlength="500"
                          rows="3"><?= h($shop['shop_description']) ?></textarea>
                <?php if (isset($shop_errors['shop_description'])): ?>
                  <span class="field-error" role="alert"><?= h((string)$shop_errors['shop_description']) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <?php if ($shop_image_supported): ?>
              <div class="field<?= isset($shop_errors['shop_image']) ? ' has-error' : '' ?>">
                <label for="si_<?= $sid ?>">Shop Image <span class="field-optional">(optional)</span></label>
                <?php if ($shop['shop_image_path'] !== ''): ?>
                  <div class="profile-v2-current-img">
                    <img src="../<?= h($shop['shop_image_path']) ?>" alt="Current shop image" width="96" height="64">
                    <span>Current image</span>
                  </div>
                <?php endif; ?>
                <input type="file" id="si_<?= $sid ?>" name="shop_image" accept="image/*"
                       <?= isset($shop_errors['shop_image']) ? 'aria-invalid="true"' : '' ?>>
                <span class="field-note">JPG, PNG, or WEBP &mdash; max 2 MB. Leave blank to keep current.</span>
                <?php if (isset($shop_errors['shop_image'])): ?>
                  <span class="field-error" role="alert"><?= h((string)$shop_errors['shop_image']) ?></span>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <div class="profile-v2-shop-footer">
                <button type="submit" class="btn btn-primary profile-v2-save-btn">
                  Save Shop
                </button>
              </div>
            </form>
          </div>
          <?php endforeach; ?>

        </div><!-- /.profile-v2-shops-grid -->
      <?php endif; ?>

    </div><!-- /.profile-v2-shops-col -->

  </div><!-- /.profile-v2-layout -->

</div><!-- /.main-wrap -->

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
