DECLARE
    PROCEDURE rename_trader_account(
        p_user_id IN NUMBER,
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_business_name IN VARCHAR2
    ) IS
    BEGIN
        UPDATE system_user
        SET name = p_name,
            email = p_email,
            status = 'ACTIVE'
        WHERE user_id = p_user_id;

        UPDATE trader
        SET business_name = p_business_name,
            status = 'ACTIVE'
        WHERE user_id = p_user_id;
    END;
BEGIN
    rename_trader_account(4, 'Northern Butchers', 'northern.butchers@cleckhuddesfax.test', 'Northern Butchers');
    rename_trader_account(5, 'Valley Greengrocers', 'valley.greengrocers@cleckhuddesfax.test', 'Valley Greengrocers');
    rename_trader_account(6, 'Harbour Fishmongers', 'harbour.fishmongers@cleckhuddesfax.test', 'Harbour Fishmongers');
    rename_trader_account(7, 'Hearth Bakery', 'hearth.bakery@cleckhuddesfax.test', 'Hearth Bakery');
    rename_trader_account(8, 'Corner Delicatessen', 'corner.delicatessen@cleckhuddesfax.test', 'Corner Delicatessen');

    COMMIT;
END;
/

PROMPT Traditional pilot trader accounts:

COLUMN TRADER_NAME FORMAT A28
COLUMN EMAIL FORMAT A42
COLUMN BUSINESS_NAME FORMAT A28
COLUMN SHOPS FORMAT A70

SELECT su.user_id,
       su.name AS trader_name,
       su.email,
       t.business_name,
       LISTAGG(s.shop_name, ', ') WITHIN GROUP (ORDER BY s.shop_name) AS shops
FROM system_user su
JOIN trader t ON t.user_id = su.user_id
LEFT JOIN shop s ON s.trader_id = t.user_id
WHERE su.user_id IN (4, 5, 6, 7, 8)
GROUP BY su.user_id, su.name, su.email, t.business_name
ORDER BY su.user_id;
