<?php
include '../db.php';
include 'auth_check.php';
include 'product_image_helper.php';
require_once 'wishlist_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$page_title = 'My Wishlist - Cleckhuddesfax Online Mart';
$customer_id = (int)$_SESSION['user_id'];
$wishlist_ready = cfo_wishlist_table_exists($conn);
$wishlist_items = [];

if ($wishlist_ready) {
    $image_select = product_image_select($conn, 'p');
    $discount_select = cfo_discount_select_sql('p');
    $status_select = product_status_column_exists($conn)
        ? ", NVL(p.STATUS, 'ACTIVE') AS PRODUCT_STATUS"
        : ", 'ACTIVE' AS PRODUCT_STATUS";

    $sql = "SELECT w.WISHLIST_ID,
                   TO_CHAR(w.CREATED_AT, 'DD Mon YYYY') AS SAVED_DATE,
                   p.PRODUCT_ID,
                   p.PRODUCT_NAME,
                   p.DESCRIPTION,
                   p.PRICE
                   {$discount_select},
                   NVL(p.STOCK_QUANTITY, 0) AS STOCK_QUANTITY,
                   s.SHOP_NAME,
                   c.CATEGORY_NAME
                   {$status_select}
                   {$image_select}
            FROM WISHLIST w
            JOIN PRODUCT p ON p.PRODUCT_ID = w.PRODUCT_ID
            JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
            LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
            WHERE w.CUSTOMER_ID = :customer_id
            ORDER BY w.CREATED_AT DESC, w.WISHLIST_ID DESC";

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    if ($stmt && oci_execute($stmt)) {
        while ($row = oci_fetch_assoc($stmt)) {
            $wishlist_items[] = $row;
        }
    }
    if ($stmt) {
        oci_free_statement($stmt);
    }
}

include 'header.php';

if (!empty($_SESSION['cart_success'])) {
    $flash = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
    echo '<div class="cfo-flash cfo-flash-success" style="display:none" data-extra-html="&lt;a href=\'cart.php\' class=\'cfo-flash-link\'&gt;View cart&lt;/a&gt;">'
       . h($flash) . '</div>';
}
if (!empty($_SESSION['cart_error'])) {
    $flash = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
    echo '<div class="cfo-flash cfo-flash-error" style="display:none">' . h($flash) . '</div>';
}
?>

<main class="wishlist-page">
    <section class="wishlist-hero">
        <div class="container wishlist-hero-inner">
            <div>
                <span class="wishlist-kicker">Saved Products</span>
                <h1>My Wishlist</h1>
                <p>Keep track of products you love, then add them to your cart when you are ready to collect.</p>
            </div>
            <div class="wishlist-hero-count">
                <strong><?= h((string)count($wishlist_items)) ?></strong>
                <span>Saved item<?= count($wishlist_items) === 1 ? '' : 's' ?></span>
            </div>
        </div>
    </section>

    <section class="wishlist-content">
        <div class="container">
            <?php if (!$wishlist_ready): ?>
                <div class="wishlist-empty">
                    <span class="material-icons" aria-hidden="true">construction</span>
                    <h2>Wishlist setup is pending</h2>
                    <p>Run the latest database migration to enable customer wishlist storage.</p>
                </div>
            <?php elseif (empty($wishlist_items)): ?>
                <div class="wishlist-empty">
                    <span class="material-icons" aria-hidden="true">favorite_border</span>
                    <h2>Your wishlist is empty</h2>
                    <p>Save products from shop pages or product details and they will appear here.</p>
                    <a href="category.php">Browse Shops</a>
                </div>
            <?php else: ?>
                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                        <?php
                            $product_id = (int)$item['PRODUCT_ID'];
                            $stock = (int)($item['STOCK_QUANTITY'] ?? 0);
                            $status = strtoupper((string)($item['PRODUCT_STATUS'] ?? 'ACTIVE'));
                            $is_available = $status === 'ACTIVE' && $stock > 0;
                            $has_discount = cfo_product_has_discount($item);
                        ?>
                        <article class="wishlist-card" data-wishlist-item="<?= h((string)$product_id) ?>">
                            <a href="product.php?id=<?= h((string)$product_id) ?>" class="wishlist-image">
                                <?php render_product_image(
                                    $item,
                                    'width:100%;height:100%;object-fit:contain;',
                                    'width:100%;height:100%;background:#f8f6f2;display:flex;align-items:center;justify-content:center;',
                                    'color:#cbd5e1;font-size:2.5rem;'
                                ); ?>
                                <span class="wishlist-stock <?= h($is_available ? 'available' : 'unavailable') ?>">
                                    <?= h($is_available ? 'Available' : 'Unavailable') ?>
                                </span>
                            </a>

                            <div class="wishlist-card-body">
                                <div class="wishlist-card-top">
                                    <span><?= h((string)($item['SHOP_NAME'] ?? 'Local trader')) ?></span>
                                    <small>Saved <?= h((string)($item['SAVED_DATE'] ?? 'recently')) ?></small>
                                </div>

                                <h2><a href="product.php?id=<?= h((string)$product_id) ?>"><?= h((string)$item['PRODUCT_NAME']) ?></a></h2>
                                <p><?= h((string)($item['CATEGORY_NAME'] ?? 'Product')) ?></p>

                                <div class="wishlist-price">
                                    <?php if ($has_discount): ?>
                                        <strong>GBP <?= h(number_format(cfo_effective_price_from_row($item), 2)) ?></strong>
                                        <span>GBP <?= h(number_format((float)$item['PRICE'], 2)) ?></span>
                                        <small><?= h(cfo_format_discount_rate(cfo_discount_rate_from_row($item))) ?>% off</small>
                                    <?php else: ?>
                                        <strong>GBP <?= h(number_format((float)$item['PRICE'], 2)) ?></strong>
                                    <?php endif; ?>
                                </div>

                                <div class="wishlist-actions">
                                    <?php if ($is_available): ?>
                                        <form method="post" action="add_to_cart.php">
                                            <?= csrf_field('customer_cart') ?>
                                            <input type="hidden" name="product_id" value="<?= h((string)$product_id) ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="wishlist-cart-btn" data-ajax-cart="1">
                                                <span class="material-icons" aria-hidden="true">shopping_cart</span>
                                                Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="wishlist-cart-btn" disabled>
                                            <span class="material-icons" aria-hidden="true">remove_shopping_cart</span>
                                            Unavailable
                                        </button>
                                    <?php endif; ?>

                                    <form method="post" action="toggle_wishlist.php" class="wishlist-remove-form">
                                        <?= csrf_field('customer_wishlist') ?>
                                        <input type="hidden" name="product_id" value="<?= h((string)$product_id) ?>">
                                        <input type="hidden" name="wishlist_action" value="remove">
                                        <input type="hidden" name="return_to" value="<?= h(cfo_wishlist_current_url('wishlist.php')) ?>">
                                        <button type="submit" class="wishlist-remove-btn" data-ajax-wishlist="1">
                                            <span class="material-icons" aria-hidden="true">delete</span>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
