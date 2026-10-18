<?php
declare(strict_types=1);
/** Staging-only optimistic-concurrency update for isolated activity 999999. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhUpdateReply(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhUpdateReply(405, ['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') bhUpdateReply(403, ['ok'=>false,'error'=>'staging_only']);
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
if ($secret === '' || !preg_match('/^Bearer (.+)$/i', $auth, $m) || !hash_equals($secret, trim($m[1]))) bhUpdateReply(401, ['ok'=>false,'error'=>'unauthorized']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if ($raw === false || strlen($raw) > 4096) bhUpdateReply(413, ['ok'=>false,'error'=>'payload_too_large']);
$input = json_decode($raw, true);
if (!is_array($input) || ($input['action'] ?? '') !== 'test_update_one' || (string)($input['id'] ?? '') !== '999999') bhUpdateReply(400, ['ok'=>false,'error'=>'invalid_action']);
$dryRun = $input['dry_run'] ?? true;
if (!is_bool($dryRun)) bhUpdateReply(400, ['ok'=>false,'error'=>'invalid_dry_run']);
$expectedHash = $input['expected_description_sha256'] ?? null;
$newDescription = $input['description'] ?? null;
if (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash) || !is_string($newDescription) ||
    strlen($newDescription) > 500 || !str_starts_with($newDescription, 'Staging-only Google Sheets sync test.')) {
    bhUpdateReply(422, ['ok'=>false,'error'=>'invalid_test_description_or_hash']);
}
function bhUpdateGate(string $key): string {
    $environment = getenv($key);
    if ($environment !== false && $environment !== '') return (string)$environment;
    $path = dirname(__DIR__).'/wp-config.php';
    if (!is_readable($path)) return '';
    $source = file_get_contents($path);
    if (!is_string($source)) return '';
    if (preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])'.preg_quote($key, '/').'=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/', $source, $m)) return $m[2];
    return '';
}
try {
    require_once __DIR__.'/db.php';
    $pdo = bh_mysql();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id,title,status,description FROM bh_activities WHERE id=? FOR UPDATE');
    $stmt->execute([999999]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['title'] !== 'BUBBA HUB SYNC TEST - DO NOT PUBLISH' || $row['status'] !== 'draft') {
        $pdo->rollBack();
        bhUpdateReply(409, ['ok'=>false,'error'=>'test_listing_not_safe']);
    }
    $trace = $pdo->prepare('SELECT sheet_row_id FROM bh_sheet_sync_records WHERE activity_id=?');
    $trace->execute([999999]);
    if ($trace->fetchColumn() !== 'staging-test-999999') {
        $pdo->rollBack();
        bhUpdateReply(409, ['ok'=>false,'error'=>'test_trace_missing']);
    }
    $currentHash = hash('sha256', (string)$row['description']);
    if (!hash_equals($currentHash, $expectedHash)) {
        $pdo->rollBack();
        bhUpdateReply(409, ['ok'=>false,'error'=>'description_conflict','current_description_sha256'=>$currentHash,'writes_performed'=>false]);
    }
    if ($dryRun) {
        $pdo->rollBack();
        bhUpdateReply(200, ['ok'=>true,'mode'=>'update_dry_run','id'=>999999,'ready'=>true,'writes_performed'=>false]);
    }
    if (bhUpdateGate('BH_SHEETS_WRITE_ENABLED') !== '1' || bhUpdateGate('BH_SHEETS_TEST_ACTIVITY_ID') !== '999999') {
        $pdo->rollBack();
        bhUpdateReply(503, ['ok'=>false,'error'=>'writes_disabled','writes_performed'=>false]);
    }
    if ($newDescription === (string)$row['description']) {
        $pdo->rollBack();
        bhUpdateReply(200, ['ok'=>true,'mode'=>'unchanged','writes_performed'=>false]);
    }
    $update = $pdo->prepare('UPDATE bh_activities SET description=?,updated_at=NOW() WHERE id=?');
    $update->execute([$newDescription,999999]);
    $pdo->commit();
    bhUpdateReply(200, ['ok'=>true,'mode'=>'test_description_updated','id'=>999999,'new_description_sha256'=>hash('sha256',$newDescription),'writes_performed'=>true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Staging Sheets test update failed: '.$e->getMessage());
    bhUpdateReply(500, ['ok'=>false,'error'=>'test_update_failed']);
}
