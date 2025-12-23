<?php

function load_env()
{
    static $loaded = false;
    if ($loaded) return;

    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        die('.env file not found at ' . $envPath);
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // skip comments
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // split KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // remove quotes if present
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
    }

    $loaded = true;
}

function db_conn()
{
    static $conn = null;
    if ($conn) return $conn;

    load_env();

    $required = ['DB_HOST','DB_NAME','DB_USER','DB_PASS'];
    foreach ($required as $k) {
        if (empty($_ENV[$k])) {
            die("Missing DB env vars. Check your .env file (DB_HOST/DB_NAME/DB_USER/DB_PASS).");
        }
    }

    $DB_HOST = $_ENV['DB_HOST'];
    $DB_NAME = $_ENV['DB_NAME'];
    $DB_USER = $_ENV['DB_USER'];
    $DB_PASS = $_ENV['DB_PASS'];
    $DB_PORT = $_ENV['DB_PORT'] ?? '5432';
    $DB_SSL  = $_ENV['DB_SSL'] ?? 'require';
    $DB_ENDPOINT = $_ENV['DB_ENDPOINT'] ?? '';

    $options = $DB_ENDPOINT ? " options='endpoint=$DB_ENDPOINT'" : '';

    $conn_string = "host=$DB_HOST port=$DB_PORT dbname=$DB_NAME user=$DB_USER password=$DB_PASS sslmode=$DB_SSL$options";

    $conn = pg_connect($conn_string);
    if (!$conn) {
        die("Database connection failed: " . pg_last_error());
    }

    return $conn;
}
