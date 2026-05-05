<?php
$page_title = 'Bakery - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="product-list-page">
    <div class="container">
        <div class="product-list-header">
            <h1>BAKERY</h1>
            <p>Artisan breads and pastries, baked right here in Cleckhuddesfax using locally sourced ingredients and traditional methods.</p>
        </div>

        <div class="product-list-grid">
            <!-- Product 1 -->
            <div class="product-list-card">
                <div class="product-list-img-box">
                    <img src="assets/images/White Bread.png" alt="White Bread" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="product-list-info">
                    <span class="product-list-name">White Bread</span>
                    <span class="product-list-price">$10.00</span>
                </div>
                <a href="bakery_product-1.php" class="btn-view-product">
                    <span class="material-icons">visibility</span> VIEW PRODUCT
                </a>
            </div>

            <!-- Product 2 -->
            <div class="product-list-card">
                <div class="product-list-img-box">
                    <img src="assets/images/Croissants.png" alt="Croissants" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="product-list-info">
                    <span class="product-list-name">Croissants</span>
                    <span class="product-list-price">$15.00</span>
                </div>
                <a href="bakery_product-2.php" class="btn-view-product">
                    <span class="material-icons">visibility</span> VIEW PRODUCT
                </a>
            </div>
            
            <!-- Product 3 -->
            <div class="product-list-card">
                <div class="product-list-img-box">
                    <img src="assets/images/Cookies.png" alt="Cookies" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="product-list-info">
                    <span class="product-list-name">Cookies</span>
                    <span class="product-list-price">$4.00</span>
                </div>
                <a href="bakery_product-3.php" class="btn-view-product">
                    <span class="material-icons">visibility</span> VIEW PRODUCT
                </a>
            </div>

            <!-- Product 4 -->
            <div class="product-list-card">
                <div class="product-list-img-box">
                    <img src="assets/images/Cupcakes.png" alt="Cupcakes" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <div class="product-list-info">
                    <span class="product-list-name">Cupcakes</span>
                    <span class="product-list-price">$8.00</span>
                </div>
                <a href="bakery_product-4.php" class="btn-view-product">
                    <span class="material-icons">visibility</span> VIEW PRODUCT
                </a>
            </div>

        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
