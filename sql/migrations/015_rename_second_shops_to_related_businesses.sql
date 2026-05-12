SET DEFINE OFF

DECLARE
    v_count NUMBER;
    v_poultry_category NUMBER;
    v_vegetable_category NUMBER;
    v_seafood_category NUMBER;
    v_sweets_category NUMBER;
    v_charcuterie_category NUMBER;

    FUNCTION get_or_create_category(
        p_name IN VARCHAR2,
        p_description IN VARCHAR2
    ) RETURN NUMBER IS
        v_category_id CATEGORY.CATEGORY_ID%TYPE;
    BEGIN
        SELECT CATEGORY_ID
        INTO v_category_id
        FROM CATEGORY
        WHERE UPPER(TRIM(CATEGORY_NAME)) = UPPER(TRIM(p_name))
        FETCH FIRST 1 ROW ONLY;

        RETURN v_category_id;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            SELECT NVL(MAX(CATEGORY_ID), 0) + 1
            INTO v_category_id
            FROM CATEGORY;

            INSERT INTO CATEGORY (CATEGORY_ID, CATEGORY_NAME, DESCRIPTION)
            VALUES (v_category_id, p_name, SUBSTR(p_description, 1, 200));

            RETURN v_category_id;
    END;

    FUNCTION shop_id_for_name(
        p_shop_name IN VARCHAR2
    ) RETURN NUMBER IS
        v_shop_id SHOP.SHOP_ID%TYPE;
    BEGIN
        SELECT SHOP_ID
        INTO v_shop_id
        FROM SHOP
        WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(p_shop_name))
        FETCH FIRST 1 ROW ONLY;

        RETURN v_shop_id;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            RETURN NULL;
    END;

    FUNCTION next_product_id RETURN NUMBER IS
        v_product_id PRODUCT.PRODUCT_ID%TYPE;
        v_max_product_id PRODUCT.PRODUCT_ID%TYPE;
    BEGIN
        SELECT NVL(MAX(PRODUCT_ID), 0)
        INTO v_max_product_id
        FROM PRODUCT;

        LOOP
            SELECT PRODUCTS_SEQ.NEXTVAL
            INTO v_product_id
            FROM DUAL;

            EXIT WHEN v_product_id > v_max_product_id;
        END LOOP;

        RETURN v_product_id;
    END;

    PROCEDURE rename_shop(
        p_old_name IN VARCHAR2,
        p_new_name IN VARCHAR2,
        p_location IN VARCHAR2
    ) IS
    BEGIN
        UPDATE SHOP
        SET SHOP_NAME = p_new_name,
            LOCATION = p_location
        WHERE UPPER(TRIM(SHOP_NAME)) = UPPER(TRIM(p_old_name));
    END;

    PROCEDURE discontinue_shop_products(
        p_shop_name IN VARCHAR2
    ) IS
        v_shop_id SHOP.SHOP_ID%TYPE;
    BEGIN
        v_shop_id := shop_id_for_name(p_shop_name);
        IF v_shop_id IS NULL THEN
            RETURN;
        END IF;

        UPDATE PRODUCT
        SET STATUS = 'DISCONTINUED'
        WHERE SHOP_ID = v_shop_id;
    END;

    PROCEDURE add_product(
        p_shop_name IN VARCHAR2,
        p_category_id IN NUMBER,
        p_name IN VARCHAR2,
        p_description IN VARCHAR2,
        p_price IN NUMBER,
        p_stock IN NUMBER
    ) IS
        v_shop_id PRODUCT.SHOP_ID%TYPE;
        v_product_id PRODUCT.PRODUCT_ID%TYPE;
        v_count NUMBER;
    BEGIN
        v_shop_id := shop_id_for_name(p_shop_name);
        IF v_shop_id IS NULL THEN
            RETURN;
        END IF;

        SELECT COUNT(*)
        INTO v_count
        FROM PRODUCT
        WHERE SHOP_ID = v_shop_id
          AND UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));

        IF v_count = 0 THEN
            v_product_id := next_product_id;

            INSERT INTO PRODUCT (
                PRODUCT_ID,
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
                v_product_id,
                p_name,
                SUBSTR(p_description, 1, 200),
                p_price,
                p_stock,
                SYSDATE + 120,
                v_shop_id,
                p_category_id,
                1,
                1,
                20,
                NULL,
                'ACTIVE'
            );
        ELSE
            UPDATE PRODUCT
            SET DESCRIPTION = SUBSTR(p_description, 1, 200),
                PRICE = p_price,
                STOCK_QUANTITY = p_stock,
                CATEGORY_ID = p_category_id,
                EXPIRY_DATE = SYSDATE + 120,
                STATUS = 'ACTIVE'
            WHERE SHOP_ID = v_shop_id
              AND UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));
        END IF;
    END;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_tab_columns
    WHERE table_name = 'PRODUCT'
      AND column_name = 'STATUS';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE PRODUCT ADD STATUS VARCHAR2(20) DEFAULT ''ACTIVE''';
    END IF;

    v_poultry_category := get_or_create_category('Poultry', 'Chicken, turkey, duck, eggs, and prepared poultry cuts.');
    v_vegetable_category := get_or_create_category('Vegetables', 'Fresh local vegetables, roots, greens, and salad produce.');
    v_seafood_category := get_or_create_category('Seafood', 'Shellfish, smoked fish, prepared seafood, and coastal specials.');
    v_sweets_category := get_or_create_category('Sweets', 'Cakes, pastries, desserts, chocolates, and sweet bakery items.');
    v_charcuterie_category := get_or_create_category('Cheese and Charcuterie', 'Cheese boards, cured meats, olives, antipasti, and deli platters.');

    rename_shop('Dairy Shop', 'Poultry Shop', 'Poultry Market Lane');
    rename_shop('Organic Market', 'Vegetable Shop', 'Vegetable Market Yard');
    rename_shop('Beverage Shop', 'Seafood Shop', 'Seafood Market Corner');
    rename_shop('Spice Store', 'Sweet Shop', 'Sweet Market Street');
    rename_shop('Household Essential', 'Cheese & Charcuterie Shop', 'Deli Market Row');
    rename_shop('Household Essentials', 'Cheese & Charcuterie Shop', 'Deli Market Row');

    discontinue_shop_products('Poultry Shop');
    discontinue_shop_products('Vegetable Shop');
    discontinue_shop_products('Seafood Shop');
    discontinue_shop_products('Sweet Shop');
    discontinue_shop_products('Cheese & Charcuterie Shop');

    add_product('Poultry Shop', v_poultry_category, 'Free Range Chicken Breast', 'Boneless free range chicken breast fillets for roasting or grilling.', 5.95, 55);
    add_product('Poultry Shop', v_poultry_category, 'Whole Roasting Chicken', 'Fresh whole chicken prepared for weekend roasting.', 8.50, 35);
    add_product('Poultry Shop', v_poultry_category, 'Chicken Thigh Pack', 'Juicy chicken thighs packed for family meals.', 4.75, 60);
    add_product('Poultry Shop', v_poultry_category, 'Turkey Mince Lean', 'Lean turkey mince for burgers, sauces, and healthy meals.', 5.25, 45);
    add_product('Poultry Shop', v_poultry_category, 'Duck Legs Pair', 'Fresh duck legs pair for slow cooking and roasting.', 6.80, 28);
    add_product('Poultry Shop', v_poultry_category, 'Chicken Drumsticks', 'Fresh chicken drumsticks for grilling and baking.', 3.95, 70);
    add_product('Poultry Shop', v_poultry_category, 'Smoked Chicken Slices', 'Ready sliced smoked chicken for sandwiches and salads.', 4.60, 42);
    add_product('Poultry Shop', v_poultry_category, 'Free Range Eggs Dozen', 'Dozen free range eggs from local poultry suppliers.', 3.40, 80);

    add_product('Vegetable Shop', v_vegetable_category, 'Carrot Bunch', 'Fresh carrot bunch with crisp sweet roots.', 1.85, 90);
    add_product('Vegetable Shop', v_vegetable_category, 'Broccoli Head', 'Fresh broccoli head for steaming, roasting, and stir frying.', 2.20, 70);
    add_product('Vegetable Shop', v_vegetable_category, 'Cauliflower Head', 'Large cauliflower head for sides, curries, and roasting.', 2.60, 60);
    add_product('Vegetable Shop', v_vegetable_category, 'Spinach Bag', 'Washed spinach leaves for salads and cooking.', 2.10, 75);
    add_product('Vegetable Shop', v_vegetable_category, 'Mixed Salad Leaves', 'Mixed salad leaves packed fresh for lunches.', 2.45, 80);
    add_product('Vegetable Shop', v_vegetable_category, 'Red Onion Net', 'Net of red onions for everyday cooking.', 1.95, 85);
    add_product('Vegetable Shop', v_vegetable_category, 'Courgette Pack', 'Fresh courgettes for grilling, baking, and stir frying.', 2.35, 65);
    add_product('Vegetable Shop', v_vegetable_category, 'Bell Pepper Trio', 'Three mixed bell peppers with bright colour and crunch.', 2.90, 70);

    add_product('Seafood Shop', v_seafood_category, 'King Prawns Pack', 'Prepared king prawns pack for pasta, salads, and curries.', 6.95, 45);
    add_product('Seafood Shop', v_seafood_category, 'Smoked Salmon Slices', 'Thin smoked salmon slices for breakfast and canapes.', 7.50, 36);
    add_product('Seafood Shop', v_seafood_category, 'Mussel Pot', 'Fresh mussels pot ready for steaming with herbs.', 5.40, 38);
    add_product('Seafood Shop', v_seafood_category, 'Scallops Half Dozen', 'Half dozen fresh scallops for pan searing.', 9.75, 24);
    add_product('Seafood Shop', v_seafood_category, 'Crab Meat Tub', 'Prepared crab meat tub for salads and sandwiches.', 8.20, 30);
    add_product('Seafood Shop', v_seafood_category, 'Calamari Rings', 'Prepared calamari rings for frying or grilling.', 5.95, 42);
    add_product('Seafood Shop', v_seafood_category, 'Seafood Chowder Mix', 'Mixed seafood selection for chowder and soups.', 6.60, 35);
    add_product('Seafood Shop', v_seafood_category, 'Marinated Anchovies', 'Marinated anchovy fillets for antipasti and salads.', 4.30, 50);

    add_product('Sweet Shop', v_sweets_category, 'Chocolate Brownie Box', 'Rich chocolate brownies packed in a share box.', 4.95, 50);
    add_product('Sweet Shop', v_sweets_category, 'Vanilla Cupcakes Six', 'Six vanilla cupcakes with buttercream topping.', 5.50, 45);
    add_product('Sweet Shop', v_sweets_category, 'Fruit Tart Slice', 'Sweet pastry tart slice topped with seasonal fruit.', 3.25, 55);
    add_product('Sweet Shop', v_sweets_category, 'Cinnamon Rolls Pack', 'Soft cinnamon rolls with sweet glaze.', 4.75, 48);
    add_product('Sweet Shop', v_sweets_category, 'Lemon Drizzle Cake', 'Moist lemon drizzle loaf cake with citrus icing.', 5.95, 32);
    add_product('Sweet Shop', v_sweets_category, 'Chocolate Eclairs', 'Fresh chocolate eclairs filled with cream.', 4.20, 40);
    add_product('Sweet Shop', v_sweets_category, 'Shortbread Biscuits', 'Buttery shortbread biscuits baked in small batches.', 3.60, 65);
    add_product('Sweet Shop', v_sweets_category, 'Raspberry Cheesecake Pot', 'Individual raspberry cheesecake dessert pot.', 3.75, 44);

    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Local Cheese Board', 'Selection of local cheeses for sharing boards.', 9.95, 28);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Cured Ham Slices', 'Thin cured ham slices for antipasti and sandwiches.', 5.95, 45);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Salami Selection', 'Mixed salami slices with mild and spicy varieties.', 6.40, 38);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Marinated Olives Tub', 'Mixed olives marinated with herbs and garlic.', 3.95, 55);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Antipasti Platter', 'Prepared antipasti platter with meats, cheese, and olives.', 11.50, 24);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Brie Wheel Small', 'Small creamy brie wheel for cheese boards.', 4.80, 42);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Pickled Cornichons Jar', 'Crunchy cornichons jar for deli platters.', 2.95, 60);
    add_product('Cheese & Charcuterie Shop', v_charcuterie_category, 'Pate Pot', 'Smooth pate pot for crackers and bread.', 4.30, 44);

    COMMIT;
END;
/

PROMPT Related second-shop mapping:

COLUMN OWNER FORMAT A28
COLUMN SHOP_NAME FORMAT A30
COLUMN ACTIVE_PRODUCTS FORMAT 999

SELECT su.name AS owner,
       s.shop_name,
       COUNT(CASE WHEN NVL(UPPER(p.status), 'ACTIVE') = 'ACTIVE' THEN 1 END) AS active_products
FROM shop s
JOIN system_user su ON su.user_id = s.trader_id
LEFT JOIN product p ON p.shop_id = s.shop_id
WHERE s.shop_name IN (
    'Poultry Shop',
    'Vegetable Shop',
    'Seafood Shop',
    'Sweet Shop',
    'Cheese & Charcuterie Shop'
)
GROUP BY su.name, s.shop_name
ORDER BY su.name, s.shop_name;
