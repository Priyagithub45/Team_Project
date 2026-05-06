<?php
echo "<pre>";
echo "Trying session_start()... ";
$ok = @session_start();
echo $ok ? "OK\n" : "FAILED\n";
echo "Status: " . session_status() . " id='" . session_id() . "'\n";
echo "</pre>";