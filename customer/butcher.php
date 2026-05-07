<?php
include '../db.php';
$page_title = 'Butchers - Cleckhuddesfax Online Mart';

$keyword = '%BUTCH%';
$sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE, p.STOCK_QUANTITY
        FROM PRODUCT p
        JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
        WHERE UPPER(s.SHOP_NAME) LIKE :kw
        ORDER BY p.PRODUCT_NAME";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':kw', $keyword);
oci_execute($stmt);
$products = [];
while ($row = oci_fetch_assoc($stmt)) {
    $products[] = $row;
}
oci_free_statement($stmt);

include 'header.php';
?>

<section class="product-list-page">
    <div class="container">
        <div class="product-list-header">
            <h1>BUTCHERS</h1>
            <p>Premium cuts, ethically sourced. Discover our curated selection of high-quality meats for your next culinary masterpiece.</p>
        </div>

        <div class="product-list-grid">
            <?php if (empty($products)): ?>
                <p style="color:#888;padding:2rem 0;">No products available at the moment.</p>
            <?php else: ?>
                <?php foreach ($products as $p):
                    $has_img = file_exists(__DIR__ . '/assets/images/' . $p['PRODUCT_NAME'] . '.png');
                ?>
                <div class="product-list-card">
                    <div class="product-list-img-box">
                        <?php if ($has_img): ?>
                            <img src="assets/images/<?php echo rawurlencode($p['PRODUCT_NAME']); ?>.png"
                                 alt="<?php echo htmlspecialchars($p['PRODUCT_NAME']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:#f3f3f3;display:flex;align-items:center;justify-content:center;">
                                <span class="material-icons" style="color:#ccc;font-size:2.5rem;">image_not_supported</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-list-info">
                        <span class="product-list-name"><?php echo htmlspecialchars($p['PRODUCT_NAME']); ?></span>
                        <span class="product-list-price">$<?php echo number_format((float)$p['PRICE'], 2); ?></span>
                    </div>
                    <a href="product.php?id=<?php echo (int)$p['PRODUCT_ID']; ?>" class="btn-view-product">
                        <span class="material-icons">visibility</span> VIEW PRODUCT
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
