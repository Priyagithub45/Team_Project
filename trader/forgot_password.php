<?php
require_once '../db.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'trader') {
    header('Location: dashboard.php');
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$errors  = $_SESSION['trader_forgot_errors']  ?? [];
$success = $_SESSION['trader_forgot_success'] ?? '';
unset($_SESSION['trader_forgot_errors'], $_SESSION['trader_forgot_success']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password &mdash; Cleckhuddesfax Online Mart</title>
    <link rel="stylesheet" href="trader.css">
</head>
<body class="trader-auth-page">
<header class="apply-header">
    <div class="apply-container apply-header-inner">
        <a class="apply-brand" href="../customer/index.php">
            <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
            <span>
                <strong>CLECKHUDDESFAX</strong>
                <small>ONLINE MART</small>
            </span>
        </a>
        <nav class="apply-nav" aria-label="Trader navigation">
            <a href="../customer/index.php">Customer Site</a>
            <a href="login.php" class="apply-nav-cta">Trader Login</a>
        </nav>
    </div>
</header>

<main class="auth-login-shell">
    <section class="auth-login-panel">
        <div class="auth-login-copy">
            <span class="apply-eyebrow">Account Recovery</span>
            <h1>Reset your password</h1>
            <p>Enter the email address linked to your approved trader account. A reset link will be sent if the account exists.</p>
        </div>

        <div class="auth-login-card">
            <h2>Forgot Password</h2>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
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

            <form method="post" action="reset_process.php" autocomplete="off">
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required
                           placeholder="your@email.com">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                    <a href="login.php" class="btn btn-ghost">Back to Login</a>
                </div>
            </form>

            <a class="auth-muted-link" href="register.php">Not yet a trader? Apply here</a>
        </div>
    </section>
</main>

<footer class="apply-footer">
    <div class="apply-container">
        <span>CLECKHUDDESFAX ONLINE MART</span>
        <span>&copy; 2026</span>
    </div>
</footer>

<script src="trader.js"></script>
</body>
</html>
