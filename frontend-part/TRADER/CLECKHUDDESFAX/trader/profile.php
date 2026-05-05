<?php
$trader = [
  'name' => 'John Smith',
  'email' => 'johnsmith@gmail.com',
  'phone' => '+44 xxxx xxxxxx',
  'role' => 'Trader',
  'shop_name' => 'Greengrocer'
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="logo">
    <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
  </div>
  <a href="dashboard.php">Dashboard</a>
  <a href="add_product.php">Add Product</a>
  <a href="profile.php" class="active">Profile</a>
  <a href="logout.php">Logout</a>
</div>

<div class="header">Profile</div>

<div class="main-wrap form-page">
  <div class="form-card" style="max-width:700px">
    <form method="post" action="save_profile.php">
      <div class="profile-card">
        <div class="profile-avatar">
          <?php echo strtoupper(substr($trader['name'],0,1)); ?>
        </div>
        <h3><?php echo htmlspecialchars($trader['name']); ?></h3>
        <div class="profile-role">
          <?php echo htmlspecialchars($trader['role']); ?> — <?php echo htmlspecialchars($trader['shop_name']); ?>
        </div>
      </div>

      <div class="field">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
              value="<?php echo htmlspecialchars($trader['email']); ?>" required>
      </div>

      <div class="field">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone"
              value="<?php echo htmlspecialchars($trader['phone']); ?>" required>
      </div>

      <div class="field">
        <label for="shop_name">Shop Name</label>
        <input type="text" id="shop_name" name="shop_name"
              value="<?php echo htmlspecialchars($trader['shop_name']); ?>" required>
      </div>

      <div class="field">
        <label for="shop_desc">Shop Description</label>
        <textarea id="shop_desc" name="shop_desc"></textarea>
      </div>

      <div class="field">
        <label for="shop_contact">Shop Contact Info</label>
        <input type="text" id="shop_contact" name="shop_contact">
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Share</button>
        <a class="btn btn-ghost" href="dashboard.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
