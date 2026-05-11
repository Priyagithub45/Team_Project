<?php
require_once '../db.php';

if (isset($_SESSION['role']) && $_SESSION['role'] === 'trader') {
    header('Location: dashboard.php');
    exit;
}

$errors = $_SESSION['trader_application_errors'] ?? [];
$old = $_SESSION['trader_application_old'] ?? [];
$success = $_SESSION['trader_application_success'] ?? '';
unset($_SESSION['trader_application_errors'], $_SESSION['trader_application_old'], $_SESSION['trader_application_success']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function old_value(array $old, string $key): string
{
    return h((string)($old[$key] ?? ''));
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply to Trade &mdash; Cleckhuddesfax Online Mart</title>
    <link rel="stylesheet" href="trader.css">
</head>
<body class="trader-apply-page">
<header class="apply-header">
    <div class="apply-container apply-header-inner">
        <a class="apply-brand" href="../customer/index.php">
            <img src="logo1.png" alt="CLECKHUDDESFAX Online Mart">
            <span>
                <strong>CLECKHUDDESFAX</strong>
                <small>ONLINE MART</small>
            </span>
        </a>
        <nav class="apply-nav" aria-label="Trader application navigation">
            <a href="../customer/index.php">Customer Site</a>
            <a href="login.php" class="apply-nav-cta">Trader Login</a>
        </nav>
    </div>
</header>

<main>
    <section class="apply-hero">
        <div class="apply-container apply-hero-grid">
            <div class="apply-hero-copy">
                <span class="apply-eyebrow">Trader Applications</span>
                <h1>Apply to trade with Cleckhuddesfax Online Mart</h1>
                <p>
                    Join the market departments customers already browse every day.
                    The admin team reviews every application before trader login access is enabled.
                </p>
            </div>
            <div class="apply-hero-media" aria-hidden="true">
                <img src="../customer/assets/images/hero.png" alt="">
            </div>
        </div>
    </section>

    <section class="apply-section">
        <div class="apply-container">
            <div class="application-card">
                <div class="application-card-heading">
                    <span class="apply-eyebrow">New Trader</span>
                    <h2>Tell us about your shop</h2>
                    <p>
                        Your application will be saved as pending until an administrator approves it.
                    </p>
                </div>

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

                <form method="post" action="register_process.php" autocomplete="off" class="application-form">
                    <div class="form-row">
                        <div class="field">
                            <label for="owner_name">Owner / Full Name</label>
                            <input type="text" id="owner_name" name="owner_name" maxlength="100" required value="<?= old_value($old, 'owner_name') ?>">
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="100" required value="<?= old_value($old, 'email') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" maxlength="20" value="<?= old_value($old, 'phone') ?>">
                        </div>
                        <div class="field">
                            <label for="license_no">Trading License Number</label>
                            <input type="text" id="license_no" name="license_no" maxlength="50" required value="<?= old_value($old, 'license_no') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="shop_name">Proposed Shop Name</label>
                            <input type="text" id="shop_name" name="shop_name" maxlength="100" required value="<?= old_value($old, 'shop_name') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="address">Business Address</label>
                            <textarea id="address" name="address" maxlength="200" required><?= old_value($old, 'address') ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="business_description">Business Description</label>
                            <textarea id="business_description" name="business_description" maxlength="500" required><?= old_value($old, 'business_description') ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="notes">Message for the Admin Team</label>
                            <textarea id="notes" name="notes" maxlength="500"><?= old_value($old, 'notes') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Submit Application</button>
                        <a href="login.php" class="btn btn-ghost">Back to Login</a>
                    </div>
                </form>
            </div>
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
