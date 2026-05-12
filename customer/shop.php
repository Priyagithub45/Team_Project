<?php
include '../db.php';
include 'product_image_helper.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function shop_column_exists($conn, string $column): bool
{
    static $cache = [];
    $column = strtoupper($column);

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'SHOP'
           AND COLUMN_NAME = :column_name"
    );
    oci_bind_by_name($stmt, ':column_name', $column);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $cache[$column] = (int)($row['CNT'] ?? 0) > 0;
    return $cache[$column];
}

function shop_fallback_image(string $shop_name): string
{
    $name = strtoupper($shop_name);

    if (str_contains($name, 'BUTCH') || str_contains($name, 'POULTRY')) {
        return 'assets/images/butchers.png';
    }
    if (str_contains($name, 'GREEN') || str_contains($name, 'VEGETABLE')) {
        return 'assets/images/greengrocers.png';
    }
    if (str_contains($name, 'DELICAT') || str_contains($name, 'CHEESE') || str_contains($name, 'CHARCUTERIE')) {
        return 'assets/images/delicatessen.png';
    }
    if (str_contains($name, 'FISH') || str_contains($name, 'SEAFOOD')) {
        return 'assets/images/fishmongers.png';
    }
    if (str_contains($name, 'BAKER') || str_contains($name, 'SWEET')) {
        return 'assets/images/bakery.png';
    }

    return 'assets/images/hero.png';
}

function shop_banner_src(array $shop): string
{
    $shop_name = (string)($shop['SHOP_NAME'] ?? '');
    $image_path = trim((string)($shop['SHOP_IMAGE_PATH'] ?? ''));

    if ($image_path !== '') {
        $image_path = ltrim(str_replace('\\', '/', $image_path), '/');
        if (file_exists(dirname(__DIR__) . '/' . $image_path)) {
            return '../' . product_image_url_encode_path($image_path);
        }
    }

    return shop_fallback_image($shop_name);
}

function shop_description_text(array $shop): string
{
    $description = trim((string)($shop['SHOP_DESCRIPTION'] ?? ''));
    if ($description !== '') {
        return $description;
    }

    $shop_name = (string)($shop['SHOP_NAME'] ?? 'This shop');
    $business_name = (string)($shop['BUSINESS_NAME'] ?? 'local trader');
    $name = strtoupper($shop_name);

    if (str_contains($name, 'POULTRY')) {
        return $business_name . ' brings fresh poultry, eggs, and prepared chicken cuts for everyday meals and weekend roasts.';
    }
    if (str_contains($name, 'VEGETABLE')) {
        return $business_name . ' offers fresh vegetables, salad staples, and seasonal produce selected for local collection.';
    }
    if (str_contains($name, 'SEAFOOD')) {
        return $business_name . ' prepares shellfish, smoked seafood, and coastal favourites for quick collection.';
    }
    if (str_contains($name, 'SWEET')) {
        return $business_name . ' serves cakes, pastries, and sweet treats for gifting, sharing, or a small reward at home.';
    }
    if (str_contains($name, 'CHEESE') || str_contains($name, 'CHARCUTERIE')) {
        return $business_name . ' curates cheese, cured meats, antipasti, and deli-board essentials.';
    }

    return $shop_name . ' is run by ' . $business_name . ', one of the local traders on Cleckhuddesfax Online Mart.';
}

function rating_label(float $rating, int $review_count): string
{
    if ($review_count === 0) {
        return 'No reviews yet';
    }

    return number_format($rating, 1) . ' / 5 from ' . $review_count . ' review' . ($review_count === 1 ? '' : 's');
}

$shop_id_raw = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$shop_id_raw || $shop_id_raw < 1) {
    http_response_code(404);
    $page_title = 'Shop Not Found - Cleckhuddesfax Online Mart';
    include 'header.php';
    ?>
    <section class="product-list-page">
        <div class="container">
            <div class="product-list-header">
                <h1>SHOP NOT FOUND</h1>
                <p>That shop link is not valid.</p>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

$shop_id = (string)(int)$shop_id_raw;

$description_select = shop_column_exists($conn, 'DESCRIPTION')
    ? ', s.DESCRIPTION AS SHOP_DESCRIPTION'
    : ", NULL AS SHOP_DESCRIPTION";
$image_select_shop = shop_column_exists($conn, 'IMAGE_PATH')
    ? ', s.IMAGE_PATH AS SHOP_IMAGE_PATH'
    : ", NULL AS SHOP_IMAGE_PATH";

$shop_sql = "SELECT s.SHOP_ID,
                    s.SHOP_NAME,
                    s.LOCATION,
                    s.CONTACT_NO AS SHOP_CONTACT_NO,
                    t.BUSINESS_NAME,
                    su.NAME AS TRADER_NAME,
                    su.EMAIL AS TRADER_EMAIL,
                    su.PHONE_NO AS TRADER_PHONE
                    {$description_select}
                    {$image_select_shop}
             FROM SHOP s
             JOIN TRADER t ON t.USER_ID = s.TRADER_ID
             JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
             WHERE s.SHOP_ID = :shop_id
               AND NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
               AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')";
$shop_stmt = oci_parse($conn, $shop_sql);
oci_bind_by_name($shop_stmt, ':shop_id', $shop_id);
oci_execute($shop_stmt);
$shop = oci_fetch_assoc($shop_stmt);
oci_free_statement($shop_stmt);

if (!$shop) {
    http_response_code(404);
    $page_title = 'Shop Not Found - Cleckhuddesfax Online Mart';
    include 'header.php';
    ?>
    <section class="product-list-page">
        <div class="container">
            <div class="product-list-header">
                <h1>SHOP NOT FOUND</h1>
                <p>This shop is not available right now.</p>
            </div>
        </div>
    </section>
    <?php
    include 'footer.php';
    exit;
}

$image_select = product_image_select($conn, 'p');
$active_filter = product_active_filter($conn, 'p');
$sort = strtolower(trim((string)($_GET['sort'] ?? 'name')));
$allowed_sorts = ['name', 'price_asc', 'price_desc', 'recent'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'name';
}

$category_filter_raw = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$category_filter = $category_filter_raw && $category_filter_raw > 0 ? (string)(int)$category_filter_raw : '';
$in_stock_only = ($_GET['in_stock'] ?? '') === '1';
$allergy_friendly = ($_GET['allergy_friendly'] ?? '') === '1';

$category_sql = "SELECT DISTINCT c.CATEGORY_ID, c.CATEGORY_NAME
                 FROM PRODUCT p
                 JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
                 WHERE p.SHOP_ID = :shop_id
                   {$active_filter}
                 ORDER BY c.CATEGORY_NAME";
$category_stmt = oci_parse($conn, $category_sql);
oci_bind_by_name($category_stmt, ':shop_id', $shop_id);
oci_execute($category_stmt);
$category_options = [];
while ($category = oci_fetch_assoc($category_stmt)) {
    $category_options[] = $category;
}
oci_free_statement($category_stmt);

$where_filters = '';
if ($category_filter !== '') {
    $where_filters .= ' AND p.CATEGORY_ID = :category_filter';
}
if ($in_stock_only) {
    $where_filters .= ' AND p.STOCK_QUANTITY > 0';
}
if ($allergy_friendly) {
    $where_filters .= " AND (p.ALLERGY_INFO IS NULL OR UPPER(TRIM(p.ALLERGY_INFO)) LIKE '%NO KNOWN%')";
}

$order_by = match ($sort) {
    'price_asc' => 'p.PRICE ASC, p.PRODUCT_NAME ASC',
    'price_desc' => 'p.PRICE DESC, p.PRODUCT_NAME ASC',
    'recent' => 'p.PRODUCT_ID DESC',
    default => 'p.PRODUCT_NAME ASC',
};

$all_product_sql = "SELECT p.PRODUCT_ID, p.STOCK_QUANTITY
                    FROM PRODUCT p
                    WHERE p.SHOP_ID = :shop_id
                      {$active_filter}";
$all_product_stmt = oci_parse($conn, $all_product_sql);
oci_bind_by_name($all_product_stmt, ':shop_id', $shop_id);
oci_execute($all_product_stmt);
$all_products = [];
while ($product = oci_fetch_assoc($all_product_stmt)) {
    $all_products[] = $product;
}
oci_free_statement($all_product_stmt);

$product_sql = "SELECT p.PRODUCT_ID,
                       p.PRODUCT_NAME,
                       p.PRICE,
                       p.STOCK_QUANTITY,
                       p.CATEGORY_ID,
                       s.SHOP_NAME,
                       c.CATEGORY_NAME
                       {$image_select}
                FROM PRODUCT p
                JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
                LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
                WHERE p.SHOP_ID = :shop_id
                  {$active_filter}
                  {$where_filters}
                ORDER BY {$order_by}";
$product_stmt = oci_parse($conn, $product_sql);
oci_bind_by_name($product_stmt, ':shop_id', $shop_id);
if ($category_filter !== '') {
    oci_bind_by_name($product_stmt, ':category_filter', $category_filter);
}
oci_execute($product_stmt);

$products = [];
while ($row = oci_fetch_assoc($product_stmt)) {
    $products[] = $row;
}
oci_free_statement($product_stmt);

$rating_sql = "SELECT NVL(ROUND(AVG(r.RATING), 1), 0) AS AVG_RATING,
                      COUNT(r.REVIEW_ID) AS REVIEW_COUNT
               FROM PRODUCT p
               LEFT JOIN REVIEW r ON r.PRODUCT_ID = p.PRODUCT_ID
                                AND NVL(UPPER(r.STATUS), 'APPROVED') = 'APPROVED'
                                AND NVL(UPPER(r.DISPLAY_FLAG), 'Y') = 'Y'
               WHERE p.SHOP_ID = :shop_id";
$rating_stmt = oci_parse($conn, $rating_sql);
oci_bind_by_name($rating_stmt, ':shop_id', $shop_id);
oci_execute($rating_stmt);
$rating = oci_fetch_assoc($rating_stmt) ?: ['AVG_RATING' => 0, 'REVIEW_COUNT' => 0];
oci_free_statement($rating_stmt);

$shop_name = (string)($shop['SHOP_NAME'] ?? 'Shop');
$business_name = (string)($shop['BUSINESS_NAME'] ?? 'Trader');
$trader_name = (string)($shop['TRADER_NAME'] ?? $business_name);
$shop_contact = trim((string)($shop['SHOP_CONTACT_NO'] ?? ''));
$trader_phone = trim((string)($shop['TRADER_PHONE'] ?? ''));
$trader_email = trim((string)($shop['TRADER_EMAIL'] ?? ''));
$location = trim((string)($shop['LOCATION'] ?? 'Cleckhuddersfax collection point'));
$description = shop_description_text($shop);
$banner_src = shop_banner_src($shop);
$avg_rating = (float)($rating['AVG_RATING'] ?? 0);
$review_count = (int)($rating['REVIEW_COUNT'] ?? 0);
$product_count = count($all_products);
$filtered_product_count = count($products);
$in_stock_count = 0;

foreach ($all_products as $product) {
    if ((int)($product['STOCK_QUANTITY'] ?? 0) > 0) {
        $in_stock_count++;
    }
}

$page_title = $shop_name . ' - Cleckhuddesfax Online Mart';
include 'header.php';
?>

<section class="shop-profile-page">
    <div class="container">
        <div class="shop-profile-hero">
            <div class="shop-profile-banner">
                <img src="<?= h($banner_src) ?>" alt="<?= h($shop_name) ?>">
            </div>
            <div class="shop-profile-copy">
                <span class="shop-profile-kicker">Local shop profile</span>
                <h1><?= h(strtoupper($shop_name)) ?></h1>
                <p><?= h($description) ?></p>
                <div class="shop-profile-actions">
                    <a href="#shop-products" class="btn btn-primary">VIEW PRODUCTS</a>
                    <a href="category.php" class="btn btn-outline">ALL SHOPS</a>
                </div>
            </div>
        </div>

        <div class="shop-profile-stats" aria-label="Shop summary">
            <div>
                <span class="material-icons">storefront</span>
                <strong><?= h((string)$product_count) ?></strong>
                <small>Products listed</small>
            </div>
            <div>
                <span class="material-icons">inventory_2</span>
                <strong><?= h((string)$in_stock_count) ?></strong>
                <small>In stock today</small>
            </div>
            <div>
                <span class="material-icons">star</span>
                <strong><?= $review_count > 0 ? h(number_format($avg_rating, 1)) : 'New' ?></strong>
                <small><?= h(rating_label($avg_rating, $review_count)) ?></small>
            </div>
        </div>

        <div class="shop-profile-info-grid">
            <section class="shop-profile-panel">
                <div class="shop-profile-panel-title">
                    <span class="material-icons">badge</span>
                    <h2>Trader</h2>
                </div>
                <dl class="shop-profile-details">
                    <div>
                        <dt>Trader name</dt>
                        <dd><?= h($business_name ?: $trader_name) ?></dd>
                    </div>
                    <div>
                        <dt>Shop</dt>
                        <dd><?= h($shop_name) ?></dd>
                    </div>
                    <div>
                        <dt>Location</dt>
                        <dd><?= h($location) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="shop-profile-panel">
                <div class="shop-profile-panel-title">
                    <span class="material-icons">contact_phone</span>
                    <h2>Contact</h2>
                </div>
                <dl class="shop-profile-details">
                    <div>
                        <dt>Phone</dt>
                        <dd><?= h($shop_contact !== '' ? $shop_contact : ($trader_phone !== '' ? $trader_phone : 'Not provided')) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= h($trader_email !== '' ? $trader_email : 'Not provided') ?></dd>
                    </div>
                    <div>
                        <dt>Support</dt>
                        <dd>Use the order invoice for order-specific queries.</dd>
                    </div>
                </dl>
            </section>

            <section class="shop-profile-panel">
                <div class="shop-profile-panel-title">
                    <span class="material-icons">event_available</span>
                    <h2>Collection Rules</h2>
                </div>
                <ul class="shop-profile-rules">
                    <li>Available Wednesday, Thursday, and Friday.</li>
                    <li>Collection windows: 10:00-13:00, 13:00-16:00, and 16:00-19:00.</li>
                    <li>Orders must be placed at least 24 hours before collection.</li>
                    <li>Each slot can accept up to 20 customer orders.</li>
                </ul>
            </section>
        </div>

        <div class="shop-products-heading" id="shop-products">
            <div>
                <span>Available products</span>
                <h2><?= h(strtoupper($shop_name)) ?></h2>
            </div>
            <p><?= h((string)$filtered_product_count) ?> of <?= h((string)$product_count) ?> products shown</p>
        </div>

        <form class="shop-product-filters" method="get" action="shop.php" aria-label="Filter shop products">
            <input type="hidden" name="id" value="<?= (int)$shop_id ?>">

            <div class="shop-filter-field">
                <label for="sort">Sort</label>
                <select id="sort" name="sort">
                    <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Product name</option>
                    <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price low to high</option>
                    <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price high to low</option>
                    <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Recently added</option>
                </select>
            </div>

            <div class="shop-filter-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($category_options as $category): ?>
                        <?php $selected = $category_filter !== '' && (int)$category_filter === (int)$category['CATEGORY_ID'] ? ' selected' : ''; ?>
                        <option value="<?= (int)$category['CATEGORY_ID'] ?>"<?= $selected ?>>
                            <?= h((string)$category['CATEGORY_NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="shop-filter-toggle">
                <input type="checkbox" name="in_stock" value="1"<?= $in_stock_only ? ' checked' : '' ?>>
                <span>In stock only</span>
            </label>

            <label class="shop-filter-toggle">
                <input type="checkbox" name="allergy_friendly" value="1"<?= $allergy_friendly ? ' checked' : '' ?>>
                <span>Allergy-friendly</span>
            </label>

            <div class="shop-filter-actions">
                <button type="submit">Apply</button>
                <a href="shop.php?id=<?= (int)$shop_id ?>">Reset</a>
            </div>
        </form>

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
                            <span class="product-list-name"><?= h((string)$p['PRODUCT_NAME']) ?></span>
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

<?php include 'footer.php'; ?>
