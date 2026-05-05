<?php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trader Registration</title>
    <link rel="stylesheet" href="trader.css">
</head>
<body>
<div class="sidebar">
    <div class="logo">
    <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
</div>
<a href="dashboard.php">Dashboard</a>
<a href="add_product.php">Add Product</a>
<a href="profile.php">Profile</a>
</div>

<div class="header">Trader Registration</div>

<div class="main-wrap form-page">
    <div class="form-card" style="max-width:500px; margin:auto;">
    <h3>Register</h3>
    <form method="post" action="register_process.php">
        <div class="field">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div class="field">
        <label for="shop_name">Shop Name</label>
        <input type="text" id="shop_name" name="shop_name" required>
    </div>
    <div style="margin-top:12px; text-align:center;">
        <button type="submit" class="btn btn-primary">Register</button>
        <a href="login.php" class="btn btn-ghost">Back to Login</a>
    </div>
    </form>
</div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
