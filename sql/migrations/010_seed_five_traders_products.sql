DECLARE
    v_count NUMBER;
    v_next NUMBER;
    v_dairy_category NUMBER;
    v_spice_category NUMBER;
    v_organic_category NUMBER;
    v_household_category NUMBER;
    v_beverage_category NUMBER;
    v_shop_id NUMBER;

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

            INSERT INTO CATEGORY (
                CATEGORY_ID,
                CATEGORY_NAME,
                DESCRIPTION
            ) VALUES (
                v_category_id,
                p_name,
                SUBSTR(p_description, 1, 200)
            );

            RETURN v_category_id;
    END;

    FUNCTION ensure_trader(
        p_name IN VARCHAR2,
        p_email IN VARCHAR2,
        p_business_name IN VARCHAR2,
        p_license_no IN VARCHAR2,
        p_shop_location IN VARCHAR2,
        p_shop_contact IN VARCHAR2
    ) RETURN NUMBER IS
        v_user_id SYSTEM_USER.USER_ID%TYPE;
        v_shop_id SHOP.SHOP_ID%TYPE;
    BEGIN
        BEGIN
            SELECT USER_ID
            INTO v_user_id
            FROM SYSTEM_USER
            WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(p_email));
        EXCEPTION
            WHEN NO_DATA_FOUND THEN
                SELECT SYSTEM_USER_SEQ.NEXTVAL
                INTO v_user_id
                FROM DUAL;

                INSERT INTO SYSTEM_USER (
                    USER_ID,
                    NAME,
                    EMAIL,
                    PASSWORD,
                    PHONE_NO,
                    ADDRESS,
                    CREATED_AT,
                    STATUS
                ) VALUES (
                    v_user_id,
                    p_name,
                    p_email,
                    'Trader@123',
                    p_shop_contact,
                    p_shop_location,
                    SYSDATE,
                    'ACTIVE'
                );
        END;

        MERGE INTO TRADER t
        USING (
            SELECT v_user_id AS USER_ID,
                   p_business_name AS BUSINESS_NAME,
                   p_license_no AS LICENSE_NO
            FROM DUAL
        ) src
        ON (t.USER_ID = src.USER_ID)
        WHEN MATCHED THEN
            UPDATE SET t.BUSINESS_NAME = src.BUSINESS_NAME,
                       t.LICENSE_NO = src.LICENSE_NO,
                       t.STATUS = 'ACTIVE'
        WHEN NOT MATCHED THEN
            INSERT (USER_ID, BUSINESS_NAME, LICENSE_NO, STATUS)
            VALUES (src.USER_ID, src.BUSINESS_NAME, src.LICENSE_NO, 'ACTIVE');

        BEGIN
            SELECT SHOP_ID
            INTO v_shop_id
            FROM SHOP
            WHERE TRADER_ID = v_user_id
            FETCH FIRST 1 ROW ONLY;

            UPDATE SHOP
            SET SHOP_NAME = p_business_name,
                LOCATION = p_shop_location,
                CONTACT_NO = p_shop_contact
            WHERE SHOP_ID = v_shop_id;
        EXCEPTION
            WHEN NO_DATA_FOUND THEN
                EXECUTE IMMEDIATE 'SELECT SHOP_SEQ.NEXTVAL FROM DUAL' INTO v_shop_id;

                INSERT INTO SHOP (
                    SHOP_ID,
                    SHOP_NAME,
                    LOCATION,
                    CONTACT_NO,
                    TRADER_ID
                ) VALUES (
                    v_shop_id,
                    p_business_name,
                    p_shop_location,
                    p_shop_contact,
                    v_user_id
                )
                RETURNING SHOP_ID INTO v_shop_id;
        END;

        RETURN v_shop_id;
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

    PROCEDURE add_product(
        p_shop_id IN NUMBER,
        p_category_id IN NUMBER,
        p_name IN VARCHAR2,
        p_description IN VARCHAR2,
        p_price IN NUMBER,
        p_stock IN NUMBER
    ) IS
        v_count NUMBER;
        v_product_id PRODUCT.PRODUCT_ID%TYPE;
    BEGIN
        SELECT COUNT(*)
        INTO v_count
        FROM PRODUCT
        WHERE SHOP_ID = p_shop_id
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
                ALLERGY_INFO
            ) VALUES (
                v_product_id,
                p_name,
                SUBSTR(p_description, 1, 200),
                p_price,
                p_stock,
                SYSDATE + 180,
                p_shop_id,
                p_category_id,
                1,
                1,
                20,
                NULL
            );
        ELSE
            UPDATE PRODUCT
            SET DESCRIPTION = SUBSTR(p_description, 1, 200),
                PRICE = p_price,
                STOCK_QUANTITY = p_stock,
                CATEGORY_ID = p_category_id
            WHERE SHOP_ID = p_shop_id
              AND UPPER(TRIM(PRODUCT_NAME)) = UPPER(TRIM(p_name));
        END IF;
    END;

BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM USER_SEQUENCES
    WHERE SEQUENCE_NAME = 'SHOP_SEQ';

    IF v_count = 0 THEN
        SELECT NVL(MAX(SHOP_ID), 0) + 1
        INTO v_next
        FROM SHOP;

        EXECUTE IMMEDIATE 'CREATE SEQUENCE SHOP_SEQ START WITH ' || v_next || ' INCREMENT BY 1 NOCACHE';
    END IF;

    v_dairy_category := get_or_create_category('Dairy', 'Milk, yogurt, cheese, cream, and chilled dairy products.');
    v_spice_category := get_or_create_category('Spices', 'Spices, herbs, seeds, pulses, and pantry seasonings.');
    v_organic_category := get_or_create_category('Organic', 'Organic fresh produce and health-focused pantry products.');
    v_household_category := get_or_create_category('Household', 'Kitchen, cleaning, storage, and household essentials.');
    v_beverage_category := get_or_create_category('Beverages', 'Juices, teas, coffees, waters, and refreshing drinks.');

    v_shop_id := ensure_trader('Dairy Shop Trader', 'dairy.trader@cleckhuddesfax.test', 'Dairy Shop', 'LICD001', 'Dairy Market Lane', '07111 000101');
    add_product(v_shop_id, v_dairy_category, 'Fresh Buttermilk Bottle', 'Fresh cultured buttermilk bottle for cooking, baking, or drinking.', 2.49, 60);
    add_product(v_shop_id, v_dairy_category, 'Strawberry Yogurt Cups', 'Creamy strawberry yogurt cups packed for quick snacks.', 3.25, 70);
    add_product(v_shop_id, v_dairy_category, 'Mango Lassi Drink', 'Smooth mango lassi drink with a rich dairy base.', 2.99, 55);
    add_product(v_shop_id, v_dairy_category, 'Halloumi Cheese Block', 'Firm halloumi cheese block suitable for grilling.', 4.75, 40);
    add_product(v_shop_id, v_dairy_category, 'Ricotta Cheese Tub', 'Soft ricotta cheese tub for pasta, desserts, and spreads.', 3.95, 45);
    add_product(v_shop_id, v_dairy_category, 'Blue Cheese Wedge', 'Bold blue cheese wedge with a creamy texture.', 4.50, 35);
    add_product(v_shop_id, v_dairy_category, 'Goat Milk Fresh', 'Fresh goat milk bottle from local dairy supply.', 3.20, 50);
    add_product(v_shop_id, v_dairy_category, 'Kefir Probiotic Drink', 'Tangy kefir probiotic drink for everyday wellness.', 3.10, 48);
    add_product(v_shop_id, v_dairy_category, 'Custard Dessert Cups', 'Ready-to-serve custard dessert cups.', 2.85, 65);
    add_product(v_shop_id, v_dairy_category, 'Whipping Cream Premium', 'Premium whipping cream for desserts and cooking.', 3.60, 55);
    add_product(v_shop_id, v_dairy_category, 'Condensed Milk Can', 'Sweet condensed milk can for desserts and drinks.', 2.35, 80);
    add_product(v_shop_id, v_dairy_category, 'Milk Powder Full Cream', 'Full cream milk powder pack for pantry storage.', 5.95, 42);
    add_product(v_shop_id, v_dairy_category, 'Mascarpone Cheese Tub', 'Rich mascarpone cheese tub for cakes and desserts.', 4.95, 35);
    add_product(v_shop_id, v_dairy_category, 'Rice Pudding Dairy Pot', 'Creamy rice pudding dairy pot.', 2.75, 58);
    add_product(v_shop_id, v_dairy_category, 'Salted Caramel Ice Cream', 'Salted caramel dairy ice cream tub.', 5.50, 38);

    v_shop_id := ensure_trader('Spice Store Trader', 'spice.trader@cleckhuddesfax.test', 'Spice Store', 'LICS001', 'Spice Market Street', '07111 000102');
    add_product(v_shop_id, v_spice_category, 'Saffron Threads Premium', 'Premium saffron threads for rice, desserts, and sauces.', 8.95, 25);
    add_product(v_shop_id, v_spice_category, 'Star Anise Whole', 'Whole star anise pods with warm aromatic flavour.', 2.20, 75);
    add_product(v_shop_id, v_spice_category, 'Nutmeg Ground', 'Ground nutmeg spice for baking and savoury cooking.', 1.95, 80);
    add_product(v_shop_id, v_spice_category, 'White Pepper Powder', 'Fine white pepper powder for soups and sauces.', 2.10, 70);
    add_product(v_shop_id, v_spice_category, 'Cajun Spice Blend', 'Smoky Cajun spice blend for meat, vegetables, and fries.', 2.75, 65);
    add_product(v_shop_id, v_spice_category, 'Italian Herb Mix', 'Dried Italian herb mix for pasta, pizza, and sauces.', 2.30, 75);
    add_product(v_shop_id, v_spice_category, 'Dried Oregano Leaves', 'Fragrant dried oregano leaves for Mediterranean cooking.', 1.85, 85);
    add_product(v_shop_id, v_spice_category, 'Sesame Seeds White', 'White sesame seeds for toppings, baking, and stir fries.', 2.15, 90);
    add_product(v_shop_id, v_spice_category, 'Bay Leaves Whole', 'Whole bay leaves for soups, stews, and stocks.', 1.70, 95);
    add_product(v_shop_id, v_spice_category, 'Tamarind Paste', 'Tangy tamarind paste for curries, chutneys, and marinades.', 2.80, 60);
    add_product(v_shop_id, v_spice_category, 'Curry Powder Mild', 'Mild curry powder blend for everyday cooking.', 2.40, 80);
    add_product(v_shop_id, v_spice_category, 'Garlic Powder', 'Fine garlic powder for seasoning and marinades.', 1.90, 90);
    add_product(v_shop_id, v_spice_category, 'Onion Powder', 'Onion powder for dry rubs, sauces, and snacks.', 1.90, 90);
    add_product(v_shop_id, v_spice_category, 'Brown Lentils Pack', 'Dried brown lentils pack for soups and curries.', 2.60, 70);
    add_product(v_shop_id, v_spice_category, 'Chickpeas Dried Pack', 'Dried chickpeas pack for hummus, salads, and stews.', 2.50, 70);

    v_shop_id := ensure_trader('Organic Market Trader', 'organic.trader@cleckhuddesfax.test', 'Organic Market', 'LICO001', 'Organic Market Yard', '07111 000103');
    add_product(v_shop_id, v_organic_category, 'Organic Kale Bunch', 'Fresh organic kale bunch for salads and cooking.', 2.25, 55);
    add_product(v_shop_id, v_organic_category, 'Organic Beetroot Pack', 'Organic beetroot pack with earthy sweet flavour.', 2.80, 50);
    add_product(v_shop_id, v_organic_category, 'Organic Sweet Potatoes', 'Organic sweet potatoes with natural sweetness.', 3.25, 60);
    add_product(v_shop_id, v_organic_category, 'Organic Blueberries Punnet', 'Fresh organic blueberries punnet.', 4.10, 45);
    add_product(v_shop_id, v_organic_category, 'Organic Pears Pack', 'Organic pears pack for snacks and desserts.', 3.60, 52);
    add_product(v_shop_id, v_organic_category, 'Organic Ginger Root', 'Organic ginger root for cooking and drinks.', 2.35, 65);
    add_product(v_shop_id, v_organic_category, 'Organic Turmeric Root', 'Fresh organic turmeric root for juices and cooking.', 2.50, 62);
    add_product(v_shop_id, v_organic_category, 'Organic Pumpkin Whole', 'Whole organic pumpkin for soups and roasting.', 4.95, 30);
    add_product(v_shop_id, v_organic_category, 'Organic Zucchini Green', 'Green organic zucchini for grilling and stir frying.', 2.70, 55);
    add_product(v_shop_id, v_organic_category, 'Organic Cauliflower Head', 'Organic cauliflower head for roasting and sides.', 3.20, 45);
    add_product(v_shop_id, v_organic_category, 'Organic Green Beans', 'Fresh organic green beans pack.', 3.15, 55);
    add_product(v_shop_id, v_organic_category, 'Organic Walnuts Raw', 'Raw organic walnuts for baking and snacking.', 5.80, 40);
    add_product(v_shop_id, v_organic_category, 'Organic Chia Seeds', 'Organic chia seeds pack for breakfast and smoothies.', 4.25, 50);
    add_product(v_shop_id, v_organic_category, 'Organic Coconut Oil', 'Organic coconut oil jar for cooking and baking.', 5.20, 42);
    add_product(v_shop_id, v_organic_category, 'Organic Oatmeal Pack', 'Organic oatmeal pack for breakfast and baking.', 3.45, 58);

    v_shop_id := ensure_trader('Household Essentials Trader', 'household.trader@cleckhuddesfax.test', 'Household Essentials Shop', 'LICH001', 'Household Market Row', '07111 000104');
    add_product(v_shop_id, v_household_category, 'Bamboo Kitchen Towels', 'Reusable bamboo kitchen towels for everyday cleaning.', 4.25, 55);
    add_product(v_shop_id, v_household_category, 'Reusable Food Wraps', 'Reusable food wraps for packed lunches and leftovers.', 5.75, 45);
    add_product(v_shop_id, v_household_category, 'Organic Dish Soap', 'Organic dish soap with a gentle fresh scent.', 3.60, 60);
    add_product(v_shop_id, v_household_category, 'Laundry Detergent Pods', 'Convenient laundry detergent pods for weekly washing.', 6.95, 48);
    add_product(v_shop_id, v_household_category, 'Beeswax Candles', 'Natural beeswax candles for a warm household glow.', 4.80, 35);
    add_product(v_shop_id, v_household_category, 'Compost Bin Liners', 'Compostable bin liners for food waste bins.', 3.50, 70);
    add_product(v_shop_id, v_household_category, 'Wooden Dish Brushes', 'Wooden dish brushes with firm cleaning bristles.', 3.25, 65);
    add_product(v_shop_id, v_household_category, 'Herbal Cleaning Spray', 'Herbal cleaning spray for kitchen and surface cleaning.', 3.95, 55);
    add_product(v_shop_id, v_household_category, 'Toilet Tissue Eco Pack', 'Eco toilet tissue pack for household bathrooms.', 6.50, 50);
    add_product(v_shop_id, v_household_category, 'Hand Wash Refill', 'Hand wash refill pouch for soap dispensers.', 3.30, 60);
    add_product(v_shop_id, v_household_category, 'Aluminum Foil Roll', 'Aluminum foil roll for wrapping and cooking.', 2.75, 75);
    add_product(v_shop_id, v_household_category, 'Baking Paper Sheets', 'Baking paper sheets for trays and food wrapping.', 2.95, 80);
    add_product(v_shop_id, v_household_category, 'Reusable Shopping Bags', 'Durable reusable shopping bags for market trips.', 4.10, 65);
    add_product(v_shop_id, v_household_category, 'Glass Storage Jars', 'Glass storage jars for pantry organization.', 5.90, 42);
    add_product(v_shop_id, v_household_category, 'Natural Air Freshener', 'Natural air freshener for fresh indoor spaces.', 3.70, 55);

    v_shop_id := ensure_trader('Beverage Shop Trader', 'beverage.trader@cleckhuddesfax.test', 'Beverage Shop', 'LICB001', 'Beverage Market Corner', '07111 000105');
    add_product(v_shop_id, v_beverage_category, 'Fresh Orange Juice', 'Fresh orange juice bottle with bright citrus flavour.', 2.95, 65);
    add_product(v_shop_id, v_beverage_category, 'Apple Cider Bottle', 'Apple cider bottle with crisp orchard flavour.', 3.50, 55);
    add_product(v_shop_id, v_beverage_category, 'Sparkling Mineral Water', 'Sparkling mineral water bottle for refreshing hydration.', 1.75, 95);
    add_product(v_shop_id, v_beverage_category, 'Kombucha Ginger Lemon', 'Ginger lemon kombucha with a tangy fermented finish.', 3.25, 48);
    add_product(v_shop_id, v_beverage_category, 'Cold Brew Coffee', 'Cold brew coffee bottle with a smooth bold taste.', 3.75, 50);
    add_product(v_shop_id, v_beverage_category, 'Herbal Chamomile Tea', 'Herbal chamomile tea for a calming hot drink.', 2.90, 70);
    add_product(v_shop_id, v_beverage_category, 'Green Tea Matcha Blend', 'Green tea matcha blend for daily tea brewing.', 4.25, 55);
    add_product(v_shop_id, v_beverage_category, 'Fresh Lemonade', 'Fresh lemonade bottle with balanced sweet citrus flavour.', 2.60, 70);
    add_product(v_shop_id, v_beverage_category, 'Coconut Water Pure', 'Pure coconut water for natural refreshment.', 2.85, 62);
    add_product(v_shop_id, v_beverage_category, 'Hot Chocolate Mix', 'Hot chocolate mix for rich warm drinks.', 3.40, 68);
    add_product(v_shop_id, v_beverage_category, 'Smoothie Berry Blend', 'Berry smoothie blend bottle with mixed fruit flavour.', 3.95, 45);
    add_product(v_shop_id, v_beverage_category, 'Fresh Sugarcane Juice', 'Fresh sugarcane juice bottle with natural sweetness.', 3.10, 50);
    add_product(v_shop_id, v_beverage_category, 'Black Tea Premium', 'Premium black tea pack for classic hot drinks.', 3.80, 65);
    add_product(v_shop_id, v_beverage_category, 'Iced Peach Tea', 'Iced peach tea bottle with a light fruity finish.', 2.70, 70);
    add_product(v_shop_id, v_beverage_category, 'Almond Milk Drink', 'Almond milk drink for dairy-free refreshment.', 3.20, 60);

    COMMIT;
END;
/
