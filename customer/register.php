<?php
/**
 * register.php — Customer registration FORM.
 * Lives at: CFO/register.php (project root, same level as header.php / db.php)
 */

session_start();

// already logged in -> profile
if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit;
}

// pull session messages set by register_process.php
$errors  = $_SESSION['register_errors']  ?? [];
$old     = $_SESSION['register_old']     ?? [];
$success = $_SESSION['register_success'] ?? '';

// clear so they only show once
unset($_SESSION['register_errors'], $_SESSION['register_old'], $_SESSION['register_success']);

function old(array $old, string $key): string {
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

include 'header.php';
?>

<style>
.auth-page {
    max-width: 480px;
    margin: 3rem auto;
    padding: 0 1rem;
    font-family: 'Poppins', sans-serif;
}
.auth-card {
    background: #fff;
    padding: 2.5rem 2rem;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.auth-card h1 {
    color: #003366;
    margin: 0 0 0.5rem;
    font-size: 1.8rem;
    text-align: center;
    letter-spacing: 1px;
}
.auth-subtitle {
    color: #666;
    text-align: center;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
}
.auth-error,
.auth-success {
    padding: 0.8rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}
.auth-error {
    background: #fdecea;
    color: #b71c1c;
    border-left: 4px solid #b71c1c;
}
.auth-error ul { margin: 0; padding-left: 1.2em; }
.auth-success {
    background: #e8f5e9;
    color: #1b5e20;
    border-left: 4px solid #1b5e20;
}
.auth-form-group { margin-bottom: 1rem; }
.auth-label {
    font-size: 0.75rem;
    color: #003366;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-bottom: 0.3rem;
}
.auth-input {
    width: 100%;
    padding: 0.7rem 0.9rem;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 0.95rem;
    font-family: inherit;
    box-sizing: border-box;
}
.auth-input:focus {
    outline: none;
    border-color: #ff7a00;
    box-shadow: 0 0 0 3px rgba(255,122,0,0.15);
}
textarea.auth-input { min-height: 70px; resize: vertical; }
.btn-auth {
    width: 100%;
    padding: 0.85rem;
    background: #ff7a00;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    margin-top: 0.5rem;
}
.btn-auth:hover { background: #e66e00; }
.auth-footer {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.9rem;
}
.auth-footer-text { color: #666; margin-right: 0.4rem; }
.auth-footer-link {
    color: #ff7a00;
    font-weight: 600;
    text-decoration: none;
}
.auth-footer-link:hover { text-decoration: underline; }
</style>

<section class="auth-page">
    <div class="auth-card">
        <h1>REGISTRATION</h1>
        <div class="auth-subtitle">Create your account to get started</div>

        <?php if (!empty($success)): ?>
            <div class="auth-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="auth-error">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register_process.php" method="POST" autocomplete="off">

            <div class="auth-form-group">
                <div class="auth-label">FULL NAME</div>
                <input type="text" class="auth-input" name="name" required
                       value="<?= old($old, 'name') ?>">
            </div>

            <div class="auth-form-group">
                <div class="auth-label">EMAIL ADDRESS</div>
                <input type="email" class="auth-input" name="email" required
                       value="<?= old($old, 'email') ?>">
            </div>

            <div class="auth-form-group">
                <div class="auth-label">PHONE NUMBER</div>
                <input type="text" class="auth-input" name="phone"
                       placeholder="optional"
                       value="<?= old($old, 'phone') ?>">
            </div>

            <div class="auth-form-group">
                <div class="auth-label">PASSWORD</div>
                <input type="password" class="auth-input" name="password" required minlength="8">
            </div>

            <div class="auth-form-group">
                <div class="auth-label">CONFIRM PASSWORD</div>
                <input type="password" class="auth-input" name="confirm_password" required minlength="8">
            </div>

            <div class="auth-form-group">
                <div class="auth-label">HOME ADDRESS</div>
                <textarea class="auth-input" name="address" required><?= old($old, 'address') ?></textarea>
            </div>

            <button type="submit" class="btn-auth">REGISTER</button>
        </form>
    </div>

    <div class="auth-footer">
        <span class="auth-footer-text">ALREADY HAVE AN ACCOUNT?</span>
        <a href="login.php" class="auth-footer-link">LOGIN</a>
    </div>
</section>

<?php include 'footer.php'; ?>
