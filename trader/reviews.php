<?php
require_once 'auth_check.php';
require_once 'product_helpers.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function trader_review_rows($conn, string $sql, array $params = []): array
{
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        return [];
    }

    foreach ($params as $name => &$value) {
        oci_bind_by_name($stmt, $name, $value);
    }
    unset($value);

    if (!oci_execute($stmt)) {
        oci_free_statement($stmt);
        return [];
    }

    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }
    oci_free_statement($stmt);

    return $rows;
}

function trader_review_scalar($conn, string $sql, array $params = [], $default = 0)
{
    $rows = trader_review_rows($conn, $sql, $params);
    if (empty($rows)) {
        return $default;
    }

    return reset($rows[0]) ?? $default;
}

function trader_review_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '<span class="review-stars" aria-label="' . h((string)$rating) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $rating ? 'filled' : '') . '" aria-hidden="true">&#9733;</span>';
    }
    $html .= '</span>';

    return $html;
}

function trader_review_flash_set(string $type, string $message): void
{
    $_SESSION['trader_review_flash'] = ['type' => $type, 'message' => $message];
}

function trader_review_flash_get(): ?array
{
    $flash = $_SESSION['trader_review_flash'] ?? null;
    unset($_SESSION['trader_review_flash']);

    return is_array($flash) ? $flash : null;
}

$trader_id = (int)$current_trader_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post('trader_review_visibility');

    $review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $review_action = strtolower(trim((string)($_POST['review_action'] ?? '')));
    $display_flag = $review_action === 'show' ? 'Y' : ($review_action === 'hide' ? 'N' : '');

    if (!$review_id || $display_flag === '') {
        trader_review_flash_set('error', 'Review action could not be completed.');
    } else {
        $update_sql = "
            UPDATE REVIEW r
               SET r.DISPLAY_FLAG = :display_flag
             WHERE r.REVIEW_ID = :review_id
               AND EXISTS (
                   SELECT 1
                     FROM PRODUCT p
                     JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
                    WHERE p.PRODUCT_ID = r.PRODUCT_ID
                      AND s.TRADER_ID = :trader_id
               )
        ";
        $stmt = oci_parse($conn, $update_sql);
        if ($stmt) {
            oci_bind_by_name($stmt, ':display_flag', $display_flag);
            oci_bind_by_name($stmt, ':review_id', $review_id);
            oci_bind_by_name($stmt, ':trader_id', $trader_id);
            $ok = oci_execute($stmt);
            $affected = $ok ? oci_num_rows($stmt) : 0;
            oci_free_statement($stmt);

            if ($ok && $affected > 0) {
                trader_review_flash_set('success', $display_flag === 'Y' ? 'Review is visible again.' : 'Review has been hidden from customers.');
            } else {
                trader_review_flash_set('error', 'Review was not found for your shops.');
            }
        } else {
            trader_review_flash_set('error', 'Review action could not be prepared.');
        }
    }

    $redirect = 'reviews.php';
    $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
    if ($query !== '') {
        $redirect .= '?' . $query;
    }
    header('Location: ' . $redirect);
    exit;
}

$shop_context = trader_shop_context($conn, $trader_id, true);
$shop_filter_sql = trader_shop_filter_sql($shop_context);
$shop_name = trader_shop_context_label($shop_context, 'All shops');
$account_name = trader_account_label($current_trader);
$flash = trader_review_flash_get();

$visibility = strtoupper(trim((string)($_GET['visibility'] ?? 'VISIBLE')));
$rating_filter = trim((string)($_GET['rating'] ?? 'all'));
$sort = strtoupper(trim((string)($_GET['sort'] ?? 'LATEST')));
$search = trim((string)($_GET['q'] ?? ''));

if (!in_array($visibility, ['VISIBLE', 'HIDDEN', 'ALL'], true)) {
    $visibility = 'VISIBLE';
}
if ($rating_filter !== 'all' && !preg_match('/^[1-5]$/', $rating_filter)) {
    $rating_filter = 'all';
}

$sort_options = [
    'LATEST' => 'r.REVIEW_DATE DESC NULLS LAST, r.REVIEW_ID DESC',
    'RATING_HIGH' => 'r.RATING DESC, r.REVIEW_DATE DESC NULLS LAST',
    'RATING_LOW' => 'r.RATING ASC, r.REVIEW_DATE DESC NULLS LAST',
    'PRODUCT' => 'p.PRODUCT_NAME ASC, r.REVIEW_DATE DESC NULLS LAST',
    'SHOP' => 's.SHOP_NAME ASC, r.REVIEW_DATE DESC NULLS LAST',
];
if (!isset($sort_options[$sort])) {
    $sort = 'LATEST';
}

$base_params = [':trader_id' => $trader_id];
if ($shop_context['selected_shop_id'] !== null) {
    $base_params[':selected_shop_id'] = (int)$shop_context['selected_shop_id'];
}

$where_sql = "
    WHERE s.TRADER_ID = :trader_id
      {$shop_filter_sql}
";
$params = $base_params;

if ($visibility === 'VISIBLE') {
    $where_sql .= " AND NVL(UPPER(r.DISPLAY_FLAG), 'Y') = 'Y'";
} elseif ($visibility === 'HIDDEN') {
    $where_sql .= " AND NVL(UPPER(r.DISPLAY_FLAG), 'Y') <> 'Y'";
}

if ($rating_filter !== 'all') {
    $where_sql .= " AND r.RATING = :rating_filter";
    $params[':rating_filter'] = (int)$rating_filter;
}

if ($search !== '') {
    $where_sql .= "
      AND (
          LOWER(p.PRODUCT_NAME) LIKE :search_term
          OR LOWER(NVL(r.COMMENT_TEXT, '')) LIKE :search_term
          OR LOWER(NVL(su.NAME, '')) LIKE :search_term
          OR LOWER(s.SHOP_NAME) LIKE :search_term
      )
    ";
    $params[':search_term'] = '%' . strtolower($search) . '%';
}

$stats_sql = "
    SELECT COUNT(*) AS TOTAL_REVIEWS,
           NVL(ROUND(AVG(r.RATING), 1), 0) AS AVG_RATING,
           NVL(SUM(CASE WHEN NVL(UPPER(r.DISPLAY_FLAG), 'Y') = 'Y' THEN 1 ELSE 0 END), 0) AS VISIBLE_REVIEWS,
           NVL(SUM(CASE WHEN NVL(UPPER(r.DISPLAY_FLAG), 'Y') <> 'Y' THEN 1 ELSE 0 END), 0) AS HIDDEN_REVIEWS,
           NVL(SUM(CASE WHEN r.REVIEW_DATE >= TRUNC(SYSDATE) - 30 THEN 1 ELSE 0 END), 0) AS RECENT_REVIEWS
      FROM REVIEW r
      JOIN PRODUCT p ON p.PRODUCT_ID = r.PRODUCT_ID
      JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
     WHERE s.TRADER_ID = :trader_id
       {$shop_filter_sql}
";
$stats_rows = trader_review_rows($conn, $stats_sql, $base_params);
$stats = $stats_rows[0] ?? [
    'TOTAL_REVIEWS' => 0,
    'AVG_RATING' => 0,
    'VISIBLE_REVIEWS' => 0,
    'HIDDEN_REVIEWS' => 0,
    'RECENT_REVIEWS' => 0,
];

$distribution_rows = trader_review_rows($conn, "
    SELECT r.RATING, COUNT(*) AS RATING_COUNT
      FROM REVIEW r
      JOIN PRODUCT p ON p.PRODUCT_ID = r.PRODUCT_ID
      JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
     WHERE s.TRADER_ID = :trader_id
       {$shop_filter_sql}
     GROUP BY r.RATING
     ORDER BY r.RATING DESC
", $base_params);
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($distribution_rows as $row) {
    $rating_key = (int)($row['RATING'] ?? 0);
    if (isset($rating_counts[$rating_key])) {
        $rating_counts[$rating_key] = (int)($row['RATING_COUNT'] ?? 0);
    }
}
$max_rating_count = max(1, max($rating_counts));

$product_rows = trader_review_rows($conn, "
    SELECT *
      FROM (
        SELECT p.PRODUCT_NAME,
               s.SHOP_NAME,
               COUNT(*) AS REVIEW_COUNT,
               NVL(ROUND(AVG(r.RATING), 1), 0) AS AVG_RATING,
               NVL(SUM(CASE WHEN NVL(UPPER(r.DISPLAY_FLAG), 'Y') <> 'Y' THEN 1 ELSE 0 END), 0) AS HIDDEN_COUNT
          FROM REVIEW r
          JOIN PRODUCT p ON p.PRODUCT_ID = r.PRODUCT_ID
          JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
         WHERE s.TRADER_ID = :trader_id
           {$shop_filter_sql}
         GROUP BY p.PRODUCT_NAME, s.SHOP_NAME
         ORDER BY REVIEW_COUNT DESC, AVG_RATING DESC, p.PRODUCT_NAME
      )
     WHERE ROWNUM <= 5
", $base_params);

$review_rows = trader_review_rows($conn, "
    SELECT r.REVIEW_ID,
           r.RATING,
           r.COMMENT_TEXT,
           TO_CHAR(r.REVIEW_DATE, 'DD Mon YYYY') AS REVIEW_DATE,
           NVL(r.STATUS, 'APPROVED') AS STATUS,
           NVL(UPPER(r.DISPLAY_FLAG), 'Y') AS DISPLAY_FLAG,
           su.NAME AS CUSTOMER_NAME,
           p.PRODUCT_ID,
           p.PRODUCT_NAME,
           s.SHOP_ID,
           s.SHOP_NAME
      FROM REVIEW r
      JOIN PRODUCT p ON p.PRODUCT_ID = r.PRODUCT_ID
      JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
      LEFT JOIN SYSTEM_USER su ON su.USER_ID = r.CUSTOMER_ID
      {$where_sql}
     ORDER BY {$sort_options[$sort]}
", $params);

$total_reviews = (int)($stats['TOTAL_REVIEWS'] ?? 0);
$visible_reviews = (int)($stats['VISIBLE_REVIEWS'] ?? 0);
$hidden_reviews = (int)($stats['HIDDEN_REVIEWS'] ?? 0);
$recent_reviews = (int)($stats['RECENT_REVIEWS'] ?? 0);
$avg_rating = (float)($stats['AVG_RATING'] ?? 0);
$query_string = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$form_action = 'reviews.php' . ($query_string !== '' ? '?' . $query_string : '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Reviews &mdash; Trader Portal</title>
  <link rel="stylesheet" href="trader.css?v=reviews-20260520">
</head>
<body>
<div class="sidebar">
  <div class="sidebar-brand">
    <img src="logo1.png" alt="Cleckhuddesfax Online Mart" width="36" height="36">
    <h2><?= h($account_name) ?></h2>
    <span class="sidebar-label">Trader Portal</span>
  </div>
  <nav>
    <a href="dashboard.php">Dashboard</a>
    <a href="products.php">Products</a>
    <a href="add_product.php">Add Product</a>
    <a href="reports_daily.php">Daily Orders</a>
    <a href="reports_weekly_finance.php">Weekly Finance</a>
    <a href="reports_monthly_sales.php">Monthly Sales</a>
    <a href="reviews.php" class="active">Reviews</a>
    <a href="profile.php">Profile</a>
  </nav>
  <?php trader_render_shop_switcher($shop_context); ?>
  <div class="sidebar-footer-link">
    <a href="logout.php">Sign Out</a>
  </div>
</div>

<div class="header">Customer Reviews</div>

<div class="main-wrap dashboard-page reviews-page">
  <?php if ($flash): ?>
    <div class="alert alert-<?= h(($flash['type'] ?? '') === 'success' ? 'success' : 'error') ?>"><?= h((string)($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <section class="dashboard-hero report-hero reviews-hero">
    <div>
      <span class="apply-eyebrow">Customer Voice</span>
      <h1><?= h($shop_name) ?></h1>
      <p>Review sentiment, product feedback, and storefront visibility controls for the selected shop view.</p>
    </div>
    <div class="dashboard-hero-meta">
      <span class="status-box status-active"><?= h(number_format($avg_rating, 1)) ?> average</span>
      <small><?= h((string)$total_reviews) ?> lifetime reviews</small>
    </div>
  </section>

  <form method="get" action="reviews.php" class="report-filter-bar finance-filter-bar reviews-filter-bar">
    <div class="field">
      <label for="shop_id">Shop View</label>
      <select id="shop_id" name="shop_id">
        <option value="all"<?= $shop_context['selected_shop_id'] === null ? ' selected' : '' ?>>All shops</option>
        <?php foreach ($shop_context['shops'] as $shop): ?>
          <?php $option_shop_id = (int)$shop['SHOP_ID']; ?>
          <option value="<?= h((string)$option_shop_id) ?>"<?= $shop_context['selected_shop_id'] === $option_shop_id ? ' selected' : '' ?>>
            <?= h((string)$shop['SHOP_NAME']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="visibility">Visibility</label>
      <select id="visibility" name="visibility">
        <option value="VISIBLE"<?= $visibility === 'VISIBLE' ? ' selected' : '' ?>>Visible reviews</option>
        <option value="HIDDEN"<?= $visibility === 'HIDDEN' ? ' selected' : '' ?>>Hidden reviews</option>
        <option value="ALL"<?= $visibility === 'ALL' ? ' selected' : '' ?>>All reviews</option>
      </select>
    </div>
    <div class="field">
      <label for="rating">Rating</label>
      <select id="rating" name="rating">
        <option value="all"<?= $rating_filter === 'all' ? ' selected' : '' ?>>All ratings</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <option value="<?= h((string)$i) ?>"<?= $rating_filter === (string)$i ? ' selected' : '' ?>><?= h((string)$i) ?> stars</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="field">
      <label for="sort">Sort By</label>
      <select id="sort" name="sort">
        <option value="LATEST"<?= $sort === 'LATEST' ? ' selected' : '' ?>>Latest first</option>
        <option value="RATING_HIGH"<?= $sort === 'RATING_HIGH' ? ' selected' : '' ?>>Highest rating</option>
        <option value="RATING_LOW"<?= $sort === 'RATING_LOW' ? ' selected' : '' ?>>Lowest rating</option>
        <option value="PRODUCT"<?= $sort === 'PRODUCT' ? ' selected' : '' ?>>Product name</option>
        <option value="SHOP"<?= $sort === 'SHOP' ? ' selected' : '' ?>>Shop name</option>
      </select>
    </div>
    <div class="field reviews-search-field">
      <label for="q">Search</label>
      <input type="search" id="q" name="q" value="<?= h($search) ?>" placeholder="Product, customer, comment">
    </div>
    <button type="submit" class="btn btn-primary">Apply Filter</button>
    <a href="reviews.php?shop_id=all" class="btn btn-ghost">Reset</a>
  </form>

  <section class="dashboard-metrics report-metrics reviews-metrics" aria-label="Review totals">
    <article class="metric-card">
      <span>Average Rating</span>
      <strong><?= h(number_format($avg_rating, 1)) ?></strong>
      <small>Across selected shop view</small>
    </article>
    <article class="metric-card">
      <span>Visible</span>
      <strong><?= h((string)$visible_reviews) ?></strong>
      <small>Shown on product pages</small>
    </article>
    <article class="metric-card">
      <span>Hidden</span>
      <strong><?= h((string)$hidden_reviews) ?></strong>
      <small>Removed from customer view</small>
    </article>
    <article class="metric-card">
      <span>Last 30 Days</span>
      <strong><?= h((string)$recent_reviews) ?></strong>
      <small>Fresh feedback volume</small>
    </article>
  </section>

  <section class="dashboard-grid report-two-column-grid reviews-insight-grid">
    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Sentiment</span>
          <h2>Rating Distribution</h2>
        </div>
      </div>
      <div class="rating-breakdown-list">
        <?php foreach ($rating_counts as $rating => $count): ?>
          <?php $width = (int)round(($count / $max_rating_count) * 100); ?>
          <article>
            <strong><?= h((string)$rating) ?> stars</strong>
            <div class="rating-track"><span style="width: <?= h((string)$width) ?>%"></span></div>
            <small><?= h((string)$count) ?></small>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="dashboard-panel">
      <div class="panel-heading">
        <div>
          <span class="apply-eyebrow">Products</span>
          <h2>Most Reviewed</h2>
        </div>
      </div>
      <?php if (empty($product_rows)): ?>
        <div class="empty-state">No product review trends yet.</div>
      <?php else: ?>
        <div class="review-product-list">
          <?php foreach ($product_rows as $product): ?>
            <article>
              <div>
                <strong><?= h((string)$product['PRODUCT_NAME']) ?></strong>
                <span><?= h((string)$product['SHOP_NAME']) ?></span>
              </div>
              <div>
                <b><?= h(number_format((float)$product['AVG_RATING'], 1)) ?></b>
                <small><?= h((string)$product['REVIEW_COUNT']) ?> reviews</small>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="dashboard-panel reviews-list-panel">
    <div class="panel-heading">
      <div>
        <span class="apply-eyebrow">Moderation</span>
        <h2>Review Inbox</h2>
      </div>
      <span class="panel-link"><?= h((string)count($review_rows)) ?> matching</span>
    </div>

    <?php if (empty($review_rows)): ?>
      <div class="empty-state">No reviews match this view.</div>
    <?php else: ?>
      <div class="review-card-list">
        <?php foreach ($review_rows as $review): ?>
          <?php
            $is_visible = strtoupper((string)$review['DISPLAY_FLAG']) === 'Y';
            $action_label = $is_visible ? 'Hide Review' : 'Show Review';
            $action_value = $is_visible ? 'hide' : 'show';
            $status_class = $is_visible ? 'status-active' : 'status-low';
          ?>
          <article class="review-card<?= $is_visible ? '' : ' review-card-hidden' ?>">
            <div class="review-card-main">
              <div class="review-card-topline">
                <?= trader_review_stars((int)$review['RATING']) ?>
                <span class="status-box <?= h($status_class) ?>"><?= h($is_visible ? 'Visible' : 'Hidden') ?></span>
              </div>
              <p class="review-card-text"><?= h(trim((string)$review['COMMENT_TEXT']) !== '' ? (string)$review['COMMENT_TEXT'] : 'No written comment.') ?></p>
              <div class="review-card-meta">
                <span><?= h((string)($review['CUSTOMER_NAME'] ?? 'Customer')) ?></span>
                <span><?= h((string)$review['REVIEW_DATE']) ?></span>
                <span><?= h((string)$review['STATUS']) ?></span>
              </div>
            </div>
            <div class="review-card-side">
              <strong><?= h((string)$review['PRODUCT_NAME']) ?></strong>
              <span><?= h((string)$review['SHOP_NAME']) ?></span>
              <form method="post" action="<?= h($form_action) ?>" onsubmit="return confirm('<?= h($action_label) ?>?');">
                <?= csrf_field('trader_review_visibility') ?>
                <input type="hidden" name="review_id" value="<?= h((string)$review['REVIEW_ID']) ?>">
                <input type="hidden" name="review_action" value="<?= h($action_value) ?>">
                <button type="submit" class="action-btn <?= h($is_visible ? 'btn-delete' : 'btn-edit') ?>"><?= h($action_label) ?></button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php include 'footer.php'; ?>

<script src="trader.js"></script>
</body>
</html>
