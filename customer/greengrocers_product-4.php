<?php
/**
 * Product Page - Cleckhuddesfax Online Mart
 */
include 'header.php';
?>

<!-- Product Section -->
<section class="product-page">
    <div class="container product-container">
        <!-- Left Column: Image -->
        <div class="product-image-col">
            <div class="main-image">
                <img src="assets/images/Fruits Basket.png" alt="Fruits Basket">
            </div>
        </div>

        <!-- Right Column: Details -->
        <div class="product-info-col">
            <h1 class="product-title">Fruits Basket</h1>

            <div class="product-price-row">
                <span class="product-price">$35.00</span>
            </div>

            <div class="product-description-box">
                <h5 class="description-title">DESCRIPTION</h5>
                <div class="product-description">
                    <!-- Description will be added here -->
                </div>
            </div>

            <div class="product-quantity-row">
                <label>QUANTITY</label>
                <div class="quantity-wrapper">
                    <div class="quantity-selector">
                        <button type="button" class="qty-btn minus">-</button>
                        <input type="number" value="1" min="1" class="qty-input">
                        <button type="button" class="qty-btn plus">+</button>
                    </div>
                    <div class="stock-status">
                        <span class="status-dot"></span> In Stock
                    </div>
                </div>
            </div>

            <div class="product-actions">
                <button type="button" class="btn btn-primary btn-full" onclick="alert('Product added to cart!')"><span
                        class="material-icons" style="font-size: 1.2rem;">shopping_cart</span> ADD TO CART</button>
                <button type="button" class="btn btn-outline btn-full" onclick="window.location.href='cart.php'"><span
                        class="material-icons" style="font-size: 1.2rem;">bolt</span> BUY IT NOW</button>
            </div>


            <div class="product-meta">
                <div class="meta-row">
                    <span class="meta-label">SKU</span>
                    <span class="meta-value">GRN-004</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">CATEGORY</span>
                    <span class="meta-value text-orange">Greengrocers</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Customer Reviews Section -->
<section class="reviews-section">
    <div class="container reviews-container">
        <!-- Left Column: Review Form -->
        <div class="reviews-form-col">
            <h2 class="reviews-main-title">Customer Reviews</h2>
            <div class="review-form-card">
                <h3>Share your thoughts</h3>
                
                <div class="rating-input">
                    <span class="rating-label">YOUR RATING</span>
                    <div class="stars-outline">
                        <span class="material-icons">star_border</span>
                        <span class="material-icons">star_border</span>
                        <span class="material-icons">star_border</span>
                        <span class="material-icons">star_border</span>
                        <span class="material-icons">star_border</span>
                    </div>
                </div>

                <div class="review-input">
                    <span class="review-label">REVIEW</span>
                    <textarea placeholder="Write a review."></textarea>
                </div>

                <button type="button" class="btn btn-full submit-review-btn">SUBMIT REVIEW</button>
            </div>
        </div>

        <!-- Right Column: Review List -->
        <div class="reviews-list-col">
            <div class="reviews-list-header">
                <h4>Customer Reviews</h4>
            </div>
            
            <div class="reviews-list">
                <!-- Review Item 1 -->
                <div class="review-item">
                    <div class="stars-filled">
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                    </div>
                    <h5 class="reviewer-name">Ram Badhur</h5>
                    <p class="review-text">The tenderness is beyond belief.</p>
                </div>

                <!-- Review Item 2 -->
                <div class="review-item">
                    <div class="stars-filled">
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star_border</span>
                    </div>
                    <h5 class="reviewer-name">Ramesh Nepal</h5>
                    <p class="review-text">Best steak I ever had.</p>
                </div>

                <!-- Review Item 3 -->
                <div class="review-item">
                    <div class="stars-filled">
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                        <span class="material-icons">star</span>
                    </div>
                    <h5 class="reviewer-name">MD Khan</h5>
                    <p class="review-text">I loved the meat.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
