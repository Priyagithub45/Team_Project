<?php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Trader Dashboard</title>
  <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
  <div class="logo">
    <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
  </div>
  <a href="dashboard.php" class="active">Dashboard</a>
  <a href="add_product.php">Add Product</a>
  <a href="profile.php">Profile</a>
  <a href="logout.php">Logout</a>
</div>

<div class="header">Trader Dashboard</div>

<div class="main-wrap dashboard-page">
  <div class="summary-boxes">
    <div class="box"><h3>Total Products</h3><p>5 Products</p></div>
    <div class="box"><h3>Total Sales</h3><p>£1420.00</p></div>
    <div class="box"><h3>Recent Orders</h3><p>12 Orders</p></div>
    <div class="box"><h3>Pending Requests</h3><p>3 Pending</p></div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No</th><th>Trade Type</th><th>Product Name</th>
          <th>Price</th><th>Stock</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>Butchers</td>
          <td>Prime Beef Mince (500g)</td>
          <td>£5.50</td>
          <td>50</td>
          <td><span class="status-box status-active">Active</span></td>
          <td>
            <a href="edit_product.php?id=1" class="action-btn btn-edit">Edit</a>
            <a href="delete_product.php?id=1" class="action-btn btn-delete">Delete</a>
          </td>
        </tr>
        <tr>
          <td>2</td>
          <td>Greengrocer</td>
          <td>Organic Carrots (1kg)</td>
          <td>£1.20</td>
          <td>10</td>
          <td><span class="status-box status-low">Low Stock</span></td>
          <td>
            <a href="edit_product.php?id=2" class="action-btn btn-edit">Edit</a>
            <a href="delete_product.php?id=2" class="action-btn btn-delete">Delete</a>
          </td>
        </tr>
        <tr>
          <td>3</td>
          <td>Fishmonger</td>
          <td>Fresh Salmon Fillets (2pk)</td>
          <td>£8.95</td>
          <td>0</td>
          <td><span class="status-box status-out">Out Of Stock</span></td>
          <td>
            <a href="edit_product.php?id=3" class="action-btn btn-edit">Edit</a>
            <a href="delete_product.php?id=3" class="action-btn btn-delete">Delete</a>
          </td>
        </tr>
        <tr>
          <td>4</td>
          <td>Bakery</td>
          <td>Sourdough Loaf (Large)</td>
          <td>£3.80</td>
          <td>45</td>
          <td><span class="status-box status-active">Active</span></td>
          <td>
            <a href="edit_product.php?id=4" class="action-btn btn-edit">Edit</a>
            <a href="delete_product.php?id=4" class="action-btn btn-delete">Delete</a>
          </td>
        </tr>
        <tr>
          <td>5</td>
          <td>Delicatessen</td>
          <td>Stuffed Garlic Olives (200g)</td>
          <td>£4.50</td>
          <td>15</td>
          <td><span class="status-box status-low">Low Stock</span></td>
          <td>
            <a href="edit_product.php?id=5" class="action-btn btn-edit">Edit</a>
            <a href="delete_product.php?id=5" class="action-btn btn-delete">Delete</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="report-section">
    <h3>Orders by Collection Slot</h3>
    <table>
      <tr><th>Slot</th><th>Total Orders</th></tr>
      <tr><td>10–13</td><td>…</td></tr>
      <tr><td>13–16</td><td>…</td></tr>
      <tr><td>16–19</td><td>…</td></tr>
    </table>
  </div>

  <div class="report-section">
    <h3>Weekly Finance Report</h3>
    <p>Total Payments Due (last 7 days delivered orders): £…</p>
  </div>

  <div class="report-section">
    <h3>Monthly Sales Report</h3>
    <label for="sort">Sort by:</label>
    <select id="sort">
      <option value="alphabetical">Alphabetical</option>
      <option value="orders">Total Orders</option>
      <option value="income">Total Income</option>
    </select>
    <table>
      <tr><th>Product</th><th>Orders</th><th>Income</th></tr>
      <tr><td>Sourdough Loaf</td><td>45</td><td>£171.00</td></tr>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
