<?php
echo "BEFORE INCLUDE: status=" . session_status() . "\n<br>";
include '../db.php';
echo "AFTER INCLUDE: status=" . session_status() . "\n<br>";
echo "session_id: " . session_id() . "\n<br>";