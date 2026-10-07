<?php
declare(strict_types=1);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store');

$clientId = '';

$wpConfigCandidates = [
    dirname(__DIR__) . '/wp-config.php',
    '/public_html/wp-config.php',
    dirname(__DIR__, 2) . '/wp-config.php',
];
foreach ($wpConfigCandidates as $wpConfigFile) {
    if (is_file($wpConfigFile)) {
        require_once $wpConfigFile;
        break;
    }
}

if (defined('BH_GOOGLE_CLIENT_ID')) {
    $clientId = (string)BH_GOOGLE_CLIENT_ID;
}
$facebookAppId = defined('BH_FACEBOOK_APP_ID') ? (string)BH_FACEBOOK_APP_ID : '';

echo 'window.BUBBAHUB_GOOGLE_CLIENT_ID=' . json_encode($clientId, JSON_UNESCAPED_SLASHES) . ';';
echo 'window.BUBBAHUB_FACEBOOK_APP_ID=' . json_encode($facebookAppId, JSON_UNESCAPED_SLASHES) . ';';