<?php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Product</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="logo">
    <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
  </div>

  <a href="dashboard.php">Dashboard</a>
  <a href="add_product.php" class="active">Add Product</a>
  <a href="profile.php">Profile</a>
  <a href="#">Logout</a>
</div>

<div class="header">Add Product</div>

<div class="main-wrap form-page">
  <div class="form-box">
    <div class="form-card">
      <form method="post" enctype="multipart/form-data">

        <div class="form-row">
          <div class="field">
            <label>Product ID</label>
            <input type="text" name="product_id" required>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Product Name</label>
            <input type="text" name="name" required>
          </div>
          <div class="field">
            <label>Trader Type</label>
            <select name="trader_type" required>
              <option value="">Select type</option>
              <option value="Butchers">Butchers</option>
              <option value="Greengrocer">Greengrocer</option>
              <option value="Fishmonger">Fishmonger</option>
              <option value="Bakery">Bakery</option>
              <option value="Delicatessen">Delicatessen</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Price (£)</label>
            <input type="number" step="0.01" name="price" required>
          </div>
          <div class="field">
            <label>Stock</label>
            <input type="number" name="stock" required>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Allergy Information</label>
            <textarea name="allergy" class="field"></textarea>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Minimum Order Quantity</label>
            <input type="number" name="min_order" class="field">
          </div>
          <div class="field">
            <label>Maximum Order Quantity</label>
            <input type="number" name="max_order" class="field">
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Status</label>
            <select name="status">
              <option value="active">Active</option>
              <option value="low-stock">Low Stock</option>
              <option value="out-stock">Out Of Stock</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="field">
            <label>Upload Product Image</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          </div>
        </div>

        <div style="margin-top:12px; text-align:center;">
          <button type="submit" class="btn btn-primary">Save</button>
          <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
