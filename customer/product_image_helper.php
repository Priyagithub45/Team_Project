<?php

function product_image_column_exists($conn): bool
{
    static $exists = null;

    if ($exists !== null) {
        return $exists;
    }

    $stmt = oci_parse(
        $conn,
        "SELECT COUNT(*) AS CNT
         FROM USER_TAB_COLUMNS
         WHERE TABLE_NAME = 'PRODUCT'
           AND COLUMN_NAME = 'IMAGE_PATH'"
    );
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    $exists = ((int)($row['CNT'] ?? 0) > 0);
    return $exists;
}

function product_image_select($conn, string $alias = 'p'): string
{
    return product_image_column_exists($conn)
        ? ", {$alias}.IMAGE_PATH AS IMAGE_PATH"
        : ", NULL AS IMAGE_PATH";
}

function product_image_url_encode_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $parts = array_filter(explode('/', $path), static fn($part) => $part !== '');

    return implode('/', array_map('rawurlencode', $parts));
}

function product_image_asset_src(string $filename): string
{
    if ($filename !== '' && file_exists(__DIR__ . '/assets/images/' . $filename)) {
        return 'assets/images/' . rawurlencode($filename);
    }

    return '';
}

function product_image_normalize(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

    return trim((string)$value);
}

function product_image_keyword_src(array $product): string
{
    $name = product_image_normalize((string)($product['PRODUCT_NAME'] ?? ''));

    $keyword_images = [
        'ribeye' => 'Dry Aged Steak.png',
        'sirloin' => 'Dry Aged Steak.png',
        'steak' => 'Dry Aged Steak.png',
        'beef' => 'Dry Aged Steak.png',
        'mince' => 'Dry Aged Steak.png',
        'chicken' => 'Whole Chicken.png',
        'turkey' => 'Whole Chicken.png',
        'lamb' => 'Lamb Chops.png',
        'mutton' => 'Lamb Chops.png',
        'sausage' => 'Beef Sausages.png',
        'pork' => 'Beef Sausages.png',
        'croissant' => 'Croissants.png',
        'pain au chocolat' => 'Croissants.png',
        'cupcake' => 'Cupcakes.png',
        'cake' => 'Cupcakes.png',
        'muffin' => 'Cupcakes.png',
        'eclair' => 'Cupcakes.png',
        'cookie' => 'Cookies.png',
        'baguette' => 'White Bread.png',
        'bread' => 'White Bread.png',
        'loaf' => 'White Bread.png',
        'ciabatta' => 'White Bread.png',
        'focaccia' => 'White Bread.png',
        'sourdough' => 'White Bread.png',
        'bagel' => 'White Bread.png',
        'tomato' => 'Fresh Tomatoes.png',
        'spinach' => 'Spinach.png',
        'asparagus' => 'Spring Asparagus.png',
        'apple' => 'Fruits Basket.png',
        'banana' => 'Fruits Basket.png',
        'lemon' => 'Fruits Basket.png',
        'avocado' => 'Fruits Basket.png',
        'fruit' => 'Fruits Basket.png',
        'pepper' => 'Fresh Tomatoes.png',
        'broccoli' => 'Spinach.png',
        'carrot' => 'Fresh Tomatoes.png',
        'cucumber' => 'Fresh Tomatoes.png',
        'garlic' => 'Fresh Tomatoes.png',
        'mushroom' => 'Spinach.png',
        'potato' => 'Fresh Tomatoes.png',
        'onion' => 'Fresh Tomatoes.png',
        'salad' => 'Spinach.png',
        'salmon' => 'Salmon Fillet.png',
        'prawn' => 'Tiger Prawns.png',
        'crab' => 'Coastal Crab.png',
        'octopus' => 'Pacific Octopus.png',
        'squid' => 'Pacific Octopus.png',
        'cod' => 'Salmon Fillet.png',
        'mackerel' => 'Salmon Fillet.png',
        'mussel' => 'Tiger Prawns.png',
        'sardine' => 'Salmon Fillet.png',
        'scallop' => 'Tiger Prawns.png',
        'bass' => 'Salmon Fillet.png',
        'haddock' => 'Salmon Fillet.png',
        'trout' => 'Salmon Fillet.png',
        'tuna' => 'Salmon Fillet.png',
        'cheese' => 'Cheese.png',
        'brie' => 'Cheese.png',
        'cheddar' => 'Cheese.png',
        'feta' => 'Cheese.png',
        'mozzarella' => 'Cheese.png',
        'parmigiano' => 'Cheese.png',
        'goat' => 'Cheese.png',
        'butter' => 'Cheese.png',
        'yogurt' => 'Cheese.png',
        'olive' => 'Olives.png',
        'dolmade' => 'Dolmades.png',
        'blini' => 'Blinis.png',
        'ham' => 'Olives.png',
        'chorizo' => 'Olives.png',
        'prosciutto' => 'Olives.png',
        'salami' => 'Olives.png',
        'pate' => 'Olives.png',
    ];

    foreach ($keyword_images as $keyword => $filename) {
        if (str_contains($name, $keyword)) {
            return product_image_asset_src($filename);
        }
    }

    return '';
}

function product_image_category_src(array $product): string
{
    $category = product_image_normalize(
        (string)($product['CATEGORY_NAME'] ?? '') . ' ' . (string)($product['SHOP_NAME'] ?? '')
    );

    $category_images = [
        'butch' => 'butchers.png',
        'green' => 'greengrocers.png',
        'fish' => 'fishmongers.png',
        'baker' => 'bakery.png',
        'deli' => 'delicatessen.png',
    ];

    foreach ($category_images as $keyword => $filename) {
        if (str_contains($category, $keyword)) {
            return product_image_asset_src($filename);
        }
    }

    return '';
}

function product_image_src(array $product): string
{
    $image_path = trim((string)($product['IMAGE_PATH'] ?? ''));

    if ($image_path !== '') {
        $image_path = ltrim(str_replace('\\', '/', $image_path), '/');
        $file_path = dirname(__DIR__) . '/' . $image_path;

        if (file_exists($file_path)) {
            return '../' . product_image_url_encode_path($image_path);
        }
    }

    $name = (string)($product['PRODUCT_NAME'] ?? '');
    if ($name !== '' && file_exists(__DIR__ . '/assets/images/' . $name . '.png')) {
        return 'assets/images/' . rawurlencode($name) . '.png';
    }

    $src = product_image_keyword_src($product);
    if ($src !== '') {
        return $src;
    }

    return product_image_category_src($product);
}

function render_product_image(
    array $product,
    string $img_style = 'width:100%;height:100%;object-fit:cover;',
    string $placeholder_style = 'width:100%;height:100%;background:#f3f3f3;display:flex;align-items:center;justify-content:center;',
    string $icon_style = 'color:#ccc;font-size:2.5rem;'
): void {
    $name = (string)($product['PRODUCT_NAME'] ?? 'Product image');
    $src = product_image_src($product);

    if ($src !== '') {
        echo '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
           . ' alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
           . ' style="' . htmlspecialchars($img_style, ENT_QUOTES, 'UTF-8') . '">';
        return;
    }

    echo '<div style="' . htmlspecialchars($placeholder_style, ENT_QUOTES, 'UTF-8') . '">'
       . '<span class="material-icons" style="' . htmlspecialchars($icon_style, ENT_QUOTES, 'UTF-8') . '">image_not_supported</span>'
       . '</div>';
}
