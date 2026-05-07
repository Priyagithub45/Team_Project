<?php
// Start the session so we can check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_title) ? $page_title : 'Cleckhuddesfax Online Mart';
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags for character encoding and responsive design -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- External Google Fonts Integration: Poppins & Material Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Main Stylesheet linked dynamically with caching preventions -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Header Section -->
    <header class="site-header">
        <div class="container header-container">
            <!-- Logo -->
            <a href="index.php" class="logo">
                <img src="assets/images/logo.png" alt="Cleckhuddesfax Online Mart Logo" width="48" height="48">
                <div class="logo-text">
                    <span class="logo-main">CLECKHUDDESFAX</span>
                    <span class="logo-sub">ONLINE MART</span>
                </div>
            </a>

            <!-- Search Bar -->
            <div class="search-bar">
                <span class="material-icons search-icon">search</span>
                <input type="text" placeholder="Search..">
            </div>

            <!-- Navigation -->
            <nav class="main-nav">
                <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">HOME</a>
                <a href="category.php" class="<?php echo $current_page == 'category.php' ? 'active' : ''; ?>">CATEGORY</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- User is logged in: show their name and a Logout link -->
                    <span style="color:#f97316; font-weight:600;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php">LOGOUT</a>
                <?php else: ?>
                    <!-- User is NOT logged in: show Login and Register links -->
                    <a href="login.php" class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">LOGIN</a>
                    <a href="register.php" class="<?php echo $current_page == 'register.php' ? 'active' : ''; ?>">REGISTER</a>
                <?php endif; ?>
            </nav>

            <!-- Icons Container -->
            <div class="header-icons" style="display: flex; gap: 1.5rem; align-items: center;">
                <!-- Profile -->
                <a href="profile.php" class="profile-icon" style="text-decoration: none;">
                    <span class="material-icons" style="color: #FFFFFF;">person</span>
                </a>
                <!-- Cart -->
                <a href="cart.php" class="cart-icon" style="text-decoration: none;">
                    <span class="material-icons" style="color: #FFFFFF;">shopping_cart</span>
                </a>
            </div>
        </div>
    </header>

    <?php if ($flash_success || $flash_error): ?>
        <div class="container" style="margin-top:16px;">
            <?php if ($flash_success): ?>
                <div style="background:#efe; border:1px solid #0a0; padding:12px; border-radius:6px; color:#060;">
                    <?php echo htmlspecialchars($flash_success); ?>
                </div>
            <?php endif; ?>

            <?php if ($flash_error): ?>
                <div style="background:#fee; border:1px solid #c00; padding:12px; border-radius:6px; color:#900;">
                    <?php echo htmlspecialchars($flash_error); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
