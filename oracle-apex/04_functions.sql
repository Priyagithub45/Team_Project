create or replace FUNCTION system_user_auth (
    p_username IN VARCHAR2,
    p_password IN VARCHAR2
) RETURN BOOLEAN
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*)
    INTO v_count
    FROM SYSTEM_USER
    WHERE UPPER(TRIM(email)) = UPPER(TRIM(p_username))
      AND TRIM(password) = TRIM(p_password);

    RETURN v_count = 1;
END;
/