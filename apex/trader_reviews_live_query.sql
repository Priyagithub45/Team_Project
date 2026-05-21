SELECT
    r.review_id,
    p.product_name,
    su.name AS customer_name,
    r.comment_text AS review_body,
    TO_CHAR(r.review_date, 'DD-MON-YYYY') AS review_date,
    CASE r.rating
        WHEN 1 THEN '★☆☆☆☆'
        WHEN 2 THEN '★★☆☆☆'
        WHEN 3 THEN '★★★☆☆'
        WHEN 4 THEN '★★★★☆'
        WHEN 5 THEN '★★★★★'
        ELSE 'No Rating'
    END AS star_rating
FROM review r
JOIN product p
    ON p.product_id = r.product_id
JOIN shop sh
    ON sh.shop_id = p.shop_id
JOIN system_user su
    ON su.user_id = r.customer_id
WHERE sh.trader_id = (
    SELECT t.user_id
    FROM trader t
    JOIN system_user trader_user
        ON trader_user.user_id = t.user_id
    WHERE LOWER(trader_user.email) = LOWER(:APP_USER)
)
AND NVL(r.display_flag, 'Y') = 'Y'
ORDER BY r.review_date DESC;
