<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 *
 * Environment-specific credentials live outside the public web root in:
 *   <hosting-home>/database-config-public_html.php
 *   <hosting-home>/database-config-staging_html.php
 *
 * The shared github-deploy-config.php remains available for deployment/admin
 * settings. Database credentials are never stored in GitHub or sent to clients.
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

    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), DIRECTORY_SEPARATOR);
    $siteDirectory = $documentRoot !== '' ? basename($documentRoot) : basename(dirname(__DIR__));
    $hostingHome = dirname(__DIR__, 2);

    // Environment-specific config is outside the public web root. The shared
    // config is loaded as a fallback for existing deployment/admin settings.
    $environmentConfigFile = $hostingHome . '/database-config-' . $siteDirectory . '.php';
    $sharedConfigCandidates = array_filter([
        $hostingHome . '/github-deploy-config.php',
        dirname($documentRoot) . '/github-deploy-config.php',
        $documentRoot . '/github-deploy-config.php',
        '/github-deploy-config.php',
    ]);

    $config = [];
    if (is_file($environmentConfigFile)) {
        $loaded = require $environmentConfigFile;
        if (!is_array($loaded)) {
            throw new RuntimeException('Environment database configuration is invalid.');
        }
        $config = $loaded;
    }

    foreach (array_unique($sharedConfigCandidates) as $configFile) {
        if (!is_file($configFile)) continue;
        $loaded = require $configFile;
        if (!is_array($loaded)) {
            throw new RuntimeException('Shared server configuration is invalid.');
        }
        // Environment-specific values always take precedence.
        $config = array_replace_recursive($loaded, $config);
        break;
    }

    $dbConfig = isset($config['db']) && is_array($config['db']) ? $config['db'] : [];
    // Flat environment-specific keys take precedence over the legacy nested
    // db shape so either supported format can safely override shared values.
    $host = $config['mysql_host'] ?? ($dbConfig['host'] ?? null);
    $name = $config['mysql_database'] ?? ($dbConfig['name'] ?? null);
    $user = $config['mysql_username'] ?? ($dbConfig['user'] ?? null);
    $pass = $config['mysql_password'] ?? ($dbConfig['pass'] ?? null);

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
