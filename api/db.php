<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 *
 * Uses the existing beta/config.php server-side configuration.
 * Expected config keys:
 *   mysql_host
 *   mysql_database
 *   mysql_username
 *   mysql_password
 * Optional:
 *   mysql_charset (defaults to utf8mb4)
 *
 * Credentials are deliberately NOT stored in GitHub.
 */

function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $configFile = dirname(__DIR__) . '/config.php';

    if (!is_file($configFile)) {
        throw new RuntimeException('Server database configuration is missing.');
    }

    $config = require $configFile;

    if (!is_array($config)) {
        throw new RuntimeException('Server database configuration must return an array.');
    }

    foreach (['mysql_host', 'mysql_database', 'mysql_username', 'mysql_password'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException('Missing MySQL configuration: ' . $key);
        }
    }

    $charset = (string)($config['mysql_charset'] ?? 'utf8mb4');

    $dsn = 'mysql:host=' . $config['mysql_host']
         . ';dbname=' . $config['mysql_database']
         . ';charset=' . $charset;

    $pdo = new PDO(
        $dsn,
        (string)$config['mysql_username'],
        (string)$config['mysql_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
