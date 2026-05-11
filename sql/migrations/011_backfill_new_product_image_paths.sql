DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'PRODUCT'
      AND COLUMN_NAME = 'IMAGE_PATH';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE PRODUCT ADD IMAGE_PATH VARCHAR2(255)';
    END IF;

    EXECUTE IMMEDIATE q'[
        UPDATE PRODUCT p
        SET p.IMAGE_PATH = 'uploads/products/'
            || TRIM(BOTH '_' FROM REGEXP_REPLACE(LOWER(TRIM(p.PRODUCT_NAME)), '[^a-z0-9]+', '_'))
            || '.jpg'
        WHERE EXISTS (
            SELECT 1
            FROM SHOP s
            JOIN TRADER t ON t.USER_ID = s.TRADER_ID
            JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
            WHERE s.SHOP_ID = p.SHOP_ID
              AND LOWER(su.EMAIL) IN (
                  'dairy.trader@cleckhuddesfax.test',
                  'spice.trader@cleckhuddesfax.test',
                  'organic.trader@cleckhuddesfax.test',
                  'household.trader@cleckhuddesfax.test',
                  'beverage.trader@cleckhuddesfax.test'
              )
        )
    ]';

    COMMIT;
END;
/
