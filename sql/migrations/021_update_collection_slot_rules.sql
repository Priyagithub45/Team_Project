BEGIN
    EXECUTE IMMEDIATE 'DROP TRIGGER TRG_CHECK_COLLECTION_TIME';
EXCEPTION
    WHEN OTHERS THEN
        IF SQLCODE != -4080 THEN
            RAISE;
        END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_COLLECTION_SLOT_ALLOWED
BEFORE INSERT OR UPDATE OF COLLECTION_DATE, COLLECTION_TIME ON COLLECTION_SLOT
FOR EACH ROW
DECLARE
    v_day  VARCHAR2(3);
    v_time VARCHAR2(20);
BEGIN
    v_day := TO_CHAR(:NEW.COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH');
    v_time := REPLACE(REPLACE(:NEW.COLLECTION_TIME, ' ', ''), ':00', '');

    IF v_day NOT IN ('WED', 'THU', 'FRI') THEN
        RAISE_APPLICATION_ERROR(-20005, 'Collection slots are only available on Wednesday, Thursday, and Friday');
    END IF;

    IF v_time NOT IN ('10-13', '13-16', '16-19') THEN
        RAISE_APPLICATION_ERROR(-20006, 'Collection slots are only available at 10:00-13:00, 13:00-16:00, or 16:00-19:00');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_ORDERS_VALIDATE_SLOT_RULES
BEFORE INSERT OR UPDATE OF SLOT_ID, ORDER_DATE ON ORDERS
FOR EACH ROW
DECLARE
    v_collection_date COLLECTION_SLOT.COLLECTION_DATE%TYPE;
    v_collection_time COLLECTION_SLOT.COLLECTION_TIME%TYPE;
    v_day             VARCHAR2(3);
    v_time            VARCHAR2(20);
    v_slot_start      DATE;
BEGIN
    SELECT COLLECTION_DATE, COLLECTION_TIME
      INTO v_collection_date, v_collection_time
      FROM COLLECTION_SLOT
     WHERE SLOT_ID = :NEW.SLOT_ID;

    v_day := TO_CHAR(v_collection_date, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH');
    v_time := REPLACE(REPLACE(v_collection_time, ' ', ''), ':00', '');

    IF v_day NOT IN ('WED', 'THU', 'FRI') THEN
        RAISE_APPLICATION_ERROR(-20005, 'Collection slots are only available on Wednesday, Thursday, and Friday');
    END IF;

    IF v_time NOT IN ('10-13', '13-16', '16-19') THEN
        RAISE_APPLICATION_ERROR(-20006, 'Collection slots are only available at 10:00-13:00, 13:00-16:00, or 16:00-19:00');
    END IF;

    v_slot_start := TRUNC(v_collection_date) +
        CASE v_time
            WHEN '10-13' THEN 10/24
            WHEN '13-16' THEN 13/24
            WHEN '16-19' THEN 16/24
        END;

    IF v_slot_start < NVL(:NEW.ORDER_DATE, SYSDATE) + 1 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Collection must be at least 24 hours later');
    END IF;
END;
/

CREATE OR REPLACE TRIGGER TRG_CHECK_SLOT_CAPACITY
FOR INSERT OR UPDATE OF SLOT_ID ON ORDERS
COMPOUND TRIGGER
    TYPE t_slot_ids IS TABLE OF ORDERS.SLOT_ID%TYPE INDEX BY PLS_INTEGER;
    g_slot_ids t_slot_ids;
    g_slot_count PLS_INTEGER := 0;

    AFTER EACH ROW IS
    BEGIN
        IF :NEW.SLOT_ID IS NOT NULL THEN
            g_slot_count := g_slot_count + 1;
            g_slot_ids(g_slot_count) := :NEW.SLOT_ID;
        END IF;
    END AFTER EACH ROW;

    AFTER STATEMENT IS
        v_order_count NUMBER;
    BEGIN
        FOR i IN 1 .. g_slot_count LOOP
            SELECT COUNT(*)
              INTO v_order_count
              FROM ORDERS
             WHERE SLOT_ID = g_slot_ids(i);

            IF v_order_count > 20 THEN
                RAISE_APPLICATION_ERROR(-20003, 'Slot is full');
            END IF;
        END LOOP;
    END AFTER STATEMENT;
END;
/
