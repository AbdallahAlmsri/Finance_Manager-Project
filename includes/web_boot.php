<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/db.php";
$conn = db_conn();
require_once __DIR__ . "/auth.php";
