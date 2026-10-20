<?php
declare(strict_types=1);
// Staging-only guarded fixture test. Disabled for writes unless explicitly enabled.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, ['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') reply(403, ['ok'=>false,'error'=>'staging_only']);
$secret = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secret)) reply(503, ['ok'=>false,'error'=>'sync_not_configured']);
require_once $secret;
$token = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (strlen($token) < 32 || !preg_match('/^Bearer\s+(.+)$/i', $auth, $matches) || !hash_equals($token, trim($matches[1]))) reply(401, ['ok'=>false,'error'=>'unauthorized']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) reply(413, ['ok'=>false,'error'=>'payload_too_large']);
$data = json_decode($raw, true);
if (!is_array($data) || ($data['action'] ?? '') !== 'guarded_fixture_update' || ($data['fixture_id'] ?? null) !== 999999 || !is_bool($data['dry_run'] ?? null)) reply(400, ['ok'=>false,'error'=>'invalid_request']);
if (($data['expected_price_from'] ?? null) !== null || ($data['expected_age_range'] ?? null) !== null || ($data['new_price_from'] ?? null) !== '2.00' || ($data['new_age_range'] ?? null) !== '0 - 5 years') reply(400, ['ok'=>false,'error'=>'unexpected_values']);
$dryRun = $data['dry_run'];
if (!$dryRun && getenv('BH_STAGING_SHEETS_FIXTURE_WRITE_ENABLED') !== '1') reply(403, ['ok'=>false,'error'=>'writes_disabled']);
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    if (!$dryRun) $pdo->beginTransaction();
    $sql = 'SELECT id,status,price_from,age_range FROM bh_activities WHERE id=999999 LIMIT 1' . ($dryRun ? '' : ' FOR UPDATE');
    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    if (!$row || strtolower((string)$row['status']) !== 'draft') {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reply(409, ['ok'=>false,'error'=>'fixture_not_draft_or_missing']);
    }
    $audit = $pdo->query("SHOW TABLES LIKE 'bh_sheets_sync_audit'")->fetchColumn();
    if (!$audit) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        reply(503, ['ok'=>false,'error'=>'audit_table_missing']);
    }
    $count = $pdo->query("SELECT COUNT(*) FROM bh_sheets_sync_audit WHERE listing_id=999999 AND operation='staging_fixture_update'")->fetchColumn();
    $safe = $row['price_from'] === null && $row['age_range'] === null && (int)$count === 0;
    if ($dryRun) reply(200, ['ok'=>true,'mode'=>'dry_run','fixture_id'=>999999,'safe_to_proceed'=>$safe,'current'=>['price_from'=>$row['price_from'],'age_range'=>$row['age_range']],'proposed'=>['price_from'=>'2.00','age_range'=>'0 - 5 years'],'audit_entries'=>(int)$count,'writes_performed'=>false]);
    if (!$safe) { $pdo->rollBack(); reply(409, ['ok'=>false,'error'=>'conflict_or_already_applied','writes_performed'=>false]); }
    $stmt = $pdo->prepare("UPDATE bh_activities SET price_from=:price,age_range=:age WHERE id=999999 AND status='draft' AND price_from IS NULL AND age_range IS NULL");
    $stmt->execute([':price'=>'2.00',':age'=>'0 - 5 years']);
    if ($stmt->rowCount() !== 1) { $pdo->rollBack(); reply(409, ['ok'=>false,'error'=>'concurrent_change','writes_performed'=>false]); }
    $stmt = $pdo->prepare('INSERT INTO bh_sheets_sync_audit (listing_id,operation,before_json,after_json) VALUES (999999,:operation,:before,:after)');
    $stmt->execute([':operation'=>'staging_fixture_update',':before'=>json_encode(['price_from'=>null,'age_range'=>null]),':after'=>json_encode(['price_from'=>'2.00','age_range'=>'0 - 5 years'])]);
    $pdo->commit();
    reply(200, ['ok'=>true,'mode'=>'guarded_update','fixture_id'=>999999,'writes_performed'=>true,'audit_recorded'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('BH staging guarded fixture: '.$e->getMessage());
    reply(500, ['ok'=>false,'error'=>'fixture_update_failed']);
}
