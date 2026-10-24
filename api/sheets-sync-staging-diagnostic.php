<?php
declare(strict_types=1);
/** Read-only staging sync configuration diagnostics. No secrets or paths returned. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'get_required']);
    exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'staging_only']);
    exit;
}
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'sync_not_configured']);
    exit;
}
require_once $secretFile;
$token = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($token) < 32) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'sync_not_configured']);
    exit;
}
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i, $auth, $match) || !hash_equals($token, trim($match[1]))) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}
$flag = getenv('BH_SHEETS_SYNC_STAGING_WRITES');
echo json_encode([
    'ok'=>true,
    'mode'=>'read_only_diagnostic',
    'secret_file_loaded'=>true,
    'write_flag_present'=>$flag !== false,
    'write_flag_enabled'=>$flag === 'enabled',
    'writes_performed'=>false
]);
