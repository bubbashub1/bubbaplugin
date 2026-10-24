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
    // Until ownership/baseline rules are verified, restrict writes to the isolated staging fixture.
    if ($request['id'] !== 999999) bhSheetsApplyReply(403, ['ok'=>false,'error'=>'test_fixture_only']);
    $pdo->beginTransaction();
    // Fail closed unless a genuinely approved, environment-scoped baseline exists.
    // The isolated fixture has no approved baseline yet, so this guard deliberately
    // blocks its writes until a separate audited approval workflow is verified.
    $baseline = $pdo->prepare(
        "SELECT id, field_values_json FROM bh_sheets_sync_baselines
         WHERE environment = 'staging' AND activity_id = :id
           AND approved_by_admin_id > 0
           AND approved_at_utc <= UTC_TIMESTAMP()
           AND approval_audit_id <> ''
         ORDER BY version DESC, id DESC LIMIT 1 FOR UPDATE"
    );
    $baseline->execute([':id' => $request['id']]);
    $approved = $baseline->fetch(PDO::FETCH_ASSOC);
    if (!$approved) {
        $pdo->rollBack();
        bhSheetsApplyReply(403, ['ok'=>false,'error'=>'approved_baseline_required','writes_performed'=>false]);
    }
    $approvedFields = json_decode((string)$approved['field_values_json'], true);
    if (!is_array($approvedFields) || !array_key_exists($request['field'], $approvedFields)
        || !is_scalar($approvedFields[$request['field']])
        || (string)$approvedFields[$request['field']] !== $request['expected']) {
        $pdo->rollBack();
        bhSheetsApplyReply(409, ['ok'=>false,'error'=>'baseline_value_conflict','writes_performed'=>false]);
    }
    $field = $request['field']; // Fixed allowlist above.
    // Lock and inspect ownership before considering any update. This is an
    // additional safeguard; the fixture-only restriction above still applies.
    $ownership = $pdo->prepare(
        'SELECT a.status, a.leader_id, a.created_by, a.organiser_id, o.email AS organiser_email
         FROM bh_activities a
         LEFT JOIN bh_organisers o ON o.id = a.organiser_id
         WHERE a.id = :id FOR UPDATE'
    );
    $ownership->execute([':id'=>$request['id']]);
    $owner = $ownership->fetch(PDO::FETCH_ASSOC);
    if (!$owner) {
        $pdo->rollBack();
        bhSheetsApplyReply(404, ['ok'=>false,'error'=>'listing_not_found']);
    }
    if ((string)$owner['status'] !== 'published') {
        $pdo->rollBack();
        bhSheetsApplyReply(403, ['ok'=>false,'error'=>'non_published_protected','writes_performed'=>false]);
    }
    if ($owner['leader_id'] !== null || $owner['created_by'] !== null) {
        $pdo->rollBack();
        bhSheetsApplyReply(403, ['ok'=>false,'error'=>'explicit_owner_protected','writes_performed'=>false]);
    }
    // Legacy portal grants editing rights through organiser email. Fail
    // closed if that organiser email matches an active leader account.
    if (trim((string)($owner['organiser_email'] ?? '')) !== '') {
        $linked = $pdo->prepare(
            "SELECT 1 FROM bh_users
             WHERE role='leader' AND status='active'
               AND LOWER(TRIM(email)) = LOWER(TRIM(:email)) LIMIT 1"
        );
        $linked->execute([':email'=>$owner['organiser_email']]);
        if ($linked->fetchColumn()) {
            $pdo->rollBack();
            bhSheetsApplyReply(403, ['ok'=>false,'error'=>'leader_managed_protected','writes_performed'=>false]);
        }
    }
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
