-- Cleanup for accidental ADMIN table spreadsheet/import pollution.
-- Keeps only the legitimate admin account: admin@test.com.

SET SERVEROUTPUT ON

DECLARE
    v_backup_exists NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_backup_exists
      FROM USER_TABLES
     WHERE TABLE_NAME = 'ADMIN_BACKUP_BEFORE_CLEANUP';

    IF v_backup_exists = 0 THEN
        EXECUTE IMMEDIATE 'CREATE TABLE ADMIN_BACKUP_BEFORE_CLEANUP AS SELECT * FROM ADMIN';
        DBMS_OUTPUT.PUT_LINE('Created ADMIN_BACKUP_BEFORE_CLEANUP.');
    ELSE
        DBMS_OUTPUT.PUT_LINE('ADMIN_BACKUP_BEFORE_CLEANUP already exists; existing backup left untouched.');
    END IF;
END;
/

DECLARE
    v_admin_user_id SYSTEM_USER.USER_ID%TYPE;
    v_admin_matches NUMBER;
    v_deleted NUMBER;
BEGIN
    SELECT COUNT(*), MIN(USER_ID)
      INTO v_admin_matches, v_admin_user_id
      FROM SYSTEM_USER
     WHERE LOWER(EMAIL) = 'admin@test.com';

    IF v_admin_matches != 1 THEN
        RAISE_APPLICATION_ERROR(
            -20040,
            'Expected exactly one SYSTEM_USER row for admin@test.com, found ' || v_admin_matches
        );
    END IF;

    MERGE INTO ADMIN a
    USING (SELECT v_admin_user_id AS USER_ID FROM DUAL) src
       ON (a.USER_ID = src.USER_ID)
    WHEN MATCHED THEN
        UPDATE SET
            a.ADMIN_ROLE = 'Manager',
            a.PERMISSIONS = 'Full Access'
    WHEN NOT MATCHED THEN
        INSERT (USER_ID, ADMIN_ROLE, PERMISSIONS)
        VALUES (src.USER_ID, 'Manager', 'Full Access');

    DELETE FROM ADMIN
     WHERE USER_ID <> v_admin_user_id;

    v_deleted := SQL%ROWCOUNT;
    DBMS_OUTPUT.PUT_LINE('Deleted ' || v_deleted || ' non-admin ADMIN row(s).');
END;
/

COMMIT;

PROMPT Remaining ADMIN rows:
SELECT a.USER_ID, su.NAME, su.EMAIL, a.ADMIN_ROLE, a.PERMISSIONS
  FROM ADMIN a
  JOIN SYSTEM_USER su ON su.USER_ID = a.USER_ID
 ORDER BY a.USER_ID;

PROMPT ADMIN overlap check:
SELECT 'ADMIN + CUSTOMER' AS KIND, COUNT(*) AS COUNT_ROWS
  FROM ADMIN a
  JOIN CUSTOMER c ON c.USER_ID = a.USER_ID
UNION ALL
SELECT 'ADMIN + TRADER' AS KIND, COUNT(*) AS COUNT_ROWS
  FROM ADMIN a
  JOIN TRADER t ON t.USER_ID = a.USER_ID;
