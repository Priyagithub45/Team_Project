<?php
// Start the session so we can check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/wishlist_helpers.php';

$current_page = basename($_SERVER['PHP_SELF']);
$page_title ??= 'Cleckhuddesfax Online Mart';
$is_customer_logged_in = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$customer_name = $is_customer_logged_in ? (string)($_SESSION['user_name'] ?? 'Customer') : '';
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$cart_count = 0;
$wishlist_count = 0;
if ($is_customer_logged_in && isset($conn)) {
    $uid = (string)(int)$_SESSION['user_id'];
    $cc_stmt = oci_parse($conn,
        "SELECT NVL(SUM(ci.QUANTITY), 0) AS CNT
         FROM CART_ITEM ci
         JOIN CART c ON ci.CART_ID = c.CART_ID
         WHERE c.CUSTOMER_ID = :p_uid AND c.STATUS = 'Active'"
    );
    if ($cc_stmt) {
        oci_bind_by_name($cc_stmt, ':p_uid', $uid);
        if (oci_execute($cc_stmt)) {
            $cc_row = oci_fetch_assoc($cc_stmt);
            $cart_count = (int)($cc_row['CNT'] ?? 0);
        }
        oci_free_statement($cc_stmt);
    }
    $wishlist_count = cfo_wishlist_count($conn, (int)$uid);
} elseif (!$is_customer_logged_in && !empty($_SESSION['guest_cart'])) {
    $cart_count = (int)array_sum($_SESSION['guest_cart']);
}
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
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css?v=2.6">
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
            <form class="search-bar" action="search.php" method="get" role="search">
                <button type="submit" class="search-submit" aria-label="Search products">
                    <span class="material-icons search-icon">search</span>
                </button>
                <input
                    type="search"
                    name="q"
                    placeholder="Search products..."
                    value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    autocomplete="off"
                >
            </form>

            <!-- Navigation -->
            <nav class="main-nav" id="main-nav">
                <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">HOME</a>
                <a href="category.php" class="<?php echo $current_page == 'category.php' ? 'active' : ''; ?>">CATEGORY</a>
                <?php if ($is_customer_logged_in): ?>
                    <!-- User is logged in: show their name and a Logout link -->
                    <span class="customer-nav-name"><?php echo htmlspecialchars($customer_name); ?></span>
                    <a href="logout.php">LOGOUT</a>
                <?php else: ?>
                    <!-- User is NOT logged in: show Login and Register links -->
                    <a href="login.php" class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">LOGIN</a>
                    <a href="register.php" class="<?php echo $current_page == 'register.php' ? 'active' : ''; ?>">REGISTER</a>
                <?php endif; ?>
                <div class="trader-header-menu">
                    <button type="button" class="trader-header-button" aria-haspopup="true" aria-expanded="false">
                        <span class="material-icons">storefront</span>
                        TRADER
                        <span class="material-icons trader-header-chevron">expand_more</span>
                    </button>
                    <div class="trader-header-dropdown" role="menu">
                        <a href="../trader/register.php" role="menuitem">
                            <span class="material-icons">add_business</span>
                            <span>
                                <strong>Become a Trader</strong>
                                <small>Apply for a shop account</small>
                            </span>
                        </a>
                        <a href="../trader/login.php" role="menuitem">
                            <span class="material-icons">login</span>
                            <span>
                                <strong>Trader Login</strong>
                                <small>Manage products and orders</small>
                            </span>
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Icons Container -->
            <div class="header-icons">
                <!-- Wishlist -->
                <a href="wishlist.php" class="wishlist-icon" aria-label="My wishlist">
                    <span class="material-icons">favorite</span>
                    <span class="cart-badge wishlist-badge<?php echo $wishlist_count === 0 ? ' cart-badge-hidden' : ''; ?>" id="wishlist-badge">
                        <?php echo $wishlist_count > 99 ? '99+' : max(0, $wishlist_count); ?>
                    </span>
                </a>
                <!-- Profile -->
                <a href="profile.php" class="profile-icon" aria-label="My profile">
                    <span class="material-icons">person</span>
                </a>
                <!-- Cart -->
                <a href="cart.php" class="cart-icon" aria-label="Shopping cart">
                    <span class="material-icons">shopping_cart</span>
                    <span class="cart-badge<?php echo $cart_count === 0 ? ' cart-badge-hidden' : ''; ?>" id="cart-badge">
                        <?php echo $cart_count > 99 ? '99+' : max(0, $cart_count); ?>
                    </span>
                </a>
                <!-- Mobile nav toggle -->
                <button class="nav-toggle" id="nav-toggle" aria-label="Open navigation" aria-expanded="false" aria-controls="main-nav">
                    <span class="material-icons nav-toggle-icon">menu</span>
                </button>
            </div>
        </div>
    </header>

    <?php if ($flash_success): ?>
        <div class="cfo-flash cfo-flash-success" style="display:none"><?php echo htmlspecialchars($flash_success); ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="cfo-flash cfo-flash-error" style="display:none"><?php echo htmlspecialchars($flash_error); ?></div>
    <?php endif; ?>
