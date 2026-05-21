-- Normalize CART status values used by the PHP customer cart flow.

SET SERVEROUTPUT ON

DECLARE
    v_updated NUMBER;
BEGIN
    UPDATE CART
       SET STATUS = 'Active'
     WHERE STATUS = 'ACTIVE';

    v_updated := SQL%ROWCOUNT;
    DBMS_OUTPUT.PUT_LINE('Normalized ' || v_updated || ' CART status value(s) from ACTIVE to Active.');
END;
/

COMMIT;

PROMPT CART status check:
SELECT NVL(STATUS, '(NULL)') AS STATUS, COUNT(*) AS COUNT_ROWS
  FROM CART
 GROUP BY STATUS
 ORDER BY STATUS;
