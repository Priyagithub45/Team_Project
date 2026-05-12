DECLARE
    PROCEDURE assign_shop_to_trader(
        p_shop_name IN VARCHAR2,
        p_trader_email IN VARCHAR2
    ) IS
        v_trader_id system_user.user_id%TYPE;
    BEGIN
        SELECT su.user_id
        INTO v_trader_id
        FROM system_user su
        JOIN trader t ON t.user_id = su.user_id
        WHERE UPPER(TRIM(su.email)) = UPPER(TRIM(p_trader_email))
        FETCH FIRST 1 ROW ONLY;

        UPDATE shop
        SET trader_id = v_trader_id
        WHERE UPPER(TRIM(shop_name)) = UPPER(TRIM(p_shop_name));
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            NULL;
    END;

    PROCEDURE suspend_extra_seed_trader(
        p_email IN VARCHAR2
    ) IS
    BEGIN
        UPDATE trader
        SET status = 'SUSPENDED'
        WHERE user_id IN (
            SELECT user_id
            FROM system_user
            WHERE UPPER(TRIM(email)) = UPPER(TRIM(p_email))
        );

        UPDATE system_user
        SET status = 'SUSPENDED'
        WHERE UPPER(TRIM(email)) = UPPER(TRIM(p_email));
    END;
BEGIN
    /*
      Pilot target:
      - 5 trader login accounts
      - 10 shops
      - 2 shops per pilot trader

      Products remain attached to SHOP_ID, so reassigning SHOP.TRADER_ID moves
      the whole business/catalogue under the correct pilot trader account.
    */

    assign_shop_to_trader('Butchers Shop', 'butcher@gmail.com');
    assign_shop_to_trader('Dairy Shop', 'butcher@gmail.com');

    assign_shop_to_trader('Greengrocer Shop', 'green@gmail.com');
    assign_shop_to_trader('Organic Market', 'green@gmail.com');

    assign_shop_to_trader('Fishmonger Shop', 'fish@gmail.com');
    assign_shop_to_trader('Beverage Shop', 'fish@gmail.com');

    assign_shop_to_trader('Bakery Shop', 'bakery@gmail.com');
    assign_shop_to_trader('Spice Store', 'bakery@gmail.com');

    assign_shop_to_trader('Delicatessen Shop', 'deli@gmail.com');
    assign_shop_to_trader('Household Essentials', 'deli@gmail.com');
    assign_shop_to_trader('Household Essential', 'deli@gmail.com');

    suspend_extra_seed_trader('dairy.trader@cleckhuddesfax.test');
    suspend_extra_seed_trader('organic.trader@cleckhuddesfax.test');
    suspend_extra_seed_trader('beverage.trader@cleckhuddesfax.test');
    suspend_extra_seed_trader('spice.trader@cleckhuddesfax.test');
    suspend_extra_seed_trader('household.trader@cleckhuddesfax.test');

    COMMIT;
END;
/

PROMPT Pilot trader/shop ownership after migration:

COLUMN TRADER_NAME FORMAT A24
COLUMN EMAIL FORMAT A32
COLUMN SHOP_NAME FORMAT A28

SELECT su.user_id AS trader_id,
       su.name AS trader_name,
       su.email,
       COUNT(s.shop_id) AS shop_count,
       LISTAGG(s.shop_name, ', ') WITHIN GROUP (ORDER BY s.shop_name) AS shops
FROM system_user su
JOIN trader t ON t.user_id = su.user_id
JOIN shop s ON s.trader_id = t.user_id
WHERE UPPER(TRIM(su.email)) IN (
    'BUTCHER@GMAIL.COM',
    'GREEN@GMAIL.COM',
    'FISH@GMAIL.COM',
    'BAKERY@GMAIL.COM',
    'DELI@GMAIL.COM'
)
GROUP BY su.user_id, su.name, su.email
ORDER BY su.user_id;
