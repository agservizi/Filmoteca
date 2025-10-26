<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function pdo_connect_from_env(): ?PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = env('DB_HOST');
    $port = env('DB_PORT', '3306');
    $dbName = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS');

    if (!$host || !$dbName || !$user) {
        return null;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

    try {
        $connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $connection;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}
