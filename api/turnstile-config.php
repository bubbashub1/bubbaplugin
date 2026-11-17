<?php
declare(strict_types=1);
require_once __DIR__.'/turnstile-settings.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$site=trim((string)bh_turnstile_setting('BUBBAHUB_TURNSTILE_SITE_KEY'));
$secret=trim((string)bh_turnstile_setting('BUBBAHUB_TURNSTILE_SECRET_KEY'));
$enabled=$site!==''&&$secret!=='';
echo json_encode(['enabled'=>$enabled,'site_key'=>$enabled?$site:''],JSON_UNESCAPED_SLASHES);
