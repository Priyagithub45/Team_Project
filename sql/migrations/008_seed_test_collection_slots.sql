-- Seed collection slots for testing (3 weeks: Wed/Thu/Fri, 3 time slots each)
-- Safe to re-run: uses MERGE to skip existing SLOT_IDs
-- Dates: 2026-05-13 through 2026-06-05 (9 days x 3 slots = 27 rows)
-- Run in SQL*Plus / Oracle SQL Developer against your schema

DECLARE
    v_base NUMBER;
    TYPE t_slot IS RECORD (
        d DATE,
        t VARCHAR2(20),
        loc VARCHAR2(100)
    );
    TYPE t_slots IS TABLE OF t_slot;
    v_slots t_slots := t_slots();

    PROCEDURE add_day(p_date DATE) IS
    BEGIN
        v_slots.EXTEND(3);
        v_slots(v_slots.LAST - 2).d   := p_date; v_slots(v_slots.LAST - 2).t := '10:00 - 13:00'; v_slots(v_slots.LAST - 2).loc := 'Cleckheaton Town Centre';
        v_slots(v_slots.LAST - 1).d   := p_date; v_slots(v_slots.LAST - 1).t := '13:00 - 16:00'; v_slots(v_slots.LAST - 1).loc := 'Cleckheaton Town Centre';
        v_slots(v_slots.LAST).d       := p_date; v_slots(v_slots.LAST).t     := '16:00 - 19:00'; v_slots(v_slots.LAST).loc     := 'Cleckheaton Town Centre';
    END;
BEGIN
    SELECT NVL(MAX(SLOT_ID), 900) INTO v_base FROM COLLECTION_SLOT;

    -- Week 1: 13-15 May 2026
    add_day(TO_DATE('2026-05-13', 'YYYY-MM-DD')); -- Wed
    add_day(TO_DATE('2026-05-14', 'YYYY-MM-DD')); -- Thu
    add_day(TO_DATE('2026-05-15', 'YYYY-MM-DD')); -- Fri
    -- Week 2: 20-22 May 2026
    add_day(TO_DATE('2026-05-20', 'YYYY-MM-DD')); -- Wed
    add_day(TO_DATE('2026-05-21', 'YYYY-MM-DD')); -- Thu
    add_day(TO_DATE('2026-05-22', 'YYYY-MM-DD')); -- Fri
    -- Week 3: 27-29 May 2026
    add_day(TO_DATE('2026-05-27', 'YYYY-MM-DD')); -- Wed
    add_day(TO_DATE('2026-05-28', 'YYYY-MM-DD')); -- Thu
    add_day(TO_DATE('2026-05-29', 'YYYY-MM-DD')); -- Fri

    FOR i IN 1 .. v_slots.COUNT LOOP
        MERGE INTO COLLECTION_SLOT tgt
        USING (SELECT (v_base + i) AS sid FROM DUAL) src
        ON (tgt.SLOT_ID = src.sid)
        WHEN NOT MATCHED THEN
            INSERT (SLOT_ID, COLLECTION_DATE, COLLECTION_TIME, LOCATION)
            VALUES (v_base + i, v_slots(i).d, v_slots(i).t, v_slots(i).loc);
    END LOOP;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Seeded ' || v_slots.COUNT || ' test slots (base ID: ' || v_base || ').');
END;
/
