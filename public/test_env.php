<?php
require_once __DIR__ . '/../includes/db.php';

load_env();

header('Content-Type: text/plain');

echo "DB_HOST from _ENV = " . ($_ENV['DB_HOST'] ?? '(missing)') . PHP_EOL;
echo "DB_NAME from _ENV = " . ($_ENV['DB_NAME'] ?? '(missing)') . PHP_EOL;

$conn = db_conn();
echo $conn ? "Connected OK\n" : "Connection FAILED\n";
