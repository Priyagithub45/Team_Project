DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_tab_columns
    WHERE table_name = 'SHOP'
      AND column_name = 'DESCRIPTION';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE SHOP ADD DESCRIPTION VARCHAR2(500)';
    END IF;
END;
/

COMMENT ON COLUMN SHOP.DESCRIPTION IS 'Trader-editable public shop/business description.';
