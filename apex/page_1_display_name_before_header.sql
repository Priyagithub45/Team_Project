DECLARE
    l_display_name VARCHAR2(200);
BEGIN
    SELECT CASE
               WHEN LOWER(su.email) = 'admin@test.com' THEN 'Admin'
               WHEN t.user_id IS NOT NULL THEN NVL(t.business_name, su.name)
               ELSE su.name
           END
      INTO l_display_name
      FROM system_user su
      LEFT JOIN trader t
        ON t.user_id = su.user_id
     WHERE LOWER(su.email) = LOWER(:APP_USER);

    :P1_DISPLAY_NAME := INITCAP(TRIM(l_display_name));
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        :P1_DISPLAY_NAME := INITCAP(
            REPLACE(REGEXP_SUBSTR(:APP_USER, '^[^@]+'), '.', ' ')
        );
END;
