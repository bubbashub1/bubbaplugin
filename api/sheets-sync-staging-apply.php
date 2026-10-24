<?php
declare(strict_types=1);
/**
 * Staging-only single-field Sheets update with compare-and-swap and audit.
 * Requires separately installed bh_sheets_sync_change_audit table.
 * Disabled unless BH_SHEETS_SYNC_STAGING_WRITES=enabled is configured server-side.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhSheetsApplyReply(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhSheetsApplyReply(405, ['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') {
    bhSheetsApplyReply(403, ['ok'=>false,'error'=>'staging_only']);
}
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) bhSheetsApplyReply(503, ['ok'=>false,'error'=>'sync_not_configured']);
require_once $secretFile;
$secret = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret) < 32) bhSheetsApplyReply(503, ['ok'=>false,'error'=>'sync_not_configured']);
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $match) || !hash_equals($secret, trim($match[1]))) {
    bhSheetsApplyReply(401, ['ok'=>false,'error'=>'unauthorized']);
}
if (getenv('BH_SHEETS_SYNC_STAGING_WRITES') !== 'enabled') {
    bhSheetsApplyReply(403, ['ok'=>false,'error'=>'staging_writes_disabled']);
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw)>4096) bhSheetsApplyReply(413, ['ok'=>false,'error'=>'payload_too_large']);
$request = json_decode($raw, true);
if (!is_array($request) || ($request['action'] ?? '') !== 'apply_one' ||
    ($request['confirmed'] ?? null) !== true ||
    !isset($request['id'],$request['field'],$request['expected'],$request['proposed']) ||
    !is_int($request['id']) || $request['id']<1 ||
    !in_array($request['field'], ['title','price_from','age_range'], true) ||
    !is_string($request['expected']) || !is_string($request['proposed']) ||
    strlen($request['expected'])>2000 || strlen($request['proposed'])>2000 ||
    trim($request['proposed']) === '') {
    bhSheetsApplyReply(400, ['ok'=>false,'error'=>'invalid_request']);
}
$pdo = null;
try {
    require_once __DIR__ . '/db.php';
    $pdo = bh_mysql();
    $pdo->beginTransaction();
    $field = $request['field']; // Fixed allowlist above.
    $stmt = $pdo->prepare('SELECT `'.$field.'` AS value FROM bh_activities WHERE id=:id FOR UPDATE');
    $stmt->execute([':id'=>$request['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        bhSheetsApplyReply(404, ['ok'=>false,'error'=>'listing_not_found']);
    }
    $current = (string)($row['value'] ?? '');
    if (!hash_equals($request['expected'], $current)) {
        $pdo->rollBack();
        bhSheetsApplyReply(409, ['ok'=>false,'error'=>'value_conflict','writes_performed'=>false]);
    }
    if ($current === $request['proposed']) {
        $pdo->rollBack();
        bhSheetsApplyReply(200, ['ok'=>true,'result'=>'already_current','writes_performed'=>false]);
    }
    $update = $pdo->prepare('UPDATE bh_activities SET `'.$field.'`=:new_value WHERE id=:id');
    $update->execute([':new_value'=>$request['proposed'], ':id'=>$request['id']]);
    $audit = $pdo->prepare("INSERT INTO bh_sheets_sync_change_audit
      (activity_id,field_name,old_value,new_value,action)
      VALUES (:id,:field,:old_value,:new_value,'apply')");
    $audit->execute([':id'=>$request['id'],':field'=>$field,':old_value'=>$current,':new_value'=>$request['proposed']]);
    $auditId = (int)$pdo->lastInsertId();
    $pdo->commit();
    bhSheetsApplyReply(200, ['ok'=>true,'result'=>'updated','audit_id'=>$auditId,'writes_performed'=>true]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Sheets staging apply failed: '.$e->getMessage());
    bhSheetsApplyReply(500, ['ok'=>false,'error'=>'apply_failed']);
}
