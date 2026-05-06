<?php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trader Login</title>
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

<div class="header">Trader Login</div>

<div class="main-wrap form-page">
    <div class="form-card" style="max-width:400px; margin:auto;">
    <h3>Login</h3>
    <form method="post" action="login_process.php">
        <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div style="margin-top:12px; text-align:center;">
        <button type="submit" class="btn btn-primary">Login</button>
        <a href="register.php" class="btn btn-ghost">Register</a>
        <a href="forgot_password.php" class="btn btn-ghost">Forgot Password?</a>
    </div>
    </form>
</div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
