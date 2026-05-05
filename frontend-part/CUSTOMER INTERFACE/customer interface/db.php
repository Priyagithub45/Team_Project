<?php
/**
 * db.php - Database Connection File
 * 
 * This file connects PHP to the Oracle database.
 * Every page that needs the database will include this file.
 * 
 * HOW TO USE: Just add this line at the top of any PHP file:
 *   include 'db.php';
 * After that, use $conn to run queries.
 */

// -------------------------------------------------------
// STEP 1: Set your Oracle database connection details here
// Ask your friend who set up Oracle for these values.
// -------------------------------------------------------
$db_host     = "localhost";          // Usually "localhost" or an IP address
$db_port     = "1521";              // Oracle default port
$db_service  = "XE";               // Service name or SID (e.g. XE, ORCL)
$db_username = "your_username";     // Oracle username (e.g. SYSTEM or your schema name)
$db_password = "your_password";     // Oracle password

// -------------------------------------------------------
// STEP 2: Build the connection string
// This is the format Oracle needs: //host:port/service
// -------------------------------------------------------
$connection_string = "//{$db_host}:{$db_port}/{$db_service}";

// -------------------------------------------------------
// STEP 3: Try to connect to the database
// oci_connect() is the PHP function for Oracle connections
// -------------------------------------------------------
$conn = oci_connect($db_username, $db_password, $connection_string);

// -------------------------------------------------------
// STEP 4: Check if the connection worked
// If it failed, stop the page and show an error message
// -------------------------------------------------------
if (!$conn) {
    // Get details about what went wrong
    $error = oci_error();
    // Stop execution and show the error (only for development)
    die("Database connection failed: " . htmlspecialchars($error['message']));
}
// If we get here, the connection was successful!
?>
