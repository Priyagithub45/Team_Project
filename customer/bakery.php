<?php
include '../db.php';
include 'product_image_helper.php';
$page_title = 'Bakery - Cleckhuddesfax Online Mart';

$keyword = '%BAKER%';
$image_select = product_image_select($conn, 'p');
$sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE, p.STOCK_QUANTITY,
               s.SHOP_NAME
               {$image_select}
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
            <h1>BAKERY</h1>
            <p>Artisan breads and pastries, baked right here in Cleckhuddesfax using locally sourced ingredients and traditional methods.</p>
        </div>

        <div class="product-list-grid">
            <?php if (empty($products)): ?>
                <p style="color:#888;padding:2rem 0;">No products available at the moment.</p>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                <div class="product-list-card">
                    <div class="product-list-img-box">
                        <?php render_product_image($p); ?>
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
