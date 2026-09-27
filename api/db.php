<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses the existing /public_html/beta/api/config.php.
 * Database credentials remain server-side and are never stored in GitHub.
 */
function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) throw new RuntimeException('Server database configuration is missing.');
    $config = require $configFile;
    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) throw new RuntimeException('Database configuration section is missing.');
    foreach (['host','name','user','pass'] as $key) {
        if (!array_key_exists($key,$config['db'])) throw new RuntimeException('Missing database configuration: '.$key);
    }

    $dsn='mysql:host='.(string)$config['db']['host'].';dbname='.(string)$config['db']['name'].';charset=utf8mb4';
    $pdo=new PDO($dsn,(string)$config['db']['user'],(string)$config['db']['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);

    // Keep the deployed database schema aligned with the application role model.
    // Older installs did not include the 'leader' enum value, while leader
    // authentication and the portal use role='leader'. Apply this safe,
    // idempotent compatibility change automatically on first connection.
    try {
        $roleType=$pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='role' LIMIT 1")->fetchColumn();
        if (is_string($roleType) && strpos($roleType,"'leader'")===false) {
            $pdo->exec("ALTER TABLE bh_users MODIFY COLUMN role ENUM('family','leader','organiser','admin') NOT NULL DEFAULT 'family'");
        }
    } catch (Throwable $ignored) {
        // Do not prevent the application from connecting if an older database
        // account cannot inspect/alter metadata. The migration file remains
        // available for manual application.
    }

    return $pdo;
}
