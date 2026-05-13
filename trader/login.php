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
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
                <a href="register.php" class="apply-nav-cta">Apply to Trade</a>
            </nav>
        </div>
    </header>

    <div class="auth-split">

        <div class="auth-panel-left" aria-hidden="true">
            <div class="auth-panel-left-inner">
                <span class="apply-eyebrow">Trader Portal</span>
                <h1>Your Shop.<br><em>Your Rules.</em></h1>
                <p>Manage products, monitor stock levels, and track your sales - all from one workspace once your
                    account is approved by admin.</p>
                <ul class="auth-features">
                    <li>List &amp; manage your products</li>
                    <li>Monitor stock &amp; collection orders</li>
                    <li>Access daily and weekly reports</li>
                </ul>
            </div>
        </div>

        <div class="auth-panel-right">
            <div class="auth-form-inner">
                <span class="apply-eyebrow">Welcome back</span>
                <h2>Sign In</h2>
                <p class="auth-form-subtitle">Enter your credentials to access your workspace.</p>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h((string) $error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="login_process.php" autocomplete="off" novalidate>
                    <div class="field">
                        <label for="email">
                            Email Address <span class="required-star" aria-hidden="true">*</span>
                        </label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                            value="<?= h((string) ($old['email'] ?? '')) ?>" placeholder="you@example.com">
                    </div>

                    <div class="field">
                        <label for="password">
                            Password <span class="required-star" aria-hidden="true">*</span>
                            <a href="forgot_password.php" class="auth-muted-link"
                                style="margin:0;display:inline;font-size:0.65rem;letter-spacing:1px;text-transform:uppercase;">Forgot?</a>
                        </label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            placeholder="••••••••">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Sign In</button>
                        <a href="register.php" class="btn btn-ghost">Apply to Trade</a>
                    </div>
                </form>

                <div class="auth-signup-prompt">
                    New trader? <a href="register.php">Submit an application</a>
                </div>
            </div>
        </div>

    </div>

    <footer class="apply-footer">
        <div class="apply-container">
            <span>Cleckhuddesfax Online Mart</span>
            <span>&copy; 2026</span>
        </div>
    </footer>

    <script src="trader.js"></script>
</body>

</html>