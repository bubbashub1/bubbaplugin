<?php
declare(strict_types=1);

/**
 * Read literal database constants from wp-config.php without including it.
 * Including wp-config.php would bootstrap WordPress and is unnecessary here.
 */
function bh_read_wp_config_constants(string $path): array {
    if (!is_file($path) || !is_readable($path)) return [];

    $source = file_get_contents($path);
    if (!is_string($source)) return [];

    $tokens = token_get_all($source);
    $values = [];
    $count = count($tokens);

    $nextMeaningful = static function (array $tokens, int &$index, int $count) {
        while ($index < $count) {
            $token = $tokens[$index];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $index++;
                continue;
            }
            return $token;
        }
        return null;
    };

    $decodeLiteral = static function (string $literal): ?string {
        $quote = $literal[0] ?? '';
        if (($quote !== "'" && $quote !== '"') || substr($literal, -1) !== $quote) return null;
        $inner = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
        }
        // Only plain, non-interpolated string tokens are accepted.
        return stripcslashes($inner);
    };

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'define') continue;

        $i++;
        if ($nextMeaningful($tokens, $i, $count) !== '(') continue;
        $i++;
        $nameToken = $nextMeaningful($tokens, $i, $count);
        if (!is_array($nameToken) || $nameToken[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $constantName = $decodeLiteral($nameToken[1]);
        if (!is_string($constantName)) continue;

        $i++;
        if ($nextMeaningful($tokens, $i, $count) !== ',') continue;
        $i++;
        $valueToken = $nextMeaningful($tokens, $i, $count);
        if (!is_array($valueToken) || $valueToken[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $value = $decodeLiteral($valueToken[1]);
        if (is_string($value)) $values[$constantName] = $value;
    }

    return $values;
}

/**
 * Bubba Hub MySQL connection.
 * Database credentials remain in server-only configuration files.
 */
function bh_ensure_newsletter_subscribers(PDO $pdo): void {
    // Newsletter storage is provisioned by a controlled database migration.
    // Public requests must never create or alter application tables.
    $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_newsletter_subscribers'");
    if (!(int)$check->fetchColumn()) {
        throw new RuntimeException('Newsletter storage is not installed.');
    }
}

function bh_mysql(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    // CLI jobs (such as deployment migrations) do not set DOCUMENT_ROOT.
    // In that context, api/db.php lives directly under the site's document root.
    if ($documentRoot === '') {
        $documentRoot = dirname(__DIR__);
    }
    $environmentName = $documentRoot !== '' ? basename($documentRoot) : '';
    $environmentConfig = ($environmentName !== '' && $documentRoot !== '')
        ? dirname($documentRoot) . '/database-config-' . $environmentName . '.php'
        : null;

    // Prefer an environment-specific private DB file, then inspect the
    // shared deployment config and legacy locations. A deployment config
    // without DB keys must never prevent later candidates being checked.
    $configCandidates = array_filter([
        $environmentConfig,
        dirname(__DIR__, 2) . '/github-deploy-config.php',
        dirname($documentRoot) . '/github-deploy-config.php',
        $documentRoot . '/github-deploy-config.php',
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

    // Last resort: read literal DB constants from this environment's WordPress
    // config. Never include wp-config.php, because it bootstraps WordPress.
    if (!is_array($config) && $documentRoot !== '') {
        $wpConfigCandidates = [
            $documentRoot . '/wp-config.php',
            dirname($documentRoot) . '/wp-config.php',
        ];

        foreach (array_unique($wpConfigCandidates) as $wpConfigFile) {
            $wpValues = bh_read_wp_config_constants($wpConfigFile);
            $wpHost = $wpValues['DB_HOST'] ?? null;
            $wpName = $wpValues['DB_NAME'] ?? null;
            $wpUser = $wpValues['DB_USER'] ?? null;
            $wpPass = $wpValues['DB_PASSWORD'] ?? null;

            if (
                is_string($wpHost) && $wpHost !== '' &&
                is_string($wpName) && $wpName !== '' &&
                is_string($wpUser) && $wpUser !== '' &&
                is_string($wpPass) && $wpPass !== ''
            ) {
                $host = $wpHost;
                $name = $wpName;
                $user = $wpUser;
                $pass = $wpPass;
                $config = ['mysql_charset' => $wpValues['DB_CHARSET'] ?? 'utf8mb4'];
                $dbConfig = [];
                break;
            }
        }
    }

    if (!is_array($config)) {
        throw new RuntimeException(
            'No complete server database configuration was found. Missing: ' .
            implode(', ', $missingKeys) .
            '. Check the private database configuration or WordPress config for this environment.'
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
