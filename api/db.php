<?php
declare(strict_types=1);

/**
 * Bubba Hub MySQL connection.
 * Database credentials remain in server-only configuration files.
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

    // Check each server-side location. The deployment config may exist but
    // contain only GitHub/deployment settings, so it must not mask DB config
    // in a later candidate.
    $configCandidates = array_filter([
        dirname(__DIR__, 2) . '/github-deploy-config.php',
        dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) . '/github-deploy-config.php',
        (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
        '/github-deploy-config.php',
        __DIR__ . '/config.php',
    ]);

    $config = null;
    $dbConfig = [];
    $host = $name = $user = $pass = null;
    $missingKeys = ['host', 'database', 'username', 'password'];

    foreach (array_unique($configCandidates) as $configFile) {
        if (!is_file($configFile)) continue;

        $loaded = require $configFile;
        if (!is_array($loaded)) continue;

        $candidateDb = (isset($loaded['db']) && is_array($loaded['db']))
            ? $loaded['db']
            : [];

        $candidateHost = $candidateDb['host'] ?? $loaded['mysql_host'] ?? null;
        $candidateName = $candidateDb['name'] ?? $loaded['mysql_database'] ?? null;
        $candidateUser = $candidateDb['user'] ?? $loaded['mysql_username'] ?? null;
        $candidatePass = $candidateDb['pass'] ?? $loaded['mysql_password'] ?? null;

        $candidateValues = [
            'host' => $candidateHost,
            'database' => $candidateName,
            'username' => $candidateUser,
            'password' => $candidatePass,
        ];
        $missing = [];
        foreach ($candidateValues as $key => $value) {
            if (!is_string($value) || $value === '') $missing[] = $key;
        }

        // Only select a file when it contains a complete DB configuration.
        if ($missing) {
            $missingKeys = $missing;
            continue;
        }

        $config = $loaded;
        $dbConfig = $candidateDb;
        $host = $candidateHost;
        $name = $candidateName;
        $user = $candidateUser;
        $pass = $candidatePass;
        break;
    }

    if (!is_array($config)) {
        throw new RuntimeException(
            'No complete server database configuration was found. Missing: ' .
            implode(', ', $missingKeys) .
            '. Check the server-only database configuration files.'
        );
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
