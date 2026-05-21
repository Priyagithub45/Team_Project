-- Correct order/payment status rules for cash and PayPal orders.
-- Cash orders stay Pending until the collection slot has ended.
-- PayPal orders stay Paid after successful checkout.

DECLARE
    v_cash_method_id PAYMENT_METHOD.METHOD_ID%TYPE;
BEGIN
    SELECT METHOD_ID
      INTO v_cash_method_id
      FROM PAYMENT_METHOD
     WHERE UPPER(TRIM(METHOD_NAME)) = 'CASH'
       AND ROWNUM = 1;

    UPDATE PAYMENT p
       SET p.PAYMENT_STATUS = 'Pending',
           p.PAYMENT_DATE = NULL
     WHERE p.METHOD_ID = v_cash_method_id
       AND UPPER(NVL(p.PAYMENT_STATUS, 'PENDING')) = 'PAID'
       AND EXISTS (
            SELECT 1
              FROM ORDERS o
              JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
             WHERE o.ORDER_ID = p.ORDER_ID
               AND UPPER(NVL(o.STATUS, 'PENDING')) <> 'CANCELLED'
               AND (
                    TRUNC(cs.COLLECTION_DATE) +
                    CASE REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')
                        WHEN '10-13' THEN 13/24
                        WHEN '13-16' THEN 16/24
                        WHEN '16-19' THEN 19/24
                    END
               ) > SYSDATE
       );

    UPDATE ORDERS o
       SET o.STATUS = 'Pending'
     WHERE UPPER(NVL(o.STATUS, 'PENDING')) = 'PAID'
       AND EXISTS (
            SELECT 1
              FROM PAYMENT p
              JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
             WHERE p.ORDER_ID = o.ORDER_ID
               AND p.METHOD_ID = v_cash_method_id
               AND UPPER(NVL(p.PAYMENT_STATUS, 'PENDING')) = 'PENDING'
               AND (
                    TRUNC(cs.COLLECTION_DATE) +
                    CASE REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')
                        WHEN '10-13' THEN 13/24
                        WHEN '13-16' THEN 16/24
                        WHEN '16-19' THEN 19/24
                    END
               ) > SYSDATE
       );

    UPDATE PAYMENT p
       SET p.PAYMENT_STATUS = 'Paid',
           p.PAYMENT_DATE = SYSTIMESTAMP
     WHERE UPPER(NVL(p.PAYMENT_STATUS, 'PENDING')) = 'PENDING'
       AND EXISTS (
            SELECT 1
              FROM ORDERS o
              JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
             WHERE o.ORDER_ID = p.ORDER_ID
               AND UPPER(NVL(o.STATUS, 'PENDING')) <> 'CANCELLED'
               AND (
                    TRUNC(cs.COLLECTION_DATE) +
                    CASE REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')
                        WHEN '10-13' THEN 13/24
                        WHEN '13-16' THEN 16/24
                        WHEN '16-19' THEN 19/24
                    END
               ) <= SYSDATE
       );

    UPDATE ORDERS o
       SET o.STATUS = 'Paid'
     WHERE UPPER(NVL(o.STATUS, 'PENDING')) = 'PENDING'
       AND EXISTS (
            SELECT 1
              FROM COLLECTION_SLOT cs
             WHERE cs.SLOT_ID = o.SLOT_ID
               AND (
                    TRUNC(cs.COLLECTION_DATE) +
                    CASE REPLACE(REPLACE(cs.COLLECTION_TIME, ' ', ''), ':00', '')
                        WHEN '10-13' THEN 13/24
                        WHEN '13-16' THEN 16/24
                        WHEN '16-19' THEN 19/24
                    END
               ) <= SYSDATE
       );

    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        DBMS_OUTPUT.PUT_LINE('Cash payment method not found; no cash status correction applied.');
END;
/
