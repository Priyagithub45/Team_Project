<?php
/**
 * login.php — Customer login form
 * Lives at: CFO/customer/login.php
 */

$page_title = 'Login';
include '../db.php';

// If already logged in, send to home
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

include 'header.php';

$errors  = $_SESSION['login_errors']  ?? [];
$old     = $_SESSION['login_old']     ?? ['email' => ''];
$success = $_SESSION['register_success'] ?? '';   // welcome msg from register

unset($_SESSION['login_errors'], $_SESSION['login_old'], $_SESSION['register_success']);
?>

<section class="form-page" style="padding:40px 0;">
    <div class="container container-narrow" style="max-width:420px; margin:auto;">
        <h2 style="text-align:center; margin-bottom:24px;">LOGIN</h2>

        <?php if ($success): ?>
            <div style="background:#efe; border:1px solid #0a0; padding:12px; margin-bottom:16px; border-radius:6px; color:#060;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div style="background:#fee; border:1px solid #c00; padding:12px; margin-bottom:16px; border-radius:6px;">
                <ul style="margin:0; padding-left:20px;">
                    <?php foreach ($errors as $e): ?>
                        <li style="color:#900;"><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="login_process.php" style="display:flex; flex-direction:column; gap:14px;">
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo htmlspecialchars($old['email']); ?>"
                       style="width:100%; padding:8px;">
            </div>
            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       style="width:100%; padding:8px;">
            </div>

            <div style="margin-top:8px; display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1; padding:10px;">LOGIN</button>
                <a href="register.php" class="btn btn-outline" style="flex:1; padding:10px; text-align:center; text-decoration:none;">Register</a>
            </div>
        </form>
    </div>
</section>

<?php include 'footer.php'; ?>
