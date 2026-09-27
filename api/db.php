<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses the existing beta/config.php without exposing credentials.
 */

function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $candidates = [];

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/beta/config.php';
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/config.php';
    }

    $candidates[] = dirname(__DIR__) . '/config.php';

    // Resolve relative to this API file as an additional server-safe fallback.
    $apiDir = realpath(__DIR__);
    if ($apiDir !== false) {
        $candidates[] = dirname($apiDir) . '/config.php';
    }

    $configFile = null;
    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) {
            $configFile = $candidate;
            break;
        }
    }

    if ($configFile === null) {
        throw new RuntimeException('Server database configuration is missing. Checked: ' . implode(' | ', array_unique($candidates)));
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

    $dsn = 'mysql:host=' . (string)$config['db']['host']
         . ';dbname=' . (string)$config['db']['name']
         . ';charset=utf8mb4';

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
