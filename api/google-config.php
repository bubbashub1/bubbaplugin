<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

function bh_read_wp_constant(string $name): string {
    $candidates = [
        '/public_html/wp-config.php',
        dirname(__DIR__) . '/wp-config.php',
        dirname(__DIR__, 2) . '/wp-config.php',
    ];

    foreach ($candidates as $file) {
        if (!is_file($file)) continue;
        $contents = @file_get_contents($file);
        if ($contents === false) continue;

        $pattern = '/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)\s*;/s';
        if (preg_match($pattern, $contents, $m)) {
            return stripcslashes($m[1]);
        }
    }

    return '';
}

// Prefer the native Bubba Hub API config. The wp-config constant remains
// supported as a deployment override for existing installations.
$clientId = '';
$appConfigFile = __DIR__ . '/config.php';
if (is_file($appConfigFile)) {
    try {
        $appConfig = require $appConfigFile;
        if (is_array($appConfig)) {
            $clientId = trim((string)($appConfig['google']['client_id'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('Bubba Hub Google config could not be loaded: ' . $e->getMessage());
    }
}
$wpClientId = bh_read_wp_constant('BH_GOOGLE_CLIENT_ID');
if ($wpClientId !== '') $clientId = $wpClientId;
$facebookAppId = bh_read_wp_constant('BH_FACEBOOK_APP_ID');

echo 'window.BUBBAHUB_GOOGLE_CLIENT_ID=' . json_encode($clientId, JSON_UNESCAPED_SLASHES) . ';';
echo 'window.BUBBAHUB_FACEBOOK_APP_ID=' . json_encode($facebookAppId, JSON_UNESCAPED_SLASHES) . ';';
