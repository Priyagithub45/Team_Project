<?php
/**
 * logout.php - Cleckhuddesfax Online Mart
 * 
 * This page logs the user out.
 * It destroys their session (removes them from memory)
 * and sends them back to the login page.
 */

// Start the session so we can access and destroy it
session_start();

// -------------------------------------------------------
// Destroy all session data
// This removes user_id, user_name, user_role, etc.
// After this, the website no longer knows who is logged in.
// -------------------------------------------------------
session_unset();    // Remove all session variables
session_destroy();  // Destroy the session itself

// -------------------------------------------------------
// Send the user to the login page
// -------------------------------------------------------
header("Location: login.php");
exit();
?>
