<?php
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password</title>
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
<a href="login.php">Login</a>
</div>

<div class="header">Reset Password</div>

<div class="main-wrap form-page">
    <div class="form-card" style="max-width:400px; margin:auto;">
    <h3>Reset Password</h3>
    <form method="post" action="reset_process.php">
        <div class="field">
        <label for="email">Enter your email</label>
        <input type="email" id="email" name="email" required>
    </div>
    <div style="margin-top:12px; text-align:center;">
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
        <a href="login.php" class="btn btn-ghost">Back to Login</a>
    </div>
    </form>
</div>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
