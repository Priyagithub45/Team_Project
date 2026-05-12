<?php
/**
 * register.php — Customer registration FORM.
 * Lives at: CFO/register.php (project root, same level as header.php / db.php)
 */

session_start();

if (isset($_GET['restart_otp'])) {
    unset($_SESSION['pending_registration'], $_SESSION['otp_errors'], $_SESSION['otp_success']);
}

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

        <p class="auth-form-legend"><span class="required-star" aria-hidden="true">*</span> Required field</p>

        <form action="register_process.php" method="POST" autocomplete="off" novalidate>

            <div class="auth-form-group<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="reg_name" class="auth-label">FULL NAME <span class="required-star" aria-hidden="true">*</span></label>
                <input type="text" id="reg_name" class="auth-input" name="name" required
                       autocomplete="name"
                       aria-required="true"
                       <?= isset($errors['name']) ? 'aria-invalid="true" aria-describedby="err_name"' : '' ?>
                       value="<?= old($old, 'name') ?>">
                <?php if (isset($errors['name'])): ?>
                    <span class="auth-field-error" id="err_name" role="alert"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="auth-form-group<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="reg_email" class="auth-label">EMAIL ADDRESS <span class="required-star" aria-hidden="true">*</span></label>
                <input type="email" id="reg_email" class="auth-input" name="email" required
                       autocomplete="email"
                       aria-required="true"
                       <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="err_email"' : '' ?>
                       value="<?= old($old, 'email') ?>">
                <?php if (isset($errors['email'])): ?>
                    <span class="auth-field-error" id="err_email" role="alert"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="auth-form-group">
                <label for="reg_phone" class="auth-label">PHONE NUMBER <span class="auth-optional">(optional)</span></label>
                <input type="tel" id="reg_phone" class="auth-input" name="phone"
                       autocomplete="tel" maxlength="20"
                       value="<?= old($old, 'phone') ?>">
            </div>

            <div class="auth-form-group<?= isset($errors['password']) ? ' has-error' : '' ?>">
                <label for="reg_password" class="auth-label">PASSWORD <span class="required-star" aria-hidden="true">*</span></label>
                <input type="password" id="reg_password" class="auth-input" name="password"
                       required minlength="8" autocomplete="new-password"
                       aria-required="true"
                       <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="err_password"' : '' ?>>
                <?php if (isset($errors['password'])): ?>
                    <span class="auth-field-error" id="err_password" role="alert"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="auth-field-error" style="color:#666;font-size:0.75rem;">Must be at least 8 characters.</span>
                <?php endif; ?>
            </div>

            <div class="auth-form-group<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
                <label for="reg_confirm" class="auth-label">CONFIRM PASSWORD <span class="required-star" aria-hidden="true">*</span></label>
                <input type="password" id="reg_confirm" class="auth-input" name="confirm_password"
                       required minlength="8" autocomplete="new-password"
                       aria-required="true"
                       <?= isset($errors['confirm_password']) ? 'aria-invalid="true" aria-describedby="err_confirm"' : '' ?>>
                <?php if (isset($errors['confirm_password'])): ?>
                    <span class="auth-field-error" id="err_confirm" role="alert"><?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <div class="auth-form-group<?= isset($errors['address']) ? ' has-error' : '' ?>">
                <label for="reg_address" class="auth-label">HOME ADDRESS <span class="required-star" aria-hidden="true">*</span></label>
                <textarea id="reg_address" class="auth-input" name="address"
                          required autocomplete="street-address" maxlength="200"
                          aria-required="true"
                          <?= isset($errors['address']) ? 'aria-invalid="true" aria-describedby="err_address"' : '' ?>><?= old($old, 'address') ?></textarea>
                <?php if (isset($errors['address'])): ?>
                    <span class="auth-field-error" id="err_address" role="alert"><?= htmlspecialchars($errors['address'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
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
