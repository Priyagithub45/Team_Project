<?php
// Start session for every page that includes db.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * db.php — Oracle DB connection.
 * Defines $conn for use by including pages.
 *
 * Edit the four constants to match your install.
 *   - Oracle 23ai/Free  -> FREEPDB1
 *   - Oracle XE 18c/21c -> XEPDB1
 *   - Oracle XE 11g     -> XE
 */

// ---- CONFIG ----
$db_user = "CLECKONMART";
$db_pass = 'clecphp99';
$db_tns = 'localhost:1521/FREEPDB1';
$db_charset = 'AL32UTF8';
// ----------------

$conn = oci_connect($db_user, $db_pass, $db_tns, $db_charset);

if (!$conn) {
    $e = oci_error();
    error_log('[DB CONNECT] ' . ($e['message'] ?? 'unknown'));
    http_response_code(500);
    die('Database is currently unavailable. Please try again shortly.');
}

// Force a known date format so PHP always sees the same shape.
$s = oci_parse($conn, "ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'");
oci_execute($s);
oci_free_statement($s);