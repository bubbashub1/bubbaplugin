<?php
declare(strict_types=1);
/**
 * Staging-only, authenticated, read-only preview of one proposed Sheets change.
 * No SQL write statements. Fail closed outside staging.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function bhSheetsChangeReply(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bhSheetsChangeReply(405, ['ok'=>false,'error'=>'post_required']);
}
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== 'staging.bubbahub.co.uk') {
    bhSheetsChangeReply(403, ['ok'=>false,'error'=>'staging_only']);
}
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) {
    bhSheetsChangeReply(503, ['ok'=>false,'error'=>'sync_not_configured']);
}
require_once $secretFile;
$secret = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret) < 32) {
    bhSheetsChangeReply(503, ['ok'=>false,'error'=>'sync_not_configured']);
}
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m) || !hash_equals($secret, trim($m[1]))) {
    bhSheetsChangeReply(401, ['ok'=>false,'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) {
    bhSheetsChangeReply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
$body = json_decode($raw, true);
$allowed = ['title', 'price_from', 'age_range'];
if (!is_array($body) ||
    ($body['action'] ?? '') !== 'preview_change' ||
    ($body['dry_run'] ?? null) !== true ||
    !isset($body['id'], $body['field'], $body['expected'], $body['proposed']) ||
    !is_int($body['id']) || $body['id'] < 1 ||
    !is_string($body['field']) || !in_array($body['field'], $allowed, true) ||
    !is_string($body['expected']) || !is_string($body['proposed']) ||
    strlen($body['expected']) > 2000 || strlen($body['proposed']) > 2000 ||
    trim($body['proposed']) === '') {
    bhSheetsChangeReply(400, ['ok'=>false,'error'=>'invalid_change_request']);
}
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    // Column name is selected from the fixed allowlist above, never arbitrary user input.
    $field = $body['field'];
    $stmt = $pdo->prepare('SELECT `' . $field . '` AS current_value FROM bh_activities WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $body['id'], PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) bhSheetsChangeReply(404, ['ok'=>false,'error'=>'listing_not_found']);
    $current = (string)($row['current_value'] ?? '');
    $matches = hash_equals($body['expected'], $current);
    bhSheetsChangeReply(200, [
        'ok'=>true, 'mode'=>'staging_read_only_change_preview',
        'id'=>$body['id'], 'field'=>$field,
        'current'=>$current, 'proposed'=>$body['proposed'],
        'matches_expected'=>$matches, 'conflict'=>!$matches,
        'eligible_for_review'=>$matches && $current !== $body['proposed'],
        'writes_performed'=>false
    ]);
} catch (Throwable $e) {
    error_log('Sheets staging change preview failed: '.$e->getMessage());
    bhSheetsChangeReply(500, ['ok'=>false,'error'=>'preview_failed']);
}
