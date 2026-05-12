<?php
include '../db.php';
include 'product_image_helper.php';

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

$image_select = product_image_select($conn, 'p');
$active_filter = product_active_filter($conn, 'p');
$sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.DESCRIPTION, p.PRICE,
               p.STOCK_QUANTITY, p.MIN_ORDER, p.MAX_ORDER, p.ALLERGY_INFO,
               p.QUANTITY_PER_ITEM
               {$image_select},
               s.SHOP_NAME, c.CATEGORY_NAME
        FROM PRODUCT p
        JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
        JOIN CATEGORY c ON p.CATEGORY_ID = c.CATEGORY_ID
        WHERE p.PRODUCT_ID = :id
          {$active_filter}";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':id', $id, -1, OCI_B_INT);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
oci_free_statement($stmt);

if (!$row) {
    not_found_page();
}

$page_title = htmlspecialchars($row['PRODUCT_NAME']) . ' - Cleckhuddesfax Online Mart';

$in_stock = ((int)$row['STOCK_QUANTITY'] > 0);
$min_qty  = max(1, (int)($row['MIN_ORDER'] ?? 1));
$max_qty  = (int)($row['MAX_ORDER'] ?? 99);
$product_id = (string)(int)$row['PRODUCT_ID'];
$current_user_id = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer')
    ? (string)(int)$_SESSION['user_id']
    : null;

$reviews = [];
$stmt = oci_parse($conn, "SELECT r.REVIEW_ID, r.RATING, r.COMMENT_TEXT, r.REVIEW_DATE,
                                 su.NAME AS CUSTOMER_NAME
                          FROM REVIEW r
                          JOIN SYSTEM_USER su ON su.USER_ID = r.CUSTOMER_ID
                          WHERE r.PRODUCT_ID = :p_pid
                            AND NVL(UPPER(r.STATUS), 'APPROVED') = 'APPROVED'
                            AND NVL(UPPER(r.DISPLAY_FLAG), 'Y') = 'Y'
                          ORDER BY r.REVIEW_DATE DESC, r.REVIEW_ID DESC");
oci_bind_by_name($stmt, ':p_pid', $product_id);
oci_execute($stmt);
while ($review = oci_fetch_assoc($stmt)) {
    $reviews[] = $review;
}
oci_free_statement($stmt);

$review_count = count($reviews);
$average_rating = 0.0;
if ($review_count > 0) {
    $rating_total = 0;
    foreach ($reviews as $review) {
        $rating_total += (int)$review['RATING'];
    }
    $average_rating = $rating_total / $review_count;
}

$has_purchased_product = false;
$has_reviewed_product = false;
if ($current_user_id !== null) {
    $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT
                              FROM ORDERS o
                              JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
                              WHERE o.CUSTOMER_ID = :p_uid
                                AND oi.PRODUCT_ID = :p_pid");
    oci_bind_by_name($stmt, ':p_uid', $current_user_id);
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    oci_execute($stmt);
    $purchase = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    $has_purchased_product = ((int)($purchase['CNT'] ?? 0) > 0);

    $stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT
                              FROM REVIEW
                              WHERE CUSTOMER_ID = :p_uid
                                AND PRODUCT_ID = :p_pid");
    oci_bind_by_name($stmt, ':p_uid', $current_user_id);
    oci_bind_by_name($stmt, ':p_pid', $product_id);
    oci_execute($stmt);
    $existing_review = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    $has_reviewed_product = ((int)($existing_review['CNT'] ?? 0) > 0);
}

$stock_quantity = (int)$row['STOCK_QUANTITY'];
$stock_label = $in_stock ? ($stock_quantity <= 5 ? 'Low stock' : 'In stock') : 'Out of stock';
$stock_class = $in_stock ? ($stock_quantity <= 5 ? 'low' : 'available') : 'out';
$rating_text = $review_count > 0
    ? number_format($average_rating, 1) . ' from ' . $review_count . ' review' . ($review_count === 1 ? '' : 's')
    : 'No reviews yet';

function render_review_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-icons">' . ($i <= $rating ? 'star' : 'star_border') . '</span>';
    }
    return $html;
}

include 'header.php';

if (!empty($_SESSION['cart_success'])) {
    $flash = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
    echo '<div class="cfo-flash cfo-flash-success" style="display:none" data-extra-html="&lt;a href=\'cart.php\' class=\'cfo-flash-link\'&gt;View cart&lt;/a&gt;">'
       . htmlspecialchars($flash) . '</div>';
}
if (!empty($_SESSION['cart_error'])) {
    $flash = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
    echo '<div class="cfo-flash cfo-flash-error" style="display:none">'
       . htmlspecialchars($flash) . '</div>';
}
if (!empty($_SESSION['review_success'])) {
    $flash = $_SESSION['review_success'];
    unset($_SESSION['review_success']);
    echo '<div class="cfo-flash cfo-flash-success" style="display:none">'
       . htmlspecialchars($flash) . '</div>';
}
if (!empty($_SESSION['review_error'])) {
    $flash = $_SESSION['review_error'];
    unset($_SESSION['review_error']);
    echo '<div class="cfo-flash cfo-flash-error" style="display:none">'
       . htmlspecialchars($flash) . '</div>';
}
?>

<section class="product-page">
    <div class="container product-container">

        <div class="product-image-col">
            <div class="product-image-badge"><?= htmlspecialchars($stock_label) ?></div>
            <div class="main-image">
                <?php render_product_image(
                    $row,
                    'width:100%;height:100%;object-fit:cover;',
                    'width:100%;height:300px;background:#f3f3f3;display:flex;align-items:center;justify-content:center;border-radius:8px;',
                    'font-size:4rem;color:#ccc;'
                ); ?>
            </div>
        </div>

        <div class="product-info-col">
            <div class="product-breadcrumb">
                <a href="category.php">Shops</a>
                <span>/</span>
                <span><?php echo htmlspecialchars($row['CATEGORY_NAME']); ?></span>
            </div>

            <div class="product-shop-chip">
                <span class="material-icons">storefront</span>
                <?php echo htmlspecialchars($row['SHOP_NAME']); ?>
            </div>

            <h1 class="product-title"><?php echo htmlspecialchars($row['PRODUCT_NAME']); ?></h1>

            <div class="product-rating-line">
                <div class="stars-filled"><?php echo $review_count > 0 ? render_review_stars((int)round($average_rating)) : render_review_stars(0); ?></div>
                <span><?php echo htmlspecialchars($rating_text); ?></span>
            </div>

            <div class="product-price-row">
                <span class="product-price">GBP <?php echo number_format((float)$row['PRICE'], 2); ?></span>
            </div>

            <div class="product-purchase-card">
                <?php if (!empty($row['DESCRIPTION'])): ?>
                <div class="product-description-box">
                    <h5 class="description-title">DESCRIPTION</h5>
                    <div class="product-description">
                        <?php echo htmlspecialchars($row['DESCRIPTION']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($row['ALLERGY_INFO'])): ?>
                <div class="product-description-box allergy-box">
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
                            <div class="stock-status <?= htmlspecialchars($stock_class) ?>">
                                <?php if ($in_stock): ?>
                                    <span class="status-dot"></span>
                                    <?php echo htmlspecialchars($stock_label); ?> (<?php echo $stock_quantity; ?> available)
                                <?php else: ?>
                                    <span class="status-dot"></span>
                                    Out of Stock
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="product-actions">
                        <?php if ($in_stock): ?>
                            <button type="submit" class="btn btn-primary btn-full" data-ajax-cart="1">
                                <span class="material-icons" style="font-size:1.2rem;">shopping_cart</span> ADD TO CART
                            </button>
                            <button type="submit" formaction="buy_now.php" class="btn btn-outline btn-full">
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
            </div>

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
            <?php if ($review_count > 0): ?>
                <div class="review-summary-card">
                    <div class="review-average"><?php echo number_format($average_rating, 1); ?></div>
                    <div>
                        <div class="stars-filled"><?php echo render_review_stars((int)round($average_rating)); ?></div>
                        <p><?php echo $review_count; ?> review<?php echo $review_count === 1 ? '' : 's'; ?> for this product</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$current_user_id): ?>
                <div class="review-form-card">
                    <h3>Want to review this product?</h3>
                    <p class="review-help-text">Log in after purchasing this product to share your thoughts.</p>
                    <a href="login.php" class="btn btn-full submit-review-btn">LOGIN TO REVIEW</a>
                </div>
            <?php elseif (!$has_purchased_product): ?>
                <div class="review-form-card">
                    <h3>Verified buyers only</h3>
                    <p class="review-help-text">You can review this product after it appears in your order history.</p>
                </div>
            <?php elseif ($has_reviewed_product): ?>
                <div class="review-form-card">
                    <h3>Review submitted</h3>
                    <p class="review-help-text">You have already reviewed this product. Thank you for helping other customers.</p>
                </div>
            <?php else: ?>
                <div class="review-form-card">
                    <h3>Share your thoughts</h3>
                    <form method="post" action="submit_review.php">
                        <input type="hidden" name="product_id" value="<?php echo (int)$row['PRODUCT_ID']; ?>">

                        <div class="rating-input">
                            <label class="rating-label" for="rating">YOUR RATING</label>
                            <select id="rating" name="rating" class="review-rating-select" required>
                                <option value="">Select rating</option>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Very good</option>
                                <option value="3">3 - Good</option>
                                <option value="2">2 - Fair</option>
                                <option value="1">1 - Poor</option>
                            </select>
                        </div>
                        <div class="review-input">
                            <label class="review-label" for="comment">REVIEW</label>
                            <textarea id="comment" name="comment" maxlength="255" required placeholder="Write a review."></textarea>
                        </div>
                        <button type="submit" class="btn btn-full submit-review-btn">SUBMIT REVIEW</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="reviews-list-col">
            <div class="reviews-list-header">
                <h4>Customer Reviews</h4>
            </div>
            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                    <p style="color:#888;padding:1rem 0;">No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-item">
                            <div class="stars-filled"><?php echo render_review_stars((int)$review['RATING']); ?></div>
                            <div class="reviewer-name">
                                <?php echo htmlspecialchars($review['CUSTOMER_NAME'] ?? 'Customer'); ?>
                                <span class="review-date">
                                    <?php echo $review['REVIEW_DATE'] ? htmlspecialchars(date('d M Y', strtotime($review['REVIEW_DATE']))) : ''; ?>
                                </span>
                            </div>
                            <p class="review-text"><?php echo htmlspecialchars($review['COMMENT_TEXT'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
