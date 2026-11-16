<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

/**
 * Read a public client/app ID from the hosting config without bootstrapping
 * WordPress. The ID is public by design; no private secrets are read here.
 */
function bh_read_wp_constant(string $name): string {
    if (defined($name)) {
        $value = trim((string)constant($name));
        if ($value !== '') return $value;
    }

    $value = getenv($name);
    if (is_string($value) && trim($value) !== '') return trim($value);

    // Standalone API endpoints do not necessarily execute wp-config.php.
    // Inspect only the requested setting; never include or eval wp-config.
    $candidates = array_unique(array_filter([
        !empty($_SERVER['DOCUMENT_ROOT'])
            ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/wp-config.php'
            : '',
        '/public_html/wp-config.php',
        dirname(__DIR__) . '/wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php',
    ]));

    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) continue;
        $source = @file_get_contents($file);
        if (!is_string($source) || $source === '') continue;

        // Support the putenv() and define() formats used by hosting config.
        $patterns = [
            '~^[ \\t]*putenv\\s*\\(\\s*["\\x27]' . preg_quote($name, '~') . '=([^"\\x27]*)["\\x27]\\s*\\)\\s*;~m',
            '~^[ \\t]*define\\s*\\(\\s*["\\x27]' . preg_quote($name, '~') . '["\\x27]\\s*,\\s*["\\x27](.*?)["\\x27]\\s*\\)\\s*;~m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source, $match)) {
                $value = trim(stripcslashes($match[1]));
                if ($value !== '') return $value;
            }
        }
    }

    return '';
}

$clientId = bh_read_wp_constant('BH_GOOGLE_CLIENT_ID');
$facebookAppId = bh_read_wp_constant('BH_FACEBOOK_APP_ID');

echo 'window.BUBBAHUB_GOOGLE_CLIENT_ID=' . json_encode($clientId, JSON_UNESCAPED_SLASHES) . ';';
echo 'window.BUBBAHUB_FACEBOOK_APP_ID=' . json_encode($facebookAppId, JSON_UNESCAPED_SLASHES) . ';';
