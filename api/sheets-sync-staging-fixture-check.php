<?php
declare(strict_types=1);
/**
 * Staging-only, authenticated, read-only diagnostic for Sheets guarded repair.
 * Never writes data and does not assume that live ID 955 exists on staging.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhStageReply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    bhStageReply(405, ['ok'=>false,'error'=>'post_required']);
}
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($host !== 'staging.bubbahub.co.uk') {
    bhStageReply(403, ['ok'=>false,'error'=>'staging_only']);
}
$secretPath = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretPath)) {
    bhStageReply(503, ['ok'=>false,'error'=>'sync_not_configured']);
}
require_once $secretPath;
$token = (string)(getenv('BH_SYNC_TOKEN') ?: '');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (strlen($token) < 32 || !preg_match('/^Bearer\s+(.+)$/i', $auth, $m) || !hash_equals($token, trim($m[1]))) {
    bhStageReply(401, ['ok'=>false,'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) {
    bhStageReply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
$request = json_decode($raw, true);
if (!is_array($request) || ($request['action'] ?? null) !== 'inspect_test_fixture' ||
    ($request['dry_run'] ?? null) !== true) {
    bhStageReply(400, ['ok'=>false,'error'=>'invalid_request']);
}
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    // Only inspect the known staging-only draft ID; never modify or seed it.
    $stmt = $pdo->prepare('SELECT id, status, price_from, age_range FROM bh_activities WHERE id = :id LIMIT 1');
    $stmt->execute([':id'=>999999]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $auditExists = (bool)$pdo->query("SHOW TABLES LIKE 'bh_sheets_sync_audit'")->fetchColumn();
    bhStageReply(200, [
        'ok'=>true,
        'mode'=>'staging_read_only_fixture_inspection',
        'fixture_id'=>999999,
        'fixture_found'=>(bool)$row,
        'fixture_is_draft'=>$row ? (strtolower((string)$row['status']) === 'draft') : false,
        'current'=>$row ? ['price_from'=>$row['price_from'], 'age_range'=>$row['age_range']] : null,
        'audit_table_exists'=>$auditExists,
        'writes_performed'=>false
    ]);
} catch (Throwable $e) {
    error_log('BH staging sync fixture inspection: '.$e->getMessage());
    bhStageReply(500, ['ok'=>false,'error'=>'inspection_failed']);
}
