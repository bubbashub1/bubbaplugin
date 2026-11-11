<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Uses the server-only github-deploy-config.php shared by the standalone app.
 * Credentials are never stored in GitHub or exposed to the browser.
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

    // Match the repository's existing server-side configuration lookup.
    $configCandidates = array_filter([
        dirname(__DIR__, 2) . '/github-deploy-config.php',
        dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) . '/github-deploy-config.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
        '/github-deploy-config.php',
        __DIR__ . '/config.php',
    ]);

    $config = null;
    foreach (array_unique($configCandidates) as $configFile) {
        if (!is_file($configFile)) continue;
        $loaded = require $configFile;
        if (!is_array($loaded)) {
            throw new RuntimeException('Server database configuration is invalid.');
        }
        $config = $loaded;
        break;
    }

    if (!is_array($config)) {
        throw new RuntimeException('Server database configuration is missing. Configure mysql_host, mysql_database, mysql_username and mysql_password in the server-only github-deploy-config.php.');
    }

    // Support the documented shared config keys and the legacy nested db shape.
    $dbConfig = [];
    if (isset($config['db']) && is_array($config['db'])) {
        $dbConfig = $config['db'];
        $host = $dbConfig['host'] ?? null;
        $name = $dbConfig['name'] ?? null;
        $user = $dbConfig['user'] ?? null;
        $pass = $dbConfig['pass'] ?? null;
    } else {
        $host = $config['mysql_host'] ?? null;
        $name = $config['mysql_database'] ?? null;
        $user = $config['mysql_username'] ?? null;
        $pass = $config['mysql_password'] ?? null;
    }

    foreach (['host' => $host, 'database' => $name, 'username' => $user, 'password' => $pass] as $key => $value) {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Missing database configuration value: ' . $key);
        }
    }

    $charset = (string)($config['mysql_charset'] ?? ($dbConfig['charset'] ?? 'utf8mb4'));
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
        throw new RuntimeException('Invalid database character set configuration.');
    }

    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
