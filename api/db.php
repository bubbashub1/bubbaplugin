<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses /public_html/beta/config.php.
 * Database credentials remain server-side and are never stored in GitHub.
 */

function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $configFile = dirname(__DIR__) . '/config.php';

    if (!is_file($configFile)) {
        throw new RuntimeException('Server database configuration is missing.');
    }

    $config = require $configFile;

    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        throw new RuntimeException('Database configuration section is missing.');
    }

    foreach (['host', 'name', 'user', 'pass'] as $key) {
        if (!array_key_exists($key, $config['db'])) {
            throw new RuntimeException('Missing database configuration: ' . $key);
        }
    }

    $charset = 'utf8mb4';

    $dsn = 'mysql:host=' . (string)$config['db']['host']
         . ';dbname=' . (string)$config['db']['name']
         . ';charset=' . $charset;

    $pdo = new PDO(
        $dsn,
        (string)$config['db']['user'],
        (string)$config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
