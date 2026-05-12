SET DEFINE OFF

DECLARE
    v_has_status NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_has_status
    FROM USER_TAB_COLUMNS
    WHERE TABLE_NAME = 'PRODUCT'
      AND COLUMN_NAME = 'STATUS';

    IF v_has_status = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE PRODUCT ADD STATUS VARCHAR2(20) DEFAULT ''ACTIVE''';
        EXECUTE IMMEDIATE 'UPDATE PRODUCT SET STATUS = ''ACTIVE'' WHERE STATUS IS NULL';
    END IF;
END;
/

DECLARE
    FUNCTION category_id_for(
        p_category_name IN CATEGORY.CATEGORY_NAME%TYPE,
        p_description   IN CATEGORY.DESCRIPTION%TYPE
    ) RETURN CATEGORY.CATEGORY_ID%TYPE
    IS
        v_category_id CATEGORY.CATEGORY_ID%TYPE;
    BEGIN
        SELECT CATEGORY_ID
        INTO v_category_id
        FROM CATEGORY
        WHERE UPPER(TRIM(CATEGORY_NAME)) = UPPER(TRIM(p_category_name))
        FETCH FIRST 1 ROW ONLY;

        RETURN v_category_id;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            SELECT NVL(MAX(CATEGORY_ID), 0) + 1
            INTO v_category_id
            FROM CATEGORY;

            INSERT INTO CATEGORY (CATEGORY_ID, CATEGORY_NAME, DESCRIPTION)
            VALUES (v_category_id, p_category_name, p_description);

            RETURN v_category_id;
    END;

    FUNCTION shop_id_for(p_shop_name IN SHOP.SHOP_NAME%TYPE)
    RETURN SHOP.SHOP_ID%TYPE
    IS
        v_shop_id SHOP.SHOP_ID%TYPE;
    BEGIN
        SELECT SHOP_ID
        INTO v_shop_id
        FROM SHOP
        WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(p_shop_name))
        FETCH FIRST 1 ROW ONLY;

        RETURN v_shop_id;
    END;

    PROCEDURE ensure_product(
        p_shop_name     IN SHOP.SHOP_NAME%TYPE,
        p_category_name IN CATEGORY.CATEGORY_NAME%TYPE,
        p_name          IN PRODUCT.PRODUCT_NAME%TYPE,
        p_description   IN PRODUCT.DESCRIPTION%TYPE,
        p_price         IN PRODUCT.PRICE%TYPE,
        p_stock         IN PRODUCT.STOCK_QUANTITY%TYPE,
        p_qty           IN PRODUCT.QUANTITY_PER_ITEM%TYPE,
        p_min           IN PRODUCT.MIN_ORDER%TYPE,
        p_max           IN PRODUCT.MAX_ORDER%TYPE,
        p_allergy       IN PRODUCT.ALLERGY_INFO%TYPE
    )
    IS
        v_shop_id      SHOP.SHOP_ID%TYPE;
        v_category_id  CATEGORY.CATEGORY_ID%TYPE;
        v_same_shop    NUMBER;
        v_other_shop   NUMBER;
    BEGIN
        v_shop_id := shop_id_for(p_shop_name);
        v_category_id := category_id_for(p_category_name, p_category_name || ' products');

        SELECT COUNT(*)
        INTO v_same_shop
        FROM PRODUCT
        WHERE SHOP_ID = v_shop_id
          AND UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));

        IF v_same_shop > 0 THEN
            UPDATE PRODUCT
            SET DESCRIPTION = p_description,
                PRICE = p_price,
                STOCK_QUANTITY = p_stock,
                EXPIRY_DATE = SYSDATE + 30,
                CATEGORY_ID = v_category_id,
                QUANTITY_PER_ITEM = p_qty,
                MIN_ORDER = p_min,
                MAX_ORDER = p_max,
                ALLERGY_INFO = p_allergy,
                STATUS = 'ACTIVE'
            WHERE SHOP_ID = v_shop_id
              AND UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));
        ELSE
            SELECT COUNT(*)
            INTO v_other_shop
            FROM PRODUCT
            WHERE UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));

            IF v_other_shop = 0 THEN
                INSERT INTO PRODUCT (
                    PRODUCT_NAME,
                    DESCRIPTION,
                    PRICE,
                    STOCK_QUANTITY,
                    EXPIRY_DATE,
                    SHOP_ID,
                    CATEGORY_ID,
                    QUANTITY_PER_ITEM,
                    MIN_ORDER,
                    MAX_ORDER,
                    ALLERGY_INFO,
                    STATUS
                ) VALUES (
                    p_name,
                    p_description,
                    p_price,
                    p_stock,
                    SYSDATE + 30,
                    v_shop_id,
                    v_category_id,
                    p_qty,
                    p_min,
                    p_max,
                    p_allergy,
                    'ACTIVE'
                );
            END IF;
        END IF;
    END;
BEGIN
    UPDATE PRODUCT p
    SET PRICE = ROUND(PRICE / 100, 2)
    WHERE PRICE >= 50
      AND EXISTS (
          SELECT 1
          FROM SHOP s
          JOIN TRADER t ON t.USER_ID = s.TRADER_ID
          JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
          WHERE s.SHOP_ID = p.SHOP_ID
            AND NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
            AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
            AND UPPER(TRIM(s.SHOP_NAME)) IN (
                'BAKERY SHOP',
                'BUTCHERS SHOP',
                'CHEESE & CHARCUTERIE SHOP',
                'DELICATESSEN SHOP',
                'FISHMONGER SHOP',
                'GREENGROCER SHOP',
                'POULTRY SHOP',
                'SEAFOOD SHOP',
                'SWEET SHOP',
                'VEGETABLE SHOP'
            )
      );

    UPDATE PRODUCT p
    SET STATUS = 'DISCONTINUED'
    WHERE UPPER(TRIM(p.PRODUCT_NAME)) = 'EGGS'
      AND NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE'
      AND EXISTS (
          SELECT 1
          FROM SHOP s
          WHERE s.SHOP_ID = p.SHOP_ID
            AND UPPER(TRIM(s.SHOP_NAME)) = 'BUTCHERS SHOP'
      );

    ensure_product('Bakery Shop', 'Bakery', 'Rye Tin Loaf', 'Dense rye bread baked in a tin loaf.', 2.70, 40, 1, 1, 8, 'Contains gluten');

    ensure_product('Delicatessen Shop', 'Delicatessen', 'Sun-Dried Tomato Tapenade', 'Rich tomato and olive spread for deli boards.', 3.95, 34, 1, 1, 8, 'May contain nuts');

    ensure_product('Fishmonger Shop', 'Fishmonger', 'Haddock Fillet Fresh', 'Fresh boneless haddock fillet.', 7.40, 30, 1, 1, 8, 'Contains fish');
    ensure_product('Fishmonger Shop', 'Fishmonger', 'Clams Live 1kg', 'Live clams prepared for cooking.', 6.90, 24, 1, 1, 6, 'Contains shellfish');

    ensure_product('Poultry Shop', 'Poultry', 'Chicken Kiev Pair', 'Two prepared chicken kiev portions.', 5.75, 32, 1, 1, 8, 'Contains gluten and dairy');
    ensure_product('Poultry Shop', 'Poultry', 'Turkey Breast Slices', 'Lean sliced turkey breast pack.', 4.95, 36, 1, 1, 8, 'No known allergies');
    ensure_product('Poultry Shop', 'Poultry', 'Corn Fed Chicken Thighs', 'Pack of corn fed chicken thighs.', 5.35, 30, 1, 1, 8, 'No known allergies');
    ensure_product('Poultry Shop', 'Poultry', 'Duck Breast Fillets', 'Two duck breast fillets.', 8.95, 22, 1, 1, 6, 'No known allergies');
    ensure_product('Poultry Shop', 'Poultry', 'Quail Eggs Dozen', 'A dozen quail eggs.', 3.80, 28, 1, 1, 6, 'Contains eggs');
    ensure_product('Poultry Shop', 'Poultry', 'Piri Piri Chicken Skewers', 'Marinated chicken skewers ready to grill.', 5.60, 34, 1, 1, 8, 'No known allergies');
    ensure_product('Poultry Shop', 'Poultry', 'Goose Fat Jar', 'Rendered goose fat for roasting.', 4.50, 26, 1, 1, 6, 'No known allergies');

    ensure_product('Vegetable Shop', 'Vegetables', 'Asparagus Bunch', 'Fresh asparagus bunch.', 3.20, 34, 1, 1, 8, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Aubergine Each', 'Single fresh aubergine.', 1.40, 45, 1, 1, 10, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Green Bean Pack', 'Trimmed green bean pack.', 2.35, 40, 1, 1, 10, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Kale Bunch', 'Curly kale bunch.', 1.75, 38, 1, 1, 10, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Leek Pack', 'Pack of fresh leeks.', 2.10, 36, 1, 1, 10, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Parsnip Bag', 'Bag of fresh parsnips.', 2.20, 36, 1, 1, 10, 'No known allergies');
    ensure_product('Vegetable Shop', 'Vegetables', 'Sweetcorn Cobs', 'Pack of sweetcorn cobs.', 2.50, 34, 1, 1, 10, 'No known allergies');

    ensure_product('Seafood Shop', 'Seafood', 'Cooked Lobster Half', 'Cooked half lobster ready to serve.', 12.95, 16, 1, 1, 4, 'Contains shellfish');
    ensure_product('Seafood Shop', 'Seafood', 'Seafood Paella Mix', 'Mixed seafood pack for paella.', 7.85, 24, 1, 1, 6, 'Contains fish and shellfish');
    ensure_product('Seafood Shop', 'Seafood', 'Tiger Prawns Raw', 'Raw tiger prawns pack.', 8.50, 26, 1, 1, 6, 'Contains shellfish');
    ensure_product('Seafood Shop', 'Seafood', 'Whitebait Pack', 'Prepared whitebait pack.', 4.80, 28, 1, 1, 8, 'Contains fish');
    ensure_product('Seafood Shop', 'Seafood', 'Crab Claws Pack', 'Cooked crab claws pack.', 7.95, 22, 1, 1, 6, 'Contains shellfish');
    ensure_product('Seafood Shop', 'Seafood', 'Oyster Half Dozen', 'Six fresh oysters.', 9.90, 18, 1, 1, 4, 'Contains shellfish');
    ensure_product('Seafood Shop', 'Seafood', 'Smoked Mackerel Pate', 'Smoked mackerel pate pot.', 3.95, 30, 1, 1, 8, 'Contains fish and dairy');

    ensure_product('Sweet Shop', 'Sweets', 'Bakewell Tart Slice', 'Almond Bakewell tart slice.', 3.45, 32, 1, 1, 8, 'Contains gluten, nuts and eggs');
    ensure_product('Sweet Shop', 'Sweets', 'Macaron Selection', 'Assorted macarons box.', 6.50, 24, 1, 1, 6, 'Contains nuts and eggs');
    ensure_product('Sweet Shop', 'Sweets', 'Fudge Pieces Bag', 'Bag of vanilla fudge pieces.', 3.75, 36, 1, 1, 8, 'Contains dairy');
    ensure_product('Sweet Shop', 'Sweets', 'Millionaire Shortbread', 'Caramel shortbread pieces.', 4.10, 34, 1, 1, 8, 'Contains gluten and dairy');
    ensure_product('Sweet Shop', 'Sweets', 'Jam Doughnuts Six', 'Pack of six jam doughnuts.', 5.40, 30, 1, 1, 6, 'Contains gluten');
    ensure_product('Sweet Shop', 'Sweets', 'Victoria Sponge Slice', 'Classic sponge cake slice.', 3.50, 32, 1, 1, 8, 'Contains gluten, eggs and dairy');
    ensure_product('Sweet Shop', 'Sweets', 'Meringue Nests', 'Crisp meringue nests pack.', 3.20, 30, 1, 1, 8, 'Contains eggs');

    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Aged Cheddar Wedge', 'Mature cheddar wedge.', 4.95, 34, 1, 1, 8, 'Contains dairy');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Blue Stilton Portion', 'Creamy blue stilton portion.', 4.60, 30, 1, 1, 8, 'Contains dairy');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Coppa Slices', 'Italian coppa slices pack.', 5.75, 28, 1, 1, 8, 'No known allergies');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Crackers Selection', 'Mixed crackers for cheese boards.', 2.95, 40, 1, 1, 10, 'Contains gluten');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Fig Chutney Jar', 'Sweet fig chutney jar.', 3.40, 34, 1, 1, 8, 'No known allergies');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Goat Cheese Log', 'Soft goat cheese log.', 4.85, 30, 1, 1, 8, 'Contains dairy');
    ensure_product('Cheese & Charcuterie Shop', 'Cheese and Charcuterie', 'Spanish Serrano Ham', 'Sliced Spanish serrano ham.', 6.25, 28, 1, 1, 8, 'No known allergies');

    COMMIT;
END;
/

PROMPT Active product count by visible pilot shop
COLUMN SHOP_NAME FORMAT A32
COLUMN ACTIVE_PRODUCTS FORMAT 999
SELECT s.SHOP_NAME,
       COUNT(CASE WHEN NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE' THEN 1 END) AS ACTIVE_PRODUCTS
FROM SHOP s
JOIN TRADER t ON t.USER_ID = s.TRADER_ID
JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
LEFT JOIN PRODUCT p ON p.SHOP_ID = s.SHOP_ID
WHERE NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
  AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
  AND UPPER(TRIM(s.SHOP_NAME)) IN (
      'BAKERY SHOP',
      'BUTCHERS SHOP',
      'CHEESE & CHARCUTERIE SHOP',
      'DELICATESSEN SHOP',
      'FISHMONGER SHOP',
      'GREENGROCER SHOP',
      'POULTRY SHOP',
      'SEAFOOD SHOP',
      'SWEET SHOP',
      'VEGETABLE SHOP'
  )
GROUP BY s.SHOP_NAME
ORDER BY s.SHOP_NAME;

PROMPT Active duplicate product names across visible pilot shops
COLUMN PRODUCT_NAME FORMAT A40
COLUMN SHOPS FORMAT A100
SELECT UPPER(TRIM(p.PRODUCT_NAME)) AS PRODUCT_NAME,
       COUNT(*) AS CNT,
       LISTAGG(s.SHOP_NAME, ', ') WITHIN GROUP (ORDER BY s.SHOP_NAME) AS SHOPS
FROM PRODUCT p
JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
JOIN TRADER t ON t.USER_ID = s.TRADER_ID
JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
WHERE NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE'
  AND NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
  AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
  AND UPPER(TRIM(s.SHOP_NAME)) IN (
      'BAKERY SHOP',
      'BUTCHERS SHOP',
      'CHEESE & CHARCUTERIE SHOP',
      'DELICATESSEN SHOP',
      'FISHMONGER SHOP',
      'GREENGROCER SHOP',
      'POULTRY SHOP',
      'SEAFOOD SHOP',
      'SWEET SHOP',
      'VEGETABLE SHOP'
  )
GROUP BY UPPER(TRIM(p.PRODUCT_NAME))
HAVING COUNT(*) > 1
ORDER BY PRODUCT_NAME;
