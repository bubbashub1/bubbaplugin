<?php
declare(strict_types=1);
require_once __DIR__.'/turnstile-settings.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$key=trim((string)bh_turnstile_setting('BUBBAHUB_TURNSTILE_SITE_KEY'));
echo json_encode(['enabled'=>$key!==''&&trim((string)bh_turnstile_setting('BUBBAHUB_TURNSTILE_SECRET_KEY'))!=='','site_key'=>$key],JSON_UNESCAPED_SLASHES);
