<?php
/**
 * Category Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

<!-- Category Section -->
<section class="category-page">
    <div class="container">
        <div class="category-header-wrap">
            <div class="category-title-box">
                <h1>PRODUCT CATEGORIES</h1>
            </div>
        </div>

        <div class="category-grid">

            <!-- Card 1 -->
            <a href="butcher.php" class="category-card" style="display: flex; text-decoration: none; cursor: pointer;">
                <div class="category-img-area">
                    <img src="assets/images/butchers.png" alt="Butchers" style="max-height: 100%; object-fit: contain;">
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>BUTCHERS</h2>
                </div>
            </a>

            <!-- Card 2 -->
            <a href="greengrocers.php" class="category-card" style="display: flex; text-decoration: none; cursor: pointer;">
                <div class="category-img-area">
                    <img src="assets/images/greengrocers.png" alt="Greengrocers"
                        style="max-height: 100%; object-fit: contain;">
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>GREENGROCERS</h2>
                </div>
            </a>

            <!-- Card 3 -->
            <a href="delicatessen.php" class="category-card" style="display: flex; text-decoration: none; cursor: pointer;">
                <div class="category-img-area">
                    <img src="assets/images/delicatessen.png" alt="Delicatessen"
                        style="max-height: 100%; object-fit: contain;">
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>DELICATESSEN</h2>
                </div>
            </a>

            <!-- Card 4 -->
            <a href="fishmongers.php" class="category-card" style="display: flex; text-decoration: none; cursor: pointer;">
                <div class="category-img-area">
                    <img src="assets/images/fishmongers.png" alt="Fishmonger"
                        style="max-height: 100%; object-fit: contain;">
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>FISHMONGER</h2>
                </div>
            </a>

            <!-- Card 5 -->
            <a href="bakery.php" class="category-card" style="display: flex; text-decoration: none; cursor: pointer;">
                <div class="category-img-area">
                    <img src="assets/images/bakery.png" alt="Bakery" style="max-height: 100%; object-fit: contain;">
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>BAKERY</h2>
                </div>
            </a>

            <!-- Card 6: New Trader -->
            <div class="category-card new-trader">
                <div class="category-img-area">
                    <div class="new-trader-icon"></div>
                </div>
                <div class="category-divider"></div>
                <div class="category-bottom-area">
                    <h2>NEW TRADER</h2>
                </div>
            </div>

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>