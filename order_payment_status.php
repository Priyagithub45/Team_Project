<?php

function cfo_collection_slot_end_sql(string $alias = 'cs'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $time_expr = "REPLACE(REPLACE({$prefix}COLLECTION_TIME, ' ', ''), ':00', '')";

    return "(TRUNC({$prefix}COLLECTION_DATE) + CASE {$time_expr}
                WHEN '10-13' THEN 13/24
                WHEN '13-16' THEN 16/24
                WHEN '16-19' THEN 19/24
            END)";
}

function cfo_sync_matured_collection_payments($conn): void
{
    static $synced = false;
    if ($synced || !$conn) {
        return;
    }
    $synced = true;

    $slot_end_sql = cfo_collection_slot_end_sql('cs');

    $payment_sql = "
        UPDATE PAYMENT p
           SET p.PAYMENT_STATUS = 'Paid',
               p.PAYMENT_DATE = SYSTIMESTAMP
         WHERE UPPER(NVL(p.PAYMENT_STATUS, 'PENDING')) = 'PENDING'
           AND EXISTS (
                SELECT 1
                  FROM ORDERS o
                  JOIN COLLECTION_SLOT cs ON cs.SLOT_ID = o.SLOT_ID
                 WHERE o.ORDER_ID = p.ORDER_ID
                   AND UPPER(NVL(o.STATUS, 'PENDING')) <> 'CANCELLED'
                   AND {$slot_end_sql} <= SYSDATE
           )";

    $stmt = oci_parse($conn, $payment_sql);
    if (!$stmt || !oci_execute($stmt)) {
        $err = $stmt ? oci_error($stmt) : oci_error($conn);
        error_log('[PAYMENT STATUS SYNC PAYMENT] ' . ($err['message'] ?? 'unknown error'));
        if ($stmt) {
            oci_free_statement($stmt);
        }
        return;
    }
    oci_free_statement($stmt);

    $order_sql = "
        UPDATE ORDERS o
           SET o.STATUS = 'Paid'
         WHERE UPPER(NVL(o.STATUS, 'PENDING')) = 'PENDING'
           AND EXISTS (
                SELECT 1
                  FROM COLLECTION_SLOT cs
                 WHERE cs.SLOT_ID = o.SLOT_ID
                   AND {$slot_end_sql} <= SYSDATE
           )";

    $stmt = oci_parse($conn, $order_sql);
    if (!$stmt || !oci_execute($stmt)) {
        $err = $stmt ? oci_error($stmt) : oci_error($conn);
        error_log('[PAYMENT STATUS SYNC ORDER] ' . ($err['message'] ?? 'unknown error'));
        if ($stmt) {
            oci_free_statement($stmt);
        }
        return;
    }
    oci_free_statement($stmt);
}
