<?php
declare(strict_types=1);
/** Staging-only explicit-ID insertion of one isolated test activity. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhTestReply(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhTestReply(405, ['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') bhTestReply(403, ['ok'=>false,'error'=>'staging_only']);
$config = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
$secret = (string)(getenv('BH_SYNC_TOKEN') ?: (is_array($config) ? ($config['sheets_sync_token'] ?? '') : ''));
if ($secret === '') {
    $wpConfig = dirname(__DIR__).'/wp-config.php';
    if (is_readable($wpConfig)) {
        $source = file_get_contents($wpConfig);
        if (is_string($source) && preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/', $source, $m)) $secret = $m[2];
    }
}
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($secret === '' || !preg_match('/^Bearer (.+)$/i', $auth, $m) || !hash_equals($secret, trim($m[1]))) bhTestReply(401, ['ok'=>false,'error'=>'unauthorized']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw) > 4096) bhTestReply(413, ['ok'=>false,'error'=>'payload_too_large']);
$data = json_decode($raw, true);
if (!is_array($data) || ($data['action'] ?? '') !== 'create_test_one') bhTestReply(400, ['ok'=>false,'error'=>'invalid_action']);
$values = $data['values'] ?? null;
if (!is_array($values) || (string)($values['id'] ?? '') !== '999999') bhTestReply(403, ['ok'=>false,'error'=>'test_id_only']);
$title = trim((string)($values['title'] ?? ''));
if ($title !== 'BUBBA HUB SYNC TEST - DO NOT PUBLISH') bhTestReply(422, ['ok'=>false,'error'=>'test_title_required']);
$dryRun = $data['dry_run'] ?? true;
if (!is_bool($dryRun)) bhTestReply(400, ['ok'=>false,'error'=>'invalid_dry_run']);
if (!$dryRun && getenv('BH_SHEETS_WRITE_ENABLED') !== '1') bhTestReply(503, ['ok'=>false,'error'=>'writes_disabled']);
if (!$dryRun && getenv('BH_SHEETS_TEST_ACTIVITY_ID') !== '999999') bhTestReply(503, ['ok'=>false,'error'=>'test_id_gate_disabled']);
try {
    require_once __DIR__.'/db.php';
    $pdo = bh_mysql();
    $pdo->beginTransaction();
    $exists = $pdo->prepare('SELECT id FROM bh_activities WHERE id = ? FOR UPDATE');
    $exists->execute([999999]);
    if ($exists->fetchColumn()) { $pdo->rollBack(); bhTestReply(409, ['ok'=>false,'error'=>'id_already_exists']); }
    $slug = 'bubba-hub-sheet-sync-test-999999';
    $slugCheck = $pdo->prepare('SELECT id FROM bh_activities WHERE slug = ? LIMIT 1');
    $slugCheck->execute([$slug]);
    if ($slugCheck->fetchColumn()) { $pdo->rollBack(); bhTestReply(409, ['ok'=>false,'error'=>'slug_already_exists']); }
    foreach (['bh_sheet_sync_records','bh_sheet_sync_runs','bh_sheet_sync_conflicts'] as $table) {
        $pdo->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
    }
    if ($dryRun) {
        $pdo->rollBack();
        bhTestReply(200, ['ok'=>true,'mode'=>'dry_run','ready'=>true,'id'=>999999,'slug'=>$slug,'writes_performed'=>false]);
    }
    // Explicitly draft regardless of the database default; never publish test data.
    $insert = $pdo->prepare('INSERT INTO bh_activities (id,title,slug,description,status,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
    $insert->execute([999999,$title,$slug,'Staging-only Google Sheets sync test. Not a real activity.','draft']);
    // Atomic trace record; test row ID is never mapped to a real MASTER SHEET row.
    $trace = $pdo->prepare('INSERT INTO bh_sheet_sync_records (activity_id,sheet_row_id,last_synced_at) VALUES (?,?,NOW())');
    $trace->execute([999999,'staging-test-999999']);
    $pdo->commit();
    bhTestReply(201, ['ok'=>true,'mode'=>'created_test_listing','id'=>999999,'slug'=>$slug,'writes_performed'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Staging single-listing test failed: '.$e->getMessage());
    bhTestReply(500, ['ok'=>false,'error'=>'test_insert_failed']);
}
