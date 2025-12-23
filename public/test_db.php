<?php
require_once __DIR__ . '/../includes/web_boot.php';

$conn = db_conn();
$result = pg_query($conn, "SELECT now()");
if (!$result) {
    die("Query failed: " . pg_last_error($conn));
}

$row = pg_fetch_assoc($result);
echo "Connected ✅ Server time: " . $row['now'];
