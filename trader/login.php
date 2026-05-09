<?php
require_once '../db.php';

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'trader') {
    header('Location: dashboard.php');
    exit;
}

$errors = $_SESSION['trader_login_errors'] ?? [];
$old = $_SESSION['trader_login_old'] ?? ['email' => ''];
$success = $_SESSION['trader_login_success'] ?? '';
unset($_SESSION['trader_login_errors'], $_SESSION['trader_login_old'], $_SESSION['trader_login_success']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trader Login &mdash; Cleckhuddesfax Online Mart</title>
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
        <nav class="apply-nav" aria-label="Trader login navigation">
            <a href="../customer/index.php">Customer Site</a>
            <a href="register.php" class="apply-nav-cta">Apply</a>
        </nav>
    </div>
</header>

<main class="auth-login-shell">
    <section class="auth-login-panel">
        <div class="auth-login-copy">
            <span class="apply-eyebrow">Trader Portal</span>
            <h1>Sign in to your shop workspace</h1>
            <p>Manage products, stock, collection orders, and reports after your trader account has been approved by admin.</p>
        </div>

        <div class="auth-login-card">
            <h2>Trader Login</h2>

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

            <form method="post" action="login_process.php" autocomplete="off">
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= h((string)($old['email'] ?? '')) ?>">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <a href="register.php" class="btn btn-ghost">Apply</a>
                </div>

                <a class="auth-muted-link" href="forgot_password.php">Forgot password?</a>
            </form>
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
