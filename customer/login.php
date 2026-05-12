<?php
/**
 * login.php — Customer login form
 * Lives at: CFO/customer/login.php
 */

$page_title = 'Login';
include '../db.php';

// If already logged in as customer, send to home. Non-customer: clear and show login.
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'customer') {
        header('Location: index.php');
        exit;
    }
    session_unset();
    session_destroy();
    session_start();
}

include 'header.php';

$errors  = $_SESSION['login_errors']  ?? [];
$old     = $_SESSION['login_old']     ?? ['email' => ''];
$success = $_SESSION['register_success'] ?? '';   // welcome msg from register

unset($_SESSION['login_errors'], $_SESSION['login_old'], $_SESSION['register_success']);
?>

<style>
.login-page { max-width: 420px; margin: 3rem auto; padding: 0 1rem; font-family: 'Poppins', sans-serif; }
.login-card { background: #fff; padding: 2.5rem 2rem; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.login-card h2 { color: #003366; margin: 0 0 1.5rem; font-size: 1.8rem; text-align: center; letter-spacing: 1px; }
.login-error { padding: 0.8rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; background: #fdecea; color: #b71c1c; border-left: 4px solid #b71c1c; }
.login-error ul { margin: 0; padding-left: 1.2em; }
.login-success { padding: 0.8rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; background: #e8f5e9; color: #1b5e20; border-left: 4px solid #1b5e20; }
.login-group { margin-bottom: 1rem; display: flex; flex-direction: column; gap: 4px; }
.login-label { font-size: 0.75rem; color: #003366; font-weight: 600; letter-spacing: 0.5px; }
.login-input { width: 100%; padding: 0.7rem 0.9rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; font-family: inherit; box-sizing: border-box; }
.login-input:focus { outline: none; border-color: #ff7a00; box-shadow: 0 0 0 3px rgba(255,122,0,0.15); }
.login-actions { display: flex; gap: 10px; margin-top: 0.5rem; }
.login-actions .btn { flex: 1; padding: 0.75rem; text-align: center; }
</style>

<section class="login-page">
    <div class="login-card">
        <h2>LOGIN</h2>

        <?php if ($success): ?>
            <div class="login-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="login-error" role="alert">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="login_process.php" novalidate>
            <div class="login-group">
                <label for="email" class="login-label">EMAIL ADDRESS <span class="required-star" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" class="login-input"
                       required autocomplete="email"
                       value="<?php echo htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="login-group">
                <label for="password" class="login-label">PASSWORD <span class="required-star" aria-hidden="true">*</span></label>
                <input type="password" id="password" name="password" class="login-input"
                       required autocomplete="current-password">
            </div>

            <div class="login-actions">
                <button type="submit" class="btn btn-primary">LOGIN</button>
                <a href="register.php" class="btn btn-outline">Register</a>
            </div>
        </form>
    </div>
</section>

<?php include 'footer.php'; ?>
