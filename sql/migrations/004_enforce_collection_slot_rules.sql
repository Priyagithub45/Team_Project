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
BEFORE INSERT OR UPDATE OF SLOT_ID ON ORDERS
FOR EACH ROW
DECLARE
    v_collection_date COLLECTION_SLOT.COLLECTION_DATE%TYPE;
    v_collection_time COLLECTION_SLOT.COLLECTION_TIME%TYPE;
    v_day             VARCHAR2(3);
    v_time            VARCHAR2(20);
BEGIN
    SELECT COLLECTION_DATE, COLLECTION_TIME
      INTO v_collection_date, v_collection_time
      FROM COLLECTION_SLOT
     WHERE SLOT_ID = :NEW.SLOT_ID;

    IF v_collection_date < SYSDATE + 1 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Collection must be at least 24 hours later');
    END IF;

    v_day := TO_CHAR(v_collection_date, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH');
    v_time := REPLACE(REPLACE(v_collection_time, ' ', ''), ':00', '');

    IF v_day NOT IN ('WED', 'THU', 'FRI') THEN
        RAISE_APPLICATION_ERROR(-20005, 'Collection slots are only available on Wednesday, Thursday, and Friday');
    END IF;

    IF v_time NOT IN ('10-13', '13-16', '16-19') THEN
        RAISE_APPLICATION_ERROR(-20006, 'Collection slots are only available at 10:00-13:00, 13:00-16:00, or 16:00-19:00');
    END IF;
END;
/

