<?php

function render_shop_group_page(array $config): void
{
    include '../db.php';
    include 'product_image_helper.php';

    $page_title = (string)($config['page_title'] ?? 'Products - Cleckhuddesfax Online Mart');
    $heading = (string)($config['heading'] ?? 'Products');
    $intro = (string)($config['intro'] ?? '');
    $keywords = $config['keywords'] ?? [];

    if (!is_array($keywords) || empty($keywords)) {
        $keywords = [$heading];
    }

    $conditions = [];
    $binds = [];
    foreach (array_values($keywords) as $index => $keyword) {
        $bind_name = ':kw' . $index;
        $conditions[] = "(UPPER(s.SHOP_NAME) LIKE {$bind_name} OR UPPER(c.CATEGORY_NAME) LIKE {$bind_name})";
        $binds[$bind_name] = '%' . strtoupper((string)$keyword) . '%';
    }

    $image_select = product_image_select($conn, 'p');
    $active_filter = product_active_filter($conn, 'p');
    $sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRICE, p.STOCK_QUANTITY,
                   s.SHOP_NAME, c.CATEGORY_NAME
                   {$image_select}
            FROM PRODUCT p
            JOIN SHOP s ON p.SHOP_ID = s.SHOP_ID
            LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
            WHERE (" . implode(' OR ', $conditions) . ")
              {$active_filter}
            ORDER BY s.SHOP_NAME, p.PRODUCT_NAME";

    $stmt = oci_parse($conn, $sql);
    foreach ($binds as $bind_name => $value) {
        oci_bind_by_name($stmt, $bind_name, $binds[$bind_name]);
    }
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
                <h1><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
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
                            <span class="product-list-name"><?= htmlspecialchars((string)$p['PRODUCT_NAME'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="product-list-price">GBP <?= number_format((float)$p['PRICE'], 2) ?></span>
                        </div>
                        <a href="product.php?id=<?= (int)$p['PRODUCT_ID'] ?>" class="btn-view-product">
                            <span class="material-icons">visibility</span> VIEW PRODUCT
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'footer.php';
}
