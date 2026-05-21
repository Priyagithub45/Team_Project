-- Cleanup for accidental CART generator/import pollution.
-- Removes generated empty carts with huge IDs while preserving real sequence carts
-- and any cart that has items attached.

SET SERVEROUTPUT ON

DECLARE
    v_deleted NUMBER;
BEGIN
    DELETE FROM CART ca
     WHERE ca.CART_ID > 1000000
       AND NOT EXISTS (
           SELECT 1
             FROM CART_ITEM ci
            WHERE ci.CART_ID = ca.CART_ID
       );

    v_deleted := SQL%ROWCOUNT;
    DBMS_OUTPUT.PUT_LINE('Deleted ' || v_deleted || ' generated empty CART row(s).');
END;
/

COMMIT;

PROMPT CART cleanup check:
SELECT 'TOTAL_CARTS' AS CHECK_NAME, COUNT(*) AS COUNT_ROWS
  FROM CART
UNION ALL
SELECT 'HUGE_EMPTY_CARTS', COUNT(*)
  FROM CART ca
 WHERE ca.CART_ID > 1000000
   AND NOT EXISTS (
       SELECT 1
         FROM CART_ITEM ci
        WHERE ci.CART_ID = ca.CART_ID
   )
UNION ALL
SELECT 'HUGE_CARTS_WITH_ITEMS', COUNT(*)
  FROM CART ca
 WHERE ca.CART_ID > 1000000
   AND EXISTS (
       SELECT 1
         FROM CART_ITEM ci
        WHERE ci.CART_ID = ca.CART_ID
   )
UNION ALL
SELECT 'NORMAL_CARTS', COUNT(*)
  FROM CART ca
 WHERE ca.CART_ID <= 1000000;
