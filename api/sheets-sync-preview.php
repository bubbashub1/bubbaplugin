<?php
declare(strict_types=1);
/**
 * Bubba Hub Google Sheets sync preview.
 * Read-only endpoint. Does not modify the database.
 * Configure BUBBAHUB_SHEETS_SYNC_TOKEN outside the web root.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_preview_reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    bh_preview_reply(405, ['ok'=>false,'error'=>'method_not_allowed']);
}
// On staging, load the existing private token configuration from the account
// home directory (sibling of staging_html), never from the public web root.
// A preconfigured environment variable takes precedence.
$expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
if (!is_string($expected) || $expected === '') {
    $privateConfig = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
    if (is_file($privateConfig) && is_readable($privateConfig)) {
        require_once $privateConfig;
    }
    $expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!is_string($expected) || strlen($expected) < 32 ||
    !preg_match('/^Bearer ([A-Za-z0-9._~+\/-]+)$/D', $authorization, $matches) ||
    !hash_equals($expected, $matches[1])) {
    bh_preview_reply(401, ['ok'=>false,'error'=>'unauthorized']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
$raw = file_get_contents('php://input', false, null, 0, 262145);
if ($raw === false || strlen($raw) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { bh_preview_reply(400, ['ok'=>false,'error'=>'invalid_json']); }
if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows']) || !array_is_list($data['rows'])) {
    bh_preview_reply(400, ['ok'=>false,'error'=>'rows_array_required']);
}
if (count($data['rows']) > 100) {
    bh_preview_reply(422, ['ok'=>false,'error'=>'too_many_rows']);
}
foreach ($data['rows'] as $row) {
    if (!is_array($row) || array_is_list($row)) {
        bh_preview_reply(422, ['ok'=>false,'error'=>'invalid_row']);
    }
}
require_once __DIR__ . '/sheets-sync-preflight.php';
$preflight = bh_sync_preflight($data['rows']);
bh_preview_reply(200, [
    'ok'=>true,
    'mode'=>'preview_only',
    'writes_performed'=>0,
    'rows_received'=>count($data['rows']),
    'preflight'=>$preflight,
    'message'=>'Payload and identifiers checked. Database matching and conflict preview are not enabled.'
]);
