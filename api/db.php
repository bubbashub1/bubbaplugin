<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses the app's private config when available, then falls back to WordPress wp-config.php.
 * Database credentials remain server-side and are never stored in GitHub.
 */
/**
 * Verify newsletter storage was installed by the database migration.
 * Request handlers must never create or alter application tables.
 */
function bh_ensure_newsletter_subscribers(PDO $pdo): void {
    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_newsletter_subscribers'");
    if (!(int)$check->fetchColumn()) {
        throw new RuntimeException('Newsletter storage is not installed.');
    }
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

    // Connection only: schema maintenance is handled separately so public auth requests
    // are not able to fail because an ALTER TABLE/DDL operation is unavailable.

    return $pdo;
}
