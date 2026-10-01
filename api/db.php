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

    // Keep public organiser profile fields available on older installs.
    try {
        $profileFields = [
            'about_content' => 'MEDIUMTEXT NULL',
            'logo_url' => 'VARCHAR(1000) NULL',
            'facebook_url' => 'VARCHAR(1000) NULL',
            'instagram_url' => 'VARCHAR(1000) NULL',
            'tiktok_url' => 'VARCHAR(1000) NULL'
        ];
        foreach ($profileFields as $field => $definition) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME=?");
            $check->execute([$field]);
            if ((int)$check->fetchColumn() === 0) $pdo->exec("ALTER TABLE bh_organisers ADD COLUMN $field $definition");
        }
    } catch (Throwable $ignored) {}

    // Keep organiser terms storage available on older installs.
    try {
        $termsCheck=$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='terms_content'")->fetchColumn();
        if ((int)$termsCheck===0) $pdo->exec("ALTER TABLE bh_organisers ADD COLUMN terms_content MEDIUMTEXT NULL");
    } catch (Throwable $ignored) {}

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

    // Account-backed Planner Pro state. The JSON document keeps Pro flexible while
    // the core planner/activity relationships remain in their dedicated tables.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bh_planner_pro_state (
            user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            state_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_planner_pro_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $ignored) {}

    return $pdo;
}
