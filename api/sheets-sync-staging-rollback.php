<?php
declare(strict_types=1);
/**
 * Staging-only rollback of a previously audited Sheets change.
 * Fail-closed unless server flag is enabled; transactional and conflict-aware.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhSheetsRollbackReply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhSheetsRollbackReply(405,['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') {
    bhSheetsRollbackReply(403,['ok'=>false,'error'=>'staging_only']);
}
$secretFile = dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) bhSheetsRollbackReply(503,['ok'=>false,'error'=>'sync_not_configured']);
require_once $secretFile;
$secret = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret)<32) bhSheetsRollbackReply(503,['ok'=>false,'error'=>'sync_not_configured']);
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i',$auth,$match) || !hash_equals($secret,trim($match[1]))) {
    bhSheetsRollbackReply(401,['ok'=>false,'error'=>'unauthorized']);
}
if (getenv('BH_SHEETS_SYNC_STAGING_WRITES') !== 'enabled') {
    bhSheetsRollbackReply(403,['ok'=>false,'error'=>'staging_writes_disabled']);
}
$raw = file_get_contents('php://input',false,null,0,4097);
if (!is_string($raw) || strlen($raw)>4096) bhSheetsRollbackReply(413,['ok'=>false,'error'=>'payload_too_large']);
$request = json_decode($raw,true);
if (!is_array($request) || ($request['action']??null)!=='rollback_one' ||
    ($request['confirmed']??null)!==true || !isset($request['audit_id']) ||
    !is_int($request['audit_id']) || $request['audit_id']<1) {
    bhSheetsRollbackReply(400,['ok'=>false,'error'=>'invalid_request']);
}
$pdo = null;
try {
    require_once __DIR__.'/db.php';
    $pdo = bh_mysql();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id,activity_id,field_name,old_value,new_value,action
      FROM bh_sheets_sync_change_audit WHERE id=:id FOR UPDATE');
    $stmt->execute([':id'=>$request['audit_id']]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$entry || $entry['action']!=='apply') {
        $pdo->rollBack();
        bhSheetsRollbackReply(404,['ok'=>false,'error'=>'original_audit_not_found']);
    }
    $field = (string)$entry['field_name'];
    if (!in_array($field,['title','price_from','age_range'],true)) {
        $pdo->rollBack();
        bhSheetsRollbackReply(409,['ok'=>false,'error'=>'field_not_allowed']);
    }
    $already = $pdo->prepare("SELECT id FROM bh_sheets_sync_change_audit
      WHERE related_audit_id=:id AND action='rollback' LIMIT 1");
    $already->execute([':id'=>$request['audit_id']]);
    if ($already->fetch()) {
        $pdo->rollBack();
        bhSheetsRollbackReply(409,['ok'=>false,'error'=>'already_rolled_back']);
    }
    $currentStmt = $pdo->prepare('SELECT `'.$field.'` AS value FROM bh_activities WHERE id=:id FOR UPDATE');
    $currentStmt->execute([':id'=>$entry['activity_id']]);
    $row = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $pdo->rollBack();
        bhSheetsRollbackReply(404,['ok'=>false,'error'=>'listing_not_found']);
    }
    if (!hash_equals((string)($entry['new_value']??''),(string)($row['value']??''))) {
        $pdo->rollBack();
        bhSheetsRollbackReply(409,['ok'=>false,'error'=>'rollback_conflict','writes_performed'=>false]);
    }
    $update = $pdo->prepare('UPDATE bh_activities SET `'.$field.'`=:value WHERE id=:id');
    $update->execute([':value'=>$entry['old_value'],':id'=>$entry['activity_id']]);
    $audit = $pdo->prepare("INSERT INTO bh_sheets_sync_change_audit
      (activity_id,field_name,old_value,new_value,action,related_audit_id)
      VALUES (:id,:field,:old,:new,'rollback',:related)");
    $audit->execute([':id'=>$entry['activity_id'],':field'=>$field,
      ':old'=>$entry['new_value'],':new'=>$entry['old_value'],':related'=>$request['audit_id']]);
    $rollbackId = (int)$pdo->lastInsertId();
    $pdo->commit();
    bhSheetsRollbackReply(200,['ok'=>true,'result'=>'rolled_back','audit_id'=>$rollbackId,'writes_performed'=>true]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Sheets staging rollback failed: '.$e->getMessage());
    bhSheetsRollbackReply(500,['ok'=>false,'error'=>'rollback_failed']);
}
