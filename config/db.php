<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

// Production overrides: create config/local.php on the server with real credentials.
// This file is gitignored and never committed.
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
}

defined('DB_HOST')    || define('DB_HOST',    'localhost');
defined('DB_NAME')    || define('DB_NAME',    'examis');
defined('DB_USER')    || define('DB_USER',    'root');
defined('DB_PASS')    || define('DB_PASS',    '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo;
    if (!isset($pdo)) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
