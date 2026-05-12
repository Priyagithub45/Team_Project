<?php
/**
 * Category Page - Cleckhuddesfax Online Mart
 */
include '../db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function category_column_exists($conn, string $table, string $column): bool
{
    $stmt = oci_parse(
        $conn,
        'SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );

    if (!$stmt) {
        return false;
    }

    $table_name = strtoupper($table);
    $column_name = strtoupper($column);
    oci_bind_by_name($stmt, ':table_name', $table_name);
    oci_bind_by_name($stmt, ':column_name', $column_name);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return false;
    }

    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    return (int)($row['CNT'] ?? 0) > 0;
}

function shop_card_config(string $shop_name): array
{
    $name = strtoupper($shop_name);

    if (str_contains($name, 'BUTCH') || str_contains($name, 'POULTRY')) {
        return ['image' => 'assets/images/butchers.png'];
    }
    if (str_contains($name, 'GREEN') || str_contains($name, 'VEGETABLE')) {
        return ['image' => 'assets/images/greengrocers.png'];
    }
    if (str_contains($name, 'DELICAT') || str_contains($name, 'CHEESE') || str_contains($name, 'CHARCUTERIE')) {
        return ['image' => 'assets/images/delicatessen.png'];
    }
    if (str_contains($name, 'FISH') || str_contains($name, 'SEAFOOD')) {
        return ['image' => 'assets/images/fishmongers.png'];
    }
    if (str_contains($name, 'BAKER') || str_contains($name, 'SWEET')) {
        return ['image' => 'assets/images/bakery.png'];
    }

    return ['image' => ''];
}

$shop_image_select = category_column_exists($conn, 'SHOP', 'IMAGE_PATH')
    ? ', s.IMAGE_PATH AS SHOP_IMAGE_PATH'
    : ", NULL AS SHOP_IMAGE_PATH";

$shop_sql = "
    SELECT s.SHOP_ID,
           s.SHOP_NAME,
           t.USER_ID,
           t.BUSINESS_NAME
           {$shop_image_select}
    FROM SHOP s
    JOIN TRADER t ON t.USER_ID = s.TRADER_ID
    JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
    WHERE NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
      AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
    ORDER BY s.SHOP_NAME
";

$shops = [];
$shop_stmt = oci_parse($conn, $shop_sql);
if ($shop_stmt && oci_execute($shop_stmt)) {
    while ($shop = oci_fetch_assoc($shop_stmt)) {
        $shops[] = $shop;
    }
    oci_free_statement($shop_stmt);
}

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
            <?php if (empty($shops)): ?>
                <div class="category-empty">No verified shops are available yet.</div>
            <?php else: ?>
                <?php foreach ($shops as $shop): ?>
                    <?php
                    $shop_name = (string)($shop['SHOP_NAME'] ?? 'Shop');
                    $business_name = (string)($shop['BUSINESS_NAME'] ?? 'Trader');
                    $card = shop_card_config($shop_name);
                    $href = 'shop.php?id=' . (int)($shop['SHOP_ID'] ?? 0);
                    $shop_image_path = trim((string)($shop['SHOP_IMAGE_PATH'] ?? ''));
                    $image_src = $shop_image_path !== '' ? '../' . $shop_image_path : $card['image'];
                    $is_placeholder = $image_src === '';
                    ?>
                    <a href="<?= h($href) ?>" class="category-card<?= $is_placeholder ? ' trader-placeholder' : '' ?>" style="display: flex; text-decoration: none; cursor: pointer;">
                        <div class="category-img-area">
                            <?php if ($is_placeholder): ?>
                                <div class="category-img-placeholder"><?= h($shop_name) ?></div>
                            <?php else: ?>
                                <img src="<?= h($image_src) ?>" alt="<?= h($shop_name) ?>" class="<?= $shop_image_path !== '' ? 'category-shop-image' : '' ?>" style="max-height: 100%; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                        <div class="category-divider"></div>
                        <div class="category-bottom-area">
                            <h2><?= h($shop_name) ?></h2>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
