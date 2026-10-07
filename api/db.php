<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses the app's private config when available, then falls back to WordPress wp-config.php.
 * Database credentials remain server-side and are never stored in GitHub.
 */
/**
 * Ensure the public newsletter subscriber table exists.
 */
function bh_ensure_newsletter_subscribers(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bh_newsletter_subscribers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        status ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
        source VARCHAR(80) NOT NULL DEFAULT 'website',
        consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        unsubscribed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $configFile = __DIR__ . '/config.php';
    $config = null;

    if (is_file($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded) && isset($loaded['db']) && is_array($loaded['db'])) {
            $config = $loaded;
        }
    }

    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        $wpCandidates = [
            '/public_html/wp-config.php',
            dirname(__DIR__) . '/wp-config.php',
            dirname(__DIR__, 2) . '/wp-config.php'
        ];
        foreach ($wpCandidates as $wpConfigFile) {
            if (!is_file($wpConfigFile)) continue;
            $wp = file_get_contents($wpConfigFile);
            if ($wp === false) continue;

            $readWpConstant = static function(string $name) use ($wp): ?string {
                $pattern = '/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)\s*;/s';
                if (preg_match($pattern, $wp, $m)) return stripcslashes($m[1]);
                return null;
            };

            $dbName = $readWpConstant('DB_NAME');
            $dbUser = $readWpConstant('DB_USER');
            $dbPass = $readWpConstant('DB_PASSWORD');
            $dbHost = $readWpConstant('DB_HOST') ?: 'localhost';

            if ($dbName !== null && $dbUser !== null && $dbPass !== null) {
                $config = ['db' => [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass
                ]];
                break;
            }
        }
    }

    if (!is_array($config) || !isset($config['db']) || !is_array($config['db'])) {
        throw new RuntimeException('Server database configuration is missing. Add api/config.php or ensure wp-config.php is available.');
    }

    foreach (['host','name','user','pass'] as $key) {
        if (!array_key_exists($key, $config['db'])) {
            throw new RuntimeException('Missing database configuration: '.$key);
        }
    }

    $dsn = 'mysql:host='.(string)$config['db']['host'].';dbname='.(string)$config['db']['name'].';charset=utf8mb4';
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
            if ((int)$check->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE bh_organisers ADD COLUMN $field $definition");
            }
        }
    } catch (Throwable $ignored) {}

    try {
        $termsCheck = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='terms_content'")->fetchColumn();
        if ((int)$termsCheck === 0) {
            $pdo->exec("ALTER TABLE bh_organisers ADD COLUMN terms_content MEDIUMTEXT NULL");
        }
    } catch (Throwable $ignored) {}

    // Older Bubba Hub databases may have an organiser status enum that predates
    // the pending-review workflow. Leader signup creates profiles as pending.
    try {
        $statusType = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='status' LIMIT 1")->fetchColumn();
        if (is_string($statusType) && strpos($statusType, "'pending'") === false) {
            $pdo->exec("ALTER TABLE bh_organisers MODIFY COLUMN status ENUM('draft','pending','published','suspended') NOT NULL DEFAULT 'draft'");
        }
    } catch (Throwable $ignored) {}

    // Keep the deployed database schema aligned with the application role model.
    try {
        $roleType = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users' AND COLUMN_NAME='role' LIMIT 1")->fetchColumn();
        if (is_string($roleType) && strpos($roleType, "'leader'") === false) {
            $pdo->exec("ALTER TABLE bh_users MODIFY COLUMN role ENUM('family','leader','organiser','admin') NOT NULL DEFAULT 'family'");
        }
    } catch (Throwable $ignored) {}

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
