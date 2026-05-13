<?php

const COLLECTION_SLOT_MAX_ORDERS = 20;

function collection_slot_time_expr(string $alias = 'cs'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return "REPLACE(REPLACE({$prefix}COLLECTION_TIME, ' ', ''), ':00', '')";
}

function collection_slot_start_expr(string $alias = 'cs'): string
{
    $time_expr = collection_slot_time_expr($alias);
    $prefix = $alias !== '' ? $alias . '.' : '';

    return "(TRUNC({$prefix}COLLECTION_DATE) + CASE {$time_expr}
                WHEN '10-13' THEN 10/24
                WHEN '13-16' THEN 13/24
                WHEN '16-19' THEN 16/24
            END)";
}

function collection_slot_allowed_sql(string $alias = 'cs', bool $include_capacity = false): string
{
    $time_expr = collection_slot_time_expr($alias);
    $start_expr = collection_slot_start_expr($alias);
    $prefix = $alias !== '' ? $alias . '.' : '';

    $sql = "{$start_expr} >= SYSDATE + 1
            AND TO_CHAR({$prefix}COLLECTION_DATE, 'FMDY', 'NLS_DATE_LANGUAGE=ENGLISH') IN ('WED','THU','FRI')
            AND {$time_expr} IN ('10-13','13-16','16-19')";

    if ($include_capacity) {
        $sql .= "
            AND (" . COLLECTION_SLOT_MAX_ORDERS . " - (SELECT COUNT(*) FROM ORDERS o_slot WHERE o_slot.SLOT_ID = {$prefix}SLOT_ID)) > 0";
    }

    return $sql;
}

function collection_slot_order_sql(string $alias = 'cs'): string
{
    $time_expr = collection_slot_time_expr($alias);

    return "CASE {$time_expr}
                WHEN '10-13' THEN 1
                WHEN '13-16' THEN 2
                WHEN '16-19' THEN 3
                ELSE 9
            END";
}
