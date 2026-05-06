<?php
include '../db.php';
echo "<pre style='background:#eef;padding:10px;'>";
echo "session_id(): " . session_id() . "\n";
echo "session_status: " . session_status() . " (1=disabled, 2=active)\n";
echo "session_name(): " . session_name() . "\n";
echo "\n--- _SESSION contents ---\n";
print_r($_SESSION);
echo "</pre>";