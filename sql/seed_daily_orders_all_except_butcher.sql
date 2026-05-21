SET SERVEROUTPUT ON
SET FEEDBACK ON
SET HEADING ON
SET PAGESIZE 200
SET LINESIZE 240
WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

DECLARE
    v_customer_count NUMBER;
    v_current_orders NUMBER;
    v_needed         NUMBER;
    v_sale_n         NUMBER;
    v_total_added    NUMBER := 0;
    v_customer_id    CUSTOMER.USER_ID%TYPE;
    v_slot_id        COLLECTION_SLOT.SLOT_ID%TYPE;
    v_order_id       ORDERS.ORDER_ID%TYPE;
    v_item_id        ORDER_ITEM.ORDER_ITEM_ID%TYPE;
    v_payment_id     PAYMENT.PAYMENT_ID%TYPE;
    v_cash_method_id PAYMENT_METHOD.METHOD_ID%TYPE;
    v_qty            NUMBER;
    v_total          NUMBER(10,2);
    v_stock_before   PRODUCT.STOCK_QUANTITY%TYPE;
BEGIN
    SELECT COUNT(*) INTO v_customer_count FROM CUSTOMER;

    IF v_customer_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'No customers exist to attach demo orders to.');
    END IF;

    BEGIN
        SELECT METHOD_ID
          INTO v_cash_method_id
          FROM PAYMENT_METHOD
         WHERE UPPER(TRIM(METHOD_NAME)) = 'CASH'
           AND ROWNUM = 1;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            SELECT NVL(MAX(METHOD_ID), 0) + 1 INTO v_cash_method_id FROM PAYMENT_METHOD;
            INSERT INTO PAYMENT_METHOD (METHOD_ID, METHOD_NAME, DESCRIPTION)
            VALUES (v_cash_method_id, 'Cash', 'Cash payment on collection');
    END;

    FOR shop_rec IN (
        SELECT s.SHOP_ID,
               s.SHOP_NAME,
               ROW_NUMBER() OVER (ORDER BY s.SHOP_NAME) AS SHOP_RN
          FROM SHOP s
         WHERE UPPER(s.SHOP_NAME) NOT LIKE '%BUTCHER%'
           AND EXISTS (
               SELECT 1
                 FROM PRODUCT p
                WHERE p.SHOP_ID = s.SHOP_ID
                  AND NVL(UPPER(TRIM(p.STATUS)), 'ACTIVE') = 'ACTIVE'
                  AND NVL(p.STOCK_QUANTITY, 0) > 0
           )
         ORDER BY s.SHOP_NAME
    ) LOOP
        SELECT COUNT(DISTINCT o.ORDER_ID)
          INTO v_current_orders
          FROM ORDERS o
          JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
          JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
         WHERE p.SHOP_ID = shop_rec.SHOP_ID
           AND TRUNC(o.ORDER_DATE) = DATE '2026-05-15';

        v_needed := GREATEST(0, 7 - v_current_orders);
        v_sale_n := 0;

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
             WHERE RN <= v_needed
             ORDER BY RN
        ) LOOP
            v_sale_n := v_sale_n + 1;

            SELECT USER_ID
              INTO v_customer_id
              FROM (
                  SELECT c.USER_ID,
                         ROW_NUMBER() OVER (ORDER BY c.USER_ID) AS RN
                    FROM CUSTOMER c
              )
             WHERE RN = MOD((shop_rec.SHOP_RN * 7) + v_sale_n - 1, v_customer_count) + 1;

            SELECT SLOT_ID
              INTO v_slot_id
              FROM (
                  SELECT SLOT_ID,
                         ROW_NUMBER() OVER (ORDER BY COLLECTION_DATE, SLOT_ID) AS RN
                    FROM COLLECTION_SLOT
                   WHERE COLLECTION_DATE >= DATE '2026-05-20'
              )
             WHERE RN = MOD((shop_rec.SHOP_RN * 7) + v_sale_n - 1, 12) + 1;

            v_qty := LEAST(product_rec.MAX_ORDER, GREATEST(product_rec.MIN_ORDER, MOD(v_sale_n + shop_rec.SHOP_RN, 3) + 1));
            v_qty := LEAST(v_qty, product_rec.STOCK_QUANTITY);

            IF v_qty < product_rec.MIN_ORDER THEN
                CONTINUE;
            END IF;

            v_total := ROUND(v_qty * product_rec.PRICE, 2);

            SELECT ORDER_SEQ.NEXTVAL INTO v_order_id FROM DUAL;

            INSERT INTO ORDERS (ORDER_ID, ORDER_DATE, TOTAL_AMOUNT, STATUS, CUSTOMER_ID, SLOT_ID)
            VALUES (
                v_order_id,
                DATE '2026-05-15' + ((8 + shop_rec.SHOP_RN) / 24) + (v_sale_n / 1440),
                v_total,
                'Paid',
                v_customer_id,
                v_slot_id
            );

            SELECT NVL(MAX(ORDER_ITEM_ID), 0) + 1 INTO v_item_id FROM ORDER_ITEM;
            SELECT STOCK_QUANTITY INTO v_stock_before FROM PRODUCT WHERE PRODUCT_ID = product_rec.PRODUCT_ID FOR UPDATE;

            INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, PRODUCT_ID, QUANTITY, PRICE)
            VALUES (v_item_id, v_order_id, product_rec.PRODUCT_ID, v_qty, product_rec.PRICE);

            UPDATE PRODUCT
               SET STOCK_QUANTITY = v_stock_before
             WHERE PRODUCT_ID = product_rec.PRODUCT_ID;

            SELECT PAYMENT_SEQ.NEXTVAL INTO v_payment_id FROM DUAL;

            INSERT INTO PAYMENT (PAYMENT_ID, PAYMENT_DATE, AMOUNT, PAYMENT_STATUS, ORDER_ID, METHOD_ID, USER_ID)
            VALUES (
                v_payment_id,
                CAST(DATE '2026-05-15' + ((8 + shop_rec.SHOP_RN) / 24) + (v_sale_n / 1440) AS TIMESTAMP),
                v_total,
                'Paid',
                v_order_id,
                v_cash_method_id,
                v_customer_id
            );
        END LOOP;

        v_total_added := v_total_added + v_sale_n;
        DBMS_OUTPUT.PUT_LINE(shop_rec.SHOP_NAME || ': added ' || v_sale_n || ' order(s)');
    END LOOP;

    DBMS_OUTPUT.PUT_LINE('Total non-butcher orders added: ' || v_total_added);
END;
/

COMMIT;

COLUMN SHOP_NAME FORMAT A34
COLUMN CATEGORY_NAME FORMAT A28
SELECT s.SHOP_NAME,
       NVL(c.CATEGORY_NAME, '(No Category)') AS CATEGORY_NAME,
       COUNT(DISTINCT o.ORDER_ID) AS ORDERS_TODAY,
       SUM(oi.QUANTITY) AS ITEMS_SOLD,
       SUM(oi.QUANTITY * oi.PRICE) AS SALES_TOTAL
  FROM ORDERS o
  JOIN ORDER_ITEM oi ON oi.ORDER_ID = o.ORDER_ID
  JOIN PRODUCT p ON p.PRODUCT_ID = oi.PRODUCT_ID
  JOIN SHOP s ON s.SHOP_ID = p.SHOP_ID
  LEFT JOIN CATEGORY c ON c.CATEGORY_ID = p.CATEGORY_ID
 WHERE TRUNC(o.ORDER_DATE) = DATE '2026-05-15'
   AND UPPER(s.SHOP_NAME) NOT LIKE '%BUTCHER%'
 GROUP BY s.SHOP_NAME, c.CATEGORY_NAME
 ORDER BY s.SHOP_NAME, c.CATEGORY_NAME;

EXIT
