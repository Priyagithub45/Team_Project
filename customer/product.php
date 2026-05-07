<?php
include '../db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

function not_found_page(string $msg = 'Product not found.'): void {
    global $page_title;
    $page_title = 'Not Found - Cleckhuddesfax Online Mart';
    include 'header.php';
    echo '<section class="product-page"><div class="container" style="padding:4rem 1rem;text-align:center;">'
       . '<h2>' . htmlspecialchars($msg) . '</h2>'
       . '<a href="category.php" class="btn btn-primary" style="margin-top:1rem;">Back to Categories</a>'
       . '</div></section>';
    include 'footer.php';
    exit;
}

if (!$id || $id < 1) {
    not_found_page();
}

$sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.DESCRIPTION, p.PRICE,
               p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER, p.ALLERGY_INFO,
               p.QUANTITY_PER_ITEM,
               s.SHOP_NAME, c.CATEGORY_NAME
        FROM PRODUCT p
        JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
        JOIN CATEGORY c ON p.CATEGORY_ID = c.CATEGORY_ID
        WHERE p.PRODUCT_ID = :id";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':id', $id, -1, OCI_B_INT);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$row) {
    not_found_page();
}

$page_title = htmlspecialchars($row['PRODUCT_NAME']) . ' - Cleckhuddesfax Online Mart';

$img_file  = __DIR__ . '/assets/images/' . $row['PRODUCT_NAME'] . '.png';
$has_image = file_exists($img_file);
$img_src   = $has_image ? 'assets/images/' . rawurlencode($row['PRODUCT_NAME']) . '.png' : '';

$in_stock = ((int)$row['STOCK_QUANTITY'] > 0);
$min_qty  = max(1, (int)($row['MIN_ORDER'] ?? 1));
$max_qty  = (int)($row['MAX_ORDER'] ?? 99);

include 'header.php';

if (!empty($_SESSION['cart_success'])) {
    $flash = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
    echo '<div style="background:#d1fae5;color:#065f46;padding:0.75rem 1.5rem;max-width:1200px;margin:1rem auto;border-radius:6px;">'
       . htmlspecialchars($flash) . '</div>';
}
if (!empty($_SESSION['cart_error'])) {
    $flash = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
    echo '<div style="background:#fee2e2;color:#991b1b;padding:0.75rem 1.5rem;max-width:1200px;margin:1rem auto;border-radius:6px;">'
       . htmlspecialchars($flash) . '</div>';
}
?>

<section class="product-page">
    <div class="container product-container">

        <div class="product-image-col">
            <div class="main-image">
                <?php if ($has_image): ?>
                    <img src="<?php echo $img_src; ?>"
                         alt="<?php echo htmlspecialchars($row['PRODUCT_NAME']); ?>">
                <?php else: ?>
                    <div style="width:100%;height:300px;background:#f3f3f3;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                        <span class="material-icons" style="font-size:4rem;color:#ccc;">image_not_supported</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-info-col">
            <h1 class="product-title"><?php echo htmlspecialchars($row['PRODUCT_NAME']); ?></h1>

            <div class="product-price-row">
                <span class="product-price">$<?php echo number_format((float)$row['PRICE'], 2); ?></span>
            </div>

            <?php if (!empty($row['DESCRIPTION'])): ?>
            <div class="product-description-box">
                <h5 class="description-title">DESCRIPTION</h5>
                <div class="product-description">
                    <?php echo htmlspecialchars($row['DESCRIPTION']); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($row['ALLERGY_INFO'])): ?>
            <div class="product-description-box" style="margin-top:0.75rem;">
                <h5 class="description-title">ALLERGY INFO</h5>
                <div class="product-description"><?php echo htmlspecialchars($row['ALLERGY_INFO']); ?></div>
            </div>
            <?php endif; ?>

            <form method="post" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="<?php echo (int)$row['PRODUCT_ID']; ?>">

                <div class="product-quantity-row">
                    <label>QUANTITY</label>
                    <div class="quantity-wrapper">
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn minus">-</button>
                            <input type="number" name="quantity"
                                   value="<?php echo $min_qty; ?>"
                                   min="<?php echo $min_qty; ?>"
                                   max="<?php echo $max_qty; ?>"
                                   class="qty-input">
                            <button type="button" class="qty-btn plus">+</button>
                        </div>
                        <div class="stock-status">
                            <?php if ($in_stock): ?>
                                <span class="status-dot" style="background:#22c55e;width:10px;height:10px;border-radius:50%;display:inline-block;"></span>
                                In Stock (<?php echo (int)$row['STOCK_QUANTITY']; ?> available)
                            <?php else: ?>
                                <span class="status-dot" style="background:#ef4444;width:10px;height:10px;border-radius:50%;display:inline-block;"></span>
                                Out of Stock
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="product-actions">
                    <?php if ($in_stock): ?>
                        <button type="submit" class="btn btn-primary btn-full">
                            <span class="material-icons" style="font-size:1.2rem;">shopping_cart</span> ADD TO CART
                        </button>
                        <button type="button" class="btn btn-outline btn-full"
                                onclick="window.location.href='cart.php'">
                            <span class="material-icons" style="font-size:1.2rem;">bolt</span> BUY IT NOW
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-full"
                                disabled style="opacity:0.5;cursor:not-allowed;">
                            <span class="material-icons" style="font-size:1.2rem;">remove_shopping_cart</span> OUT OF STOCK
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <div class="product-meta">
                <div class="meta-row">
                    <span class="meta-label">SKU</span>
                    <span class="meta-value"><?php echo (int)$row['PRODUCT_ID']; ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">SHOP</span>
                    <span class="meta-value"><?php echo htmlspecialchars($row['SHOP_NAME']); ?></span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">CATEGORY</span>
                    <span class="meta-value text-orange"><?php echo htmlspecialchars($row['CATEGORY_NAME']); ?></span>
                </div>
                <?php if (!empty($row['QUANTITY_PER_ITEM'])): ?>
                <div class="meta-row">
                    <span class="meta-label">QTY PER ITEM</span>
                    <span class="meta-value"><?php echo htmlspecialchars($row['QUANTITY_PER_ITEM']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($row['MAX_ORDER']): ?>
                <div class="meta-row">
                    <span class="meta-label">MAX ORDER</span>
                    <span class="meta-value"><?php echo (int)$row['MAX_ORDER']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="reviews-section">
    <div class="container reviews-container">
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

        <div class="reviews-list-col">
            <div class="reviews-list-header">
                <h4>Customer Reviews</h4>
            </div>
            <div class="reviews-list">
                <p style="color:#888;padding:1rem 0;">No reviews yet. Be the first!</p>
            </div>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.qty-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = this.closest('.quantity-selector').querySelector('.qty-input');
        var val = parseInt(input.value) || 1;
        var mn  = parseInt(input.min)   || 1;
        var mx  = parseInt(input.max)   || 999;
        if (this.classList.contains('minus')) val = Math.max(mn, val - 1);
        if (this.classList.contains('plus'))  val = Math.min(mx, val + 1);
        input.value = val;
    });
});
</script>

<?php include 'footer.php'; ?>
