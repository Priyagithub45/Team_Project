SET DEFINE OFF

DECLARE
    v_cash_method_id PAYMENT_METHOD.METHOD_ID%TYPE;
    v_customer_id    CUSTOMER.USER_ID%TYPE;
    v_order_id       ORDERS.ORDER_ID%TYPE;
    v_payment_id     PAYMENT.PAYMENT_ID%TYPE;
    v_slot_id        COLLECTION_SLOT.SLOT_ID%TYPE;
    v_item_id        ORDER_ITEM.ORDER_ITEM_ID%TYPE;
    v_order_total    NUMBER(10,2);
    v_existing_sales NUMBER;
    v_shop_seeded    NUMBER;
    v_stock_before   PRODUCT.STOCK_QUANTITY%TYPE;
    v_qty            NUMBER;

    TYPE t_num_list IS TABLE OF NUMBER INDEX BY PLS_INTEGER;
    v_customer_ids t_num_list;

    FUNCTION next_system_user_id RETURN NUMBER IS
        v_id NUMBER;
        v_max_id NUMBER;
    BEGIN
        SELECT NVL(MAX(USER_ID), 0) INTO v_max_id FROM SYSTEM_USER;

        FOR attempt IN 1 .. 200 LOOP
            BEGIN
                EXECUTE IMMEDIATE 'SELECT SYSTEM_USER_SEQ.NEXTVAL FROM DUAL' INTO v_id;
            EXCEPTION
                WHEN OTHERS THEN
                    BEGIN
                        EXECUTE IMMEDIATE 'SELECT SEQ_SYSTEM_USER.NEXTVAL FROM DUAL' INTO v_id;
                    EXCEPTION
                        WHEN OTHERS THEN
                            v_id := v_max_id + 1;
                    END;
            END;

            EXIT WHEN v_id > v_max_id;
        END LOOP;

        IF v_id <= v_max_id THEN
            v_id := v_max_id + 1;
        END IF;

        RETURN v_id;
    END;

    FUNCTION next_order_id RETURN NUMBER IS
        v_id NUMBER;
        v_max_id NUMBER;
    BEGIN
        SELECT NVL(MAX(ORDER_ID), 0) INTO v_max_id FROM ORDERS;

        FOR attempt IN 1 .. 300 LOOP
            SELECT ORDER_SEQ.NEXTVAL INTO v_id FROM DUAL;
            EXIT WHEN v_id > v_max_id;
        END LOOP;

        IF v_id <= v_max_id THEN
            v_id := v_max_id + 1;
        END IF;

        RETURN v_id;
    END;

    FUNCTION next_payment_id RETURN NUMBER IS
        v_id NUMBER;
        v_max_id NUMBER;
    BEGIN
        SELECT NVL(MAX(PAYMENT_ID), 0) INTO v_max_id FROM PAYMENT;

        FOR attempt IN 1 .. 300 LOOP
            SELECT PAYMENT_SEQ.NEXTVAL INTO v_id FROM DUAL;
            EXIT WHEN v_id > v_max_id;
        END LOOP;

        IF v_id <= v_max_id THEN
            v_id := v_max_id + 1;
        END IF;

        RETURN v_id;
    END;

    FUNCTION next_order_item_id RETURN NUMBER IS
        v_id NUMBER;
    BEGIN
        SELECT NVL(MAX(ORDER_ITEM_ID), 0) + 1
          INTO v_id
          FROM ORDER_ITEM;

        RETURN v_id;
    END;

    FUNCTION slot_time_for(p_index IN NUMBER) RETURN VARCHAR2 IS
    BEGIN
        CASE MOD(p_index - 1, 3)
            WHEN 0 THEN RETURN '10:00 - 13:00';
            WHEN 1 THEN RETURN '13:00 - 16:00';
            ELSE RETURN '16:00 - 19:00';
        END CASE;
    END;

    FUNCTION slot_date_for(p_index IN NUMBER) RETURN DATE IS
        v_base DATE;
    BEGIN
        v_base := NEXT_DAY(TRUNC(SYSDATE + 1), 'WEDNESDAY') + (7 * FLOOR((p_index - 1) / 9));

        CASE MOD(FLOOR((p_index - 1) / 3), 3)
            WHEN 0 THEN RETURN v_base;
            WHEN 1 THEN RETURN v_base + 1;
            ELSE RETURN v_base + 2;
        END CASE;
    END;

    FUNCTION ensure_slot(p_index IN NUMBER) RETURN NUMBER IS
        v_id COLLECTION_SLOT.SLOT_ID%TYPE;
        v_date DATE;
        v_time COLLECTION_SLOT.COLLECTION_TIME%TYPE;
    BEGIN
        v_date := slot_date_for(p_index);
        v_time := slot_time_for(p_index);

        BEGIN
            SELECT SLOT_ID
              INTO v_id
              FROM COLLECTION_SLOT
             WHERE TRUNC(COLLECTION_DATE) = v_date
               AND REPLACE(REPLACE(COLLECTION_TIME, ' ', ''), ':00', '') = REPLACE(REPLACE(v_time, ' ', ''), ':00', '')
               AND ROWNUM = 1;

            RETURN v_id;
        EXCEPTION
            WHEN NO_DATA_FOUND THEN
                SELECT NVL(MAX(SLOT_ID), 0) + 1
                  INTO v_id
                  FROM COLLECTION_SLOT;

                INSERT INTO COLLECTION_SLOT (SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION)
                VALUES (v_id, v_date, v_time, 'Cleckheaton Town Centre');

                RETURN v_id;
        END;
    END;

    FUNCTION available_slot(p_seed IN NUMBER) RETURN NUMBER IS
        v_id COLLECTION_SLOT.SLOT_ID%TYPE;
        v_booked NUMBER;
        v_index NUMBER;
    BEGIN
        FOR attempt IN 0 .. 53 LOOP
            v_index := p_seed + attempt;
            v_id := ensure_slot(v_index);

            SELECT COUNT(*)
              INTO v_booked
              FROM ORDERS
             WHERE SLOT_ID = v_id;

            IF v_booked < 18 THEN
                RETURN v_id;
            END IF;
        END LOOP;

        RETURN ensure_slot(p_seed + 54);
    END;

    PROCEDURE ensure_customer(
        p_index IN PLS_INTEGER,
        p_name  IN VARCHAR2,
        p_email IN VARCHAR2
    ) IS
        v_id SYSTEM_USER.USER_ID%TYPE;
    BEGIN
        BEGIN
            SELECT USER_ID
              INTO v_id
              FROM SYSTEM_USER
             WHERE UPPER(TRIM(EMAIL)) = UPPER(TRIM(p_email));
        EXCEPTION
            WHEN NO_DATA_FOUND THEN
                v_id := next_system_user_id;

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
                    v_id,
                    p_name,
                    p_email,
                    'Customer@123',
                    '07900 10010' || p_index,
                    'Demo customer address ' || p_index,
                    SYSDATE,
                    'ACTIVE'
                );
        END;

        MERGE INTO CUSTOMER c
        USING (SELECT v_id AS USER_ID FROM DUAL) src
        ON (c.USER_ID = src.USER_ID)
        WHEN MATCHED THEN
            UPDATE SET c.LOYALTY_POINTS = NVL(c.LOYALTY_POINTS, 0)
        WHEN NOT MATCHED THEN
            INSERT (USER_ID, LOYALTY_POINTS, DATE_OF_BIRTH)
            VALUES (src.USER_ID, 0, ADD_MONTHS(TRUNC(SYSDATE), -360));

        v_customer_ids(p_index) := v_id;
    END;

    PROCEDURE add_line(
        p_order_id   IN ORDERS.ORDER_ID%TYPE,
        p_product_id IN PRODUCT.PRODUCT_ID%TYPE,
        p_quantity   IN NUMBER,
        p_price      IN NUMBER
    ) IS
    BEGIN
        SELECT STOCK_QUANTITY
          INTO v_stock_before
          FROM PRODUCT
         WHERE PRODUCT_ID = p_product_id
         FOR UPDATE;

        v_item_id := next_order_item_id;

        INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, PRICE)
        VALUES (v_item_id, p_order_id, p_product_id, p_quantity, p_price);

        UPDATE PRODUCT
           SET STOCK_QUANTITY = v_stock_before
         WHERE PRODUCT_ID = p_product_id;
    END;

BEGIN
    BEGIN
        SELECT METHOD_ID
          INTO v_cash_method_id
          FROM PAYMENT_METHOD
         WHERE UPPER(TRIM(METHOD_NAME)) = 'CASH'
           AND ROWNUM = 1;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            SELECT NVL(MAX(METHOD_ID), 0) + 1
              INTO v_cash_method_id
              FROM PAYMENT_METHOD;

            INSERT INTO PAYMENT_METHOD (METHOD_ID, METHOD_NAME, DESCRIPTION)
            VALUES (v_cash_method_id, 'Cash', 'Cash payment on collection');
    END;

    ensure_customer(1, 'Demo Customer One', 'demo.customer.one@cleckhuddesfax.test');
    ensure_customer(2, 'Demo Customer Two', 'demo.customer.two@cleckhuddesfax.test');
    ensure_customer(3, 'Demo Customer Three', 'demo.customer.three@cleckhuddesfax.test');
    ensure_customer(4, 'Demo Customer Four', 'demo.customer.four@cleckhuddesfax.test');
    ensure_customer(5, 'Demo Customer Five', 'demo.customer.five@cleckhuddesfax.test');

    FOR shop_rec IN (
        SELECT s.SHOP_ID, s.SHOP_NAME
          FROM SHOP s
          JOIN TRADER t ON t.USER_ID = s.TRADER_ID
          JOIN SYSTEM_USER su ON su.USER_ID = t.USER_ID
         WHERE NVL(UPPER(TRIM(t.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
           AND NVL(UPPER(TRIM(su.STATUS)), 'ACTIVE') IN ('ACTIVE', 'APPROVED')
           AND EXISTS (
               SELECT 1
                 FROM PRODUCT p
                WHERE p.SHOP_ID = s.SHOP_ID
                  AND NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE'
           )
         ORDER BY s.SHOP_NAME
    ) LOOP
        SELECT COUNT(*)
          INTO v_existing_sales
          FROM ORDER_ITEM oi
          JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
          JOIN ORDERS o ON o.ORDER_ID = oi.ORDER_ID
         WHERE p.SHOP_ID = shop_rec.SHOP_ID
           AND UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED');

        IF v_existing_sales = 0 THEN
            v_shop_seeded := 0;

            FOR product_rec IN (
                SELECT *
                  FROM (
                      SELECT p.PRODUCT_ID,
                             p.PRODUCT_NAME,
                             p.PRICE,
                             NVL(p.STOCK_QUANTITY, 0) AS STOCK_QUANTITY,
                             NVL(p.MIN_ORDER, 1) AS MIN_ORDER,
                             NVL(p.MAX_ORDER, 10) AS MAX_ORDER,
                             ROW_NUMBER() OVER (ORDER BY p.PRICE DESC, p.PRODUCT_NAME) AS RN
                        FROM PRODUCT p
                       WHERE p.SHOP_ID = shop_rec.SHOP_ID
                         AND NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE'
                         AND NVL(p.STOCK_QUANTITY, 0) > 0
                  )
                 WHERE RN <= 6
                 ORDER BY RN
            ) LOOP
                v_qty := LEAST(product_rec.MAX_ORDER, GREATEST(product_rec.MIN_ORDER, MOD(product_rec.RN, 3) + 1));
                v_qty := LEAST(v_qty, product_rec.STOCK_QUANTITY);

                IF v_qty >= product_rec.MIN_ORDER THEN
                    v_slot_id := available_slot(((v_shop_seeded * 2) + product_rec.RN));
                    v_customer_id := v_customer_ids(MOD(product_rec.RN - 1, 5) + 1);
                    v_order_total := ROUND(v_qty * product_rec.PRICE, 2);

                    v_order_id := next_order_id;

                    INSERT INTO ORDERS (
                        ORDER_ID,
                        CUSTOMER_ID,
                        SLOT_ID,
                        TOTAL_AMOUNT,
                        ORDER_DATE,
                        STATUS
                    ) VALUES (
                        v_order_id,
                        v_customer_id,
                        v_slot_id,
                        v_order_total,
                        SYSDATE,
                        'Paid'
                    );

                    add_line(v_order_id, product_rec.PRODUCT_ID, v_qty, product_rec.PRICE);

                    v_payment_id := next_payment_id;

                    INSERT INTO PAYMENT (
                        PAYMENT_ID,
                        AMOUNT,
                        PAYMENT_STATUS,
                        ORDER_ID,
                        METHOD_ID,
                        USER_ID
                    ) VALUES (
                        v_payment_id,
                        v_order_total,
                        'Paid',
                        v_order_id,
                        v_cash_method_id,
                        v_customer_id
                    );

                    v_shop_seeded := v_shop_seeded + 1;
                END IF;
            END LOOP;
        END IF;
    END LOOP;

    COMMIT;
END;
/

PROMPT Demo sales seeded for active shops with no existing paid sales. Product stock quantities were restored after order item inserts.

COLUMN SHOP_NAME FORMAT A36
COLUMN SEEDED_ORDER_LINES FORMAT 999
SELECT s.SHOP_NAME,
       COUNT(oi.ORDER_ITEM_ID) AS SEEDED_ORDER_LINES
  FROM SHOP s
  JOIN PRODUCT p ON p.SHOP_ID = s.SHOP_ID
  LEFT JOIN ORDER_ITEM oi ON oi.PRODUCT_ID = p.PRODUCT_ID
  LEFT JOIN ORDERS o ON o.ORDER_ID = oi.ORDER_ID
 WHERE UPPER(NVL(o.STATUS, 'PAID')) IN ('PAID', 'PREPARING', 'READY FOR COLLECTION', 'COLLECTED', 'COMPLETED')
 GROUP BY s.SHOP_NAME
 ORDER BY s.SHOP_NAME;
