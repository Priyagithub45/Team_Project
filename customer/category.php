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

function trader_card_config(string $business_name): array
{
    $name = strtoupper($business_name);

    if (str_contains($name, 'BUTCH')) {
        return ['image' => 'assets/images/butchers.png', 'href' => 'butcher.php'];
    }
    if (str_contains($name, 'GREEN')) {
        return ['image' => 'assets/images/greengrocers.png', 'href' => 'greengrocers.php'];
    }
    if (str_contains($name, 'DELICAT')) {
        return ['image' => 'assets/images/delicatessen.png', 'href' => 'delicatessen.php'];
    }
    if (str_contains($name, 'FISH')) {
        return ['image' => 'assets/images/fishmongers.png', 'href' => 'fishmongers.php'];
    }
    if (str_contains($name, 'BAKER')) {
        return ['image' => 'assets/images/bakery.png', 'href' => 'bakery.php'];
    }

    return ['image' => '', 'href' => 'search.php?q=' . urlencode($business_name)];
}

$shop_image_select = category_column_exists($conn, 'SHOP', 'IMAGE_PATH')
    ? ', s.IMAGE_PATH AS SHOP_IMAGE_PATH'
    : ", NULL AS SHOP_IMAGE_PATH";

$trader_sql = "
    SELECT t.USER_ID,
           t.BUSINESS_NAME
           {$shop_image_select}
    FROM TRADER t
    JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
    LEFT JOIN SHOP s ON s.TRADER_ID = t.USER_ID
    WHERE NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
      AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
    ORDER BY t.USER_ID
";

$traders = [];
$trader_stmt = oci_parse($conn, $trader_sql);
if ($trader_stmt && oci_execute($trader_stmt)) {
    while ($trader = oci_fetch_assoc($trader_stmt)) {
        $traders[] = $trader;
    }
    oci_free_statement($trader_stmt);
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
            <?php if (empty($traders)): ?>
                <div class="category-empty">No verified traders are available yet.</div>
            <?php else: ?>
                <?php foreach ($traders as $trader): ?>
                    <?php
                    $business_name = (string)($trader['BUSINESS_NAME'] ?? 'Trader');
                    $card = trader_card_config($business_name);
                    $shop_image_path = trim((string)($trader['SHOP_IMAGE_PATH'] ?? ''));
                    $image_src = $shop_image_path !== '' ? '../' . $shop_image_path : $card['image'];
                    $is_placeholder = $image_src === '';
                    ?>
                    <a href="<?= h($card['href']) ?>" class="category-card<?= $is_placeholder ? ' trader-placeholder' : '' ?>" style="display: flex; text-decoration: none; cursor: pointer;">
                        <div class="category-img-area">
                            <?php if ($is_placeholder): ?>
                                <div class="category-img-placeholder"><?= h($business_name) ?></div>
                            <?php else: ?>
                                <img src="<?= h($image_src) ?>" alt="<?= h($business_name) ?>" class="<?= $shop_image_path !== '' ? 'category-shop-image' : '' ?>" style="max-height: 100%; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                        <div class="category-divider"></div>
                        <div class="category-bottom-area">
                            <h2><?= h($business_name) ?></h2>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'footer.php'; ?>
