DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_tables
    WHERE table_name = 'CFO_MAIL_OUTBOX';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE '
            CREATE TABLE CFO_MAIL_OUTBOX (
                OUTBOX_ID NUMBER PRIMARY KEY,
                MAIL_TYPE VARCHAR2(50) NOT NULL,
                RELATED_ID NUMBER,
                RECIPIENT_EMAIL VARCHAR2(150) NOT NULL,
                SUBJECT VARCHAR2(250) NOT NULL,
                STATUS VARCHAR2(20) DEFAULT ''PENDING'' NOT NULL,
                ATTEMPTS NUMBER DEFAULT 0 NOT NULL,
                ERROR_MESSAGE VARCHAR2(1000),
                CREATED_AT TIMESTAMP DEFAULT SYSTIMESTAMP NOT NULL,
                SENT_AT TIMESTAMP,
                CONSTRAINT CK_CFO_MAIL_OUTBOX_STATUS CHECK (STATUS IN (''PENDING'', ''SENT'', ''FAILED''))
            )';
    END IF;
END;
/

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_sequences
    WHERE sequence_name = 'CFO_MAIL_OUTBOX_SEQ';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE SEQUENCE CFO_MAIL_OUTBOX_SEQ START WITH 1 INCREMENT BY 1 NOCACHE';
    END IF;
END;
/

DECLARE
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM user_indexes
    WHERE index_name = 'UQ_CFO_MAIL_APPROVAL';

    IF v_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE UNIQUE INDEX UQ_CFO_MAIL_APPROVAL ON CFO_MAIL_OUTBOX (
            CASE WHEN MAIL_TYPE = ''TRADER_APPROVAL'' THEN RELATED_ID END
        )';
    END IF;
END;
/

CREATE OR REPLACE TRIGGER trg_queue_trader_approval_mail
AFTER UPDATE OF status ON trader_application
FOR EACH ROW
BEGIN
    IF UPPER(NVL(:OLD.status, 'PENDING')) <> 'APPROVED'
       AND UPPER(NVL(:NEW.status, 'PENDING')) = 'APPROVED'
       AND NVL(:NEW.email_consent, 0) = 1
       AND :NEW.email_verified_at IS NOT NULL THEN
        BEGIN
            INSERT INTO cfo_mail_outbox (
                outbox_id,
                mail_type,
                related_id,
                recipient_email,
                subject,
                status,
                created_at
            ) VALUES (
                cfo_mail_outbox_seq.NEXTVAL,
                'TRADER_APPROVAL',
                :NEW.application_id,
                :NEW.email,
                'Your trader account has been approved - Cleckhuddesfax Online Mart',
                'PENDING',
                SYSTIMESTAMP
            );
        EXCEPTION
            WHEN DUP_VAL_ON_INDEX THEN
                NULL;
        END;
    END IF;
END;
/

COMMIT;
