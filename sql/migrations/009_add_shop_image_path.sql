DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'SHOP'
      AND COLUMN_NAME = 'IMAGE_PATH';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE SHOP ADD IMAGE_PATH VARCHAR2(255)';
    END IF;
END;
/

COMMENT ON COLUMN SHOP.IMAGE_PATH IS 'Relative path to public shop image file, for example uploads/shops/shop_19_1712345678.jpg';
