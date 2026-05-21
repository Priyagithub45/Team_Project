<?php

function cfo_discount_rate_sql(string $product_alias = 'p'): string
{
    return "(
        SELECT MAX(d.DISCOUNT_RATE)
        FROM PRODUCT_DISCOUNT pd
        JOIN DISCOUNT d ON d.DISCOUNT_ID = pd.DISCOUNT_ID
        WHERE pd.PRODUCT_ID = {$product_alias}.PRODUCT_ID
          AND NVL(d.DISCOUNT_RATE, 0) > 0
          AND (d.START_DATE IS NULL OR d.START_DATE <= SYSDATE)
          AND (d.END_DATE IS NULL OR d.END_DATE >= SYSDATE)
    )";
}

function cfo_effective_price_sql(string $product_alias = 'p'): string
{
    $rate_sql = cfo_discount_rate_sql($product_alias);
    return "GREATEST(0.01, ROUND({$product_alias}.PRICE * (1 - NVL({$rate_sql}, 0) / 100), 2))";
}

function cfo_discount_select_sql(string $product_alias = 'p'): string
{
    $rate_sql = cfo_discount_rate_sql($product_alias);
    $price_sql = cfo_effective_price_sql($product_alias);

    return ",
           NVL({$rate_sql}, 0) AS DISCOUNT_RATE,
           {$price_sql} AS DISCOUNTED_PRICE";
}

function cfo_effective_price_from_row(array $row): float
{
    if (array_key_exists('DISCOUNTED_PRICE', $row) && $row['DISCOUNTED_PRICE'] !== null) {
        return (float)$row['DISCOUNTED_PRICE'];
    }

    $price = (float)($row['PRICE'] ?? 0);
    $rate = max(0.0, min(99.99, (float)($row['DISCOUNT_RATE'] ?? 0)));

    return max(0.01, round($price * (1 - ($rate / 100)), 2));
}

function cfo_discount_rate_from_row(array $row): float
{
    return max(0.0, (float)($row['DISCOUNT_RATE'] ?? 0));
}

function cfo_product_has_discount(array $row): bool
{
    return cfo_discount_rate_from_row($row) > 0;
}

function cfo_format_discount_rate(float $rate): string
{
    $formatted = number_format($rate, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}
