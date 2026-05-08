<?php
include '../db.php';

$query = trim($_GET['q'] ?? '');
$query = preg_replace('/\s+/', ' ', $query);
$page_title = $query === ''
    ? 'Search Products - Cleckhuddesfax Online Mart'
    : 'Search for ' . $query . ' - Cleckhuddesfax Online Mart';

$results = [];
$best_matches = [];
$related_matches = [];
$extra_related = [];

function product_image_exists(string $product_name): bool
{
    return file_exists(__DIR__ . '/assets/images/' . $product_name . '.png');
}

function render_product_card(array $product): void
{
    $name = $product['PRODUCT_NAME'] ?? '';
    $has_img = product_image_exists($name);
    ?>
    <div class="product-list-card">
        <div class="product-list-img-box">
            <?php if ($has_img): ?>
                <img src="assets/images/<?php echo rawurlencode($name); ?>.png"
                     alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                     style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <div style="width:100%;height:100%;background:#f3f3f3;display:flex;align-items:center;justify-content:center;">
                    <span class="material-icons" style="color:#ccc;font-size:2.5rem;">image_not_supported</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="product-list-info">
            <span class="product-list-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="product-list-price">$<?php echo number_format((float)($product['PRICE'] ?? 0), 2); ?></span>
        </div>
        <div class="search-card-meta">
            <span><?php echo htmlspecialchars($product['SHOP_NAME'] ?? 'Local trader', ENT_QUOTES, 'UTF-8'); ?></span>
            <span><?php echo htmlspecialchars($product['CATEGORY_NAME'] ?? 'Product', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <a href="product.php?id=<?php echo (int)$product['PRODUCT_ID']; ?>" class="btn-view-product">
            <span class="material-icons">visibility</span> VIEW PRODUCT
        </a>
    </div>
    <?php
}

if ($query !== '') {
    $exact = strtoupper($query);
    $prefix = strtoupper($query) . '%';
    $like = '%' . strtoupper($query) . '%';

    $sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.DESCRIPTION, p.PRICE, p.STOCK_QUANTITY,
                   s.SHOP_ID, s.SHOP_NAME,
                   c.CATEGORY_ID, c.CATEGORY_NAME,
                   CASE
                       WHEN UPPER(p.PRODUCT_NAME) = :exact_rank THEN 1
                       WHEN UPPER(p.PRODUCT_NAME) LIKE :prefix_rank THEN 2
                       WHEN UPPER(p.PRODUCT_NAME) LIKE :name_rank THEN 3
                       WHEN UPPER(p.DESCRIPTION) LIKE :desc_rank THEN 4
                       WHEN UPPER(c.CATEGORY_NAME) LIKE :cat_rank THEN 5
                       WHEN UPPER(s.SHOP_NAME) LIKE :shop_rank THEN 6
                       ELSE 9
                   END AS MATCH_SCORE
            FROM PRODUCT p
            JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
            JOIN CATEGORY c ON p.CATEGORY_ID = c.CATEGORY_ID
            WHERE UPPER(p.PRODUCT_NAME) LIKE :name_filter
               OR UPPER(p.DESCRIPTION) LIKE :desc_filter
               OR UPPER(c.CATEGORY_NAME) LIKE :cat_filter
               OR UPPER(s.SHOP_NAME) LIKE :shop_filter
            ORDER BY MATCH_SCORE, p.PRODUCT_NAME
            FETCH FIRST 48 ROWS ONLY";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':exact_rank', $exact);
    oci_bind_by_name($stmt, ':prefix_rank', $prefix);
    oci_bind_by_name($stmt, ':name_rank', $like);
    oci_bind_by_name($stmt, ':desc_rank', $like);
    oci_bind_by_name($stmt, ':cat_rank', $like);
    oci_bind_by_name($stmt, ':shop_rank', $like);
    oci_bind_by_name($stmt, ':name_filter', $like);
    oci_bind_by_name($stmt, ':desc_filter', $like);
    oci_bind_by_name($stmt, ':cat_filter', $like);
    oci_bind_by_name($stmt, ':shop_filter', $like);
    oci_execute($stmt);

    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
    }
    oci_free_statement($stmt);

    foreach ($results as $product) {
        if ((int)$product['MATCH_SCORE'] <= 3) {
            $best_matches[] = $product;
        } else {
            $related_matches[] = $product;
        }
    }

    if (!empty($best_matches)) {
        $seed = $best_matches[0];
        $seed_category_id = (string)(int)$seed['CATEGORY_ID'];
        $seed_shop_id = (string)(int)$seed['SHOP_ID'];
        $seed_product_id = (string)(int)$seed['PRODUCT_ID'];

        $sql_related = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.DESCRIPTION, p.PRICE, p.STOCK_QUANTITY,
                               s.SHOP_ID, s.SHOP_NAME,
                               c.CATEGORY_ID, c.CATEGORY_NAME,
                               7 AS MATCH_SCORE
                        FROM PRODUCT p
                        JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
                        JOIN CATEGORY c ON p.CATEGORY_ID = c.CATEGORY_ID
                        WHERE p.PRODUCT_ID <> :seed_product_id
                          AND (p.CATEGORY_ID = :seed_category_id OR p.SHOP_ID = :seed_shop_id)
                        ORDER BY
                          CASE WHEN p.CATEGORY_ID = :seed_category_rank THEN 1 ELSE 2 END,
                          p.PRODUCT_NAME
                        FETCH FIRST 12 ROWS ONLY";
        $stmt = oci_parse($conn, $sql_related);
        oci_bind_by_name($stmt, ':seed_product_id', $seed_product_id);
        oci_bind_by_name($stmt, ':seed_category_id', $seed_category_id);
        oci_bind_by_name($stmt, ':seed_shop_id', $seed_shop_id);
        oci_bind_by_name($stmt, ':seed_category_rank', $seed_category_id);
        oci_execute($stmt);

        $seen_ids = [];
        foreach ($results as $product) {
            $seen_ids[(int)$product['PRODUCT_ID']] = true;
        }
        while ($row = oci_fetch_assoc($stmt)) {
            $id = (int)$row['PRODUCT_ID'];
            if (!isset($seen_ids[$id])) {
                $extra_related[] = $row;
                $seen_ids[$id] = true;
            }
        }
        oci_free_statement($stmt);
    }
}

if (empty($best_matches) && !empty($related_matches)) {
    $best_matches = array_slice($related_matches, 0, 8);
    $related_matches = array_slice($related_matches, 8);
}

$related_products = array_merge($related_matches, $extra_related);

include 'header.php';
?>

<section class="search-page">
    <div class="container">
        <div class="search-results-header">
            <span class="motto">SEARCH</span>
            <h1>PRODUCT SEARCH</h1>
            <?php if ($query !== ''): ?>
                <p>
                    Results for <strong><?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
            <?php else: ?>
                <p>Search for fresh products by name, trader, category, or description.</p>
            <?php endif; ?>
        </div>

        <form class="search-page-form" action="search.php" method="get" role="search">
            <span class="material-icons">search</span>
            <input
                type="search"
                name="q"
                value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Try bread, salmon, cheese, bakery..."
                autocomplete="off"
                autofocus
            >
            <button type="submit">SEARCH</button>
        </form>

        <?php if ($query === ''): ?>
            <div class="search-empty-state">
                <h2>Find local goods fast</h2>
                <p>Search product names, shops, or categories from Cleckhuddesfax traders.</p>
                <div class="search-chip-row">
                    <a href="search.php?q=bread">Bread</a>
                    <a href="search.php?q=salmon">Salmon</a>
                    <a href="search.php?q=cheese">Cheese</a>
                    <a href="search.php?q=vegetables">Vegetables</a>
                    <a href="search.php?q=butcher">Butcher</a>
                </div>
            </div>
        <?php elseif (empty($best_matches) && empty($related_products)): ?>
            <div class="search-empty-state">
                <h2>No products found</h2>
                <p>Try a broader term such as a trader name, category, or ingredient.</p>
                <div class="search-chip-row">
                    <a href="search.php?q=bakery">Bakery</a>
                    <a href="search.php?q=fish">Fish</a>
                    <a href="search.php?q=fruit">Fruit</a>
                    <a href="search.php?q=meat">Meat</a>
                </div>
            </div>
        <?php else: ?>
            <div class="search-section-title">
                <h2>Best Matches</h2>
                <span><?php echo count($best_matches); ?> result<?php echo count($best_matches) === 1 ? '' : 's'; ?></span>
            </div>
            <div class="product-list-grid search-results-grid">
                <?php foreach ($best_matches as $product): ?>
                    <?php render_product_card($product); ?>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($related_products)): ?>
                <div class="search-section-title related-title">
                    <h2>Related Products</h2>
                    <span><?php echo count($related_products); ?> suggestion<?php echo count($related_products) === 1 ? '' : 's'; ?></span>
                </div>
                <div class="product-list-grid search-results-grid">
                    <?php foreach ($related_products as $product): ?>
                        <?php render_product_card($product); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include 'footer.php'; ?>
