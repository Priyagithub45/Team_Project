DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_tab_columns
    WHERE table_name = 'TRADER_APPLICATION'
      AND column_name = 'EMAIL_CONSENT';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_CONSENT NUMBER(1) DEFAULT 0 NOT NULL';
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
      AND column_name = 'EMAIL_CONSENT_AT';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_CONSENT_AT TIMESTAMP';
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
      AND column_name = 'EMAIL_VERIFIED_AT';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_VERIFIED_AT TIMESTAMP';
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
      AND column_name = 'EMAIL_VERIFY_TOKEN';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_VERIFY_TOKEN VARCHAR2(100)';
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
      AND column_name = 'EMAIL_VERIFY_OTP_HASH';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_VERIFY_OTP_HASH VARCHAR2(255)';
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
      AND column_name = 'EMAIL_VERIFY_OTP_EXPIRES_AT';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_VERIFY_OTP_EXPIRES_AT TIMESTAMP';
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
      AND column_name = 'EMAIL_VERIFY_OTP_ATTEMPTS';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'ALTER TABLE TRADER_APPLICATION ADD EMAIL_VERIFY_OTP_ATTEMPTS NUMBER DEFAULT 0 NOT NULL';
    END IF;
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
       ta.APPROVED_USER_ID,
       ta.EMAIL_CONSENT,
       ta.EMAIL_CONSENT_AT,
       ta.EMAIL_VERIFIED_AT,
       ta.EMAIL_VERIFY_OTP_EXPIRES_AT,
       ta.EMAIL_VERIFY_OTP_ATTEMPTS
FROM TRADER_APPLICATION ta
LEFT JOIN CATEGORY c ON c.CATEGORY_ID = ta.CATEGORY_ID;

COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_CONSENT IS '1 when applicant consented to transactional trader application/account emails.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_CONSENT_AT IS 'Timestamp when email consent was captured.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_VERIFIED_AT IS 'Timestamp when applicant verified ownership of their email address.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_VERIFY_TOKEN IS 'Token for future trader application email verification flow.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_VERIFY_OTP_HASH IS 'Password-hashed OTP used to verify trader application email ownership.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_VERIFY_OTP_EXPIRES_AT IS 'Expiry timestamp for the current trader application email OTP.';
COMMENT ON COLUMN TRADER_APPLICATION.EMAIL_VERIFY_OTP_ATTEMPTS IS 'Number of failed OTP attempts for trader application email verification.';

COMMIT;
