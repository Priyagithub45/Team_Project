DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_sequences
    WHERE sequence_name = 'SHOP_SEQ';

    IF v_count = 0 THEN
        SELECT NVL(MAX(shop_id), 0) + 1
        INTO v_count
        FROM shop;

        EXECUTE IMMEDIATE 'CREATE SEQUENCE SHOP_SEQ START WITH ' || v_count || ' INCREMENT BY 1 NOCACHE';
    END IF;
END;
/

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_tab_columns
    WHERE table_name = 'TRADER_APPLICATION'
      AND column_name = 'LICENSE_NO';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD LICENSE_NO VARCHAR2(50)';
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_trader_ensure_shop
AFTER INSERT OR UPDATE OF business_name, status ON trader
FOR EACH ROW
DECLARE
    v_count    NUMBER;
    v_shop_id  shop.shop_id%TYPE;
    v_name     shop.shop_name%TYPE;
    v_location shop.location%TYPE;
    v_contact  shop.contact_no%TYPE;

    FUNCTION next_shop_id RETURN NUMBER IS
        v_next shop.shop_id%TYPE;
    BEGIN
        BEGIN
            EXECUTE IMMEDIATE 'SELECT SHOP_SEQ.NEXTVAL FROM DUAL' INTO v_next;
            RETURN v_next;
        EXCEPTION
            WHEN OTHERS THEN
                SELECT NVL(MAX(shop_id), 0) + 1
                INTO v_next
                FROM shop;

                RETURN v_next;
        END;
    END;
BEGIN
    IF UPPER(NVL(:NEW.status, 'ACTIVE')) NOT IN ('ACTIVE', 'APPROVED') THEN
        RETURN;
    END IF;

    SELECT COUNT(*)
    INTO v_count
    FROM shop
    WHERE trader_id = :NEW.user_id;

    IF v_count > 0 THEN
        RETURN;
    END IF;

    v_name := SUBSTR(NVL(TRIM(:NEW.business_name), 'Trader ' || :NEW.user_id || ' Shop'), 1, 100);

    BEGIN
        SELECT address, phone_no
        INTO v_location, v_contact
        FROM system_user
        WHERE user_id = :NEW.user_id;
    EXCEPTION
        WHEN NO_DATA_FOUND THEN
            v_location := NULL;
            v_contact := NULL;
    END;

    FOR i IN 1..5 LOOP
        v_shop_id := next_shop_id;

        BEGIN
            INSERT INTO shop (
                shop_id,
                shop_name,
                location,
                contact_no,
                trader_id
            ) VALUES (
                v_shop_id,
                v_name,
                v_location,
                v_contact,
                :NEW.user_id
            );

            RETURN;
        EXCEPTION
            WHEN DUP_VAL_ON_INDEX THEN
                NULL;
        END;
    END LOOP;
END;
/

CREATE OR REPLACE TRIGGER trg_trader_application_approval
BEFORE UPDATE OF status ON trader_application
FOR EACH ROW
DECLARE
    v_user_id    system_user.user_id%TYPE;
    v_count      NUMBER;
    v_license_no trader.license_no%TYPE;

    FUNCTION next_user_id RETURN NUMBER IS
        v_next system_user.user_id%TYPE;
    BEGIN
        BEGIN
            EXECUTE IMMEDIATE 'SELECT SYSTEM_USER_SEQ.NEXTVAL FROM DUAL' INTO v_next;
            RETURN v_next;
        EXCEPTION
            WHEN OTHERS THEN
                BEGIN
                    EXECUTE IMMEDIATE 'SELECT SEQ_SYSTEM_USER.NEXTVAL FROM DUAL' INTO v_next;
                    RETURN v_next;
                EXCEPTION
                    WHEN OTHERS THEN
                        SELECT NVL(MAX(user_id), 0) + 1
                        INTO v_next
                        FROM system_user;

                        RETURN v_next;
                END;
        END;
    END;
BEGIN
    :NEW.status := UPPER(TRIM(:NEW.status));

    IF :NEW.status IN ('APPROVED', 'REJECTED')
       AND (UPPER(NVL(:OLD.status, 'PENDING')) <> :NEW.status OR :NEW.reviewed_at IS NULL) THEN
        :NEW.reviewed_at := SYSTIMESTAMP;
    END IF;

    IF :NEW.status <> 'APPROVED' THEN
        RETURN;
    END IF;

    v_user_id := :NEW.approved_user_id;

    IF v_user_id IS NULL THEN
        BEGIN
            SELECT user_id
            INTO v_user_id
            FROM system_user
            WHERE UPPER(TRIM(email)) = UPPER(TRIM(:NEW.email))
            FETCH FIRST 1 ROW ONLY;
        EXCEPTION
            WHEN NO_DATA_FOUND THEN
                v_user_id := NULL;
        END;
    END IF;

    IF v_user_id IS NULL THEN
        v_user_id := next_user_id;

        INSERT INTO system_user (
            user_id,
            name,
            email,
            password,
            phone_no,
            address,
            created_at,
            status
        ) VALUES (
            v_user_id,
            :NEW.owner_name,
            :NEW.email,
            'Trader@123',
            :NEW.phone_no,
            :NEW.address,
            SYSDATE,
            'ACTIVE'
        );
    ELSE
        UPDATE system_user
        SET name = :NEW.owner_name,
            phone_no = :NEW.phone_no,
            address = :NEW.address,
            status = 'ACTIVE'
        WHERE user_id = v_user_id;
    END IF;

    v_license_no := NVL(TRIM(:NEW.license_no), 'APP-' || :NEW.application_id);

    MERGE INTO trader t
    USING (
        SELECT v_user_id AS user_id,
               :NEW.proposed_shop_name AS business_name,
               v_license_no AS license_no
        FROM dual
    ) src
    ON (t.user_id = src.user_id)
    WHEN MATCHED THEN
        UPDATE SET t.business_name = src.business_name,
                   t.license_no = src.license_no,
                   t.status = 'ACTIVE'
    WHEN NOT MATCHED THEN
        INSERT (user_id, business_name, license_no, status)
        VALUES (src.user_id, src.business_name, src.license_no, 'ACTIVE');

    UPDATE shop
    SET shop_name = :NEW.proposed_shop_name,
        location = :NEW.address,
        contact_no = :NEW.phone_no
    WHERE trader_id = v_user_id;

    SELECT COUNT(*)
    INTO v_count
    FROM shop
    WHERE trader_id = v_user_id;

    IF v_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20042, 'Trader approval did not create a shop. Check shop name uniqueness and SHOP_SEQ.');
    END IF;

    :NEW.approved_user_id := v_user_id;
END;
/

CREATE OR REPLACE VIEW TRADER_APPLICATION_ADMIN_V AS
SELECT ta.APPLICATION_ID,
       ta.OWNER_NAME,
       ta.EMAIL,
       ta.PHONE_NO,
       ta.ADDRESS,
       ta.PROPOSED_SHOP_NAME,
       ta.LICENSE_NO,
       c.CATEGORY_NAME,
       ta.BUSINESS_DESCRIPTION,
       ta.NOTES,
       ta.STATUS,
       ta.ADMIN_NOTE,
       ta.CREATED_AT,
       ta.REVIEWED_AT,
       ta.REVIEWED_BY,
       ta.APPROVED_USER_ID
FROM TRADER_APPLICATION ta
LEFT JOIN CATEGORY c ON c.CATEGORY_ID = ta.CATEGORY_ID;

UPDATE trader_application
SET status = 'APPROVED'
WHERE UPPER(status) = 'APPROVED';

UPDATE trader
SET status = status
WHERE UPPER(NVL(status, 'ACTIVE')) IN ('ACTIVE', 'APPROVED')
  AND NOT EXISTS (
      SELECT 1
      FROM shop
      WHERE shop.trader_id = trader.user_id
  );

COMMIT;
