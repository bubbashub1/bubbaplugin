<?php
declare(strict_types=1);
/**
 * Staging-only explicit baseline approval. Disabled by default.
 * No bh_activities updates. Never accept baseline values from client.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhApprovalReply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data + ['activity_writes_performed'=>0], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk')
    bhApprovalReply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
    bhApprovalReply(405,['ok'=>false,'error'=>'post_required']);
if ((string)($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://staging.bubbahub.co.uk')
    bhApprovalReply(403,['ok'=>false,'error'=>'invalid_origin']);
$site = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($site !== '' && $site !== 'same-origin')
    bhApprovalReply(403,['ok'=>false,'error'=>'invalid_fetch_site']);
// This separate approval flag is NOT the staging activity-write flag.
if (getenv('BH_SHEETS_BASELINE_APPROVALS') !== 'enabled')
    bhApprovalReply(403,['ok'=>false,'error'=>'baseline_approvals_disabled']);
$adminId = filter_var(getenv('BH_SHEETS_BASELINE_ADMIN_ID'), FILTER_VALIDATE_INT,
    ['options'=>['min_range'=>1]]);
// Match the authenticated username to the same server-side credentials
// used by admin-auth.php. A numeric environment ID alone is not proof.
$configCandidates = array_filter([
    dirname(__DIR__, 2) . '/github-deploy-config.php',
    dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) . '/github-deploy-config.php',
    (string)($_SERVER['DOCUMENT_ROOT'] ?? '') . '/github-deploy-config.php',
    '/github-deploy-config.php'
]);
$config = null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $config = require $candidate;
        break;
    }
}
$configAdminId = is_array($config) ? filter_var($config['sheets_baseline_admin_id'] ?? null,
    FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) : false;
$sessionUsername = (string)($_SESSION['bh_admin_username'] ?? '');
if (!$adminId || !$configAdminId || (int)$adminId !== (int)$configAdminId
    || !is_array($config) || !is_string($config['admin_username'] ?? null)
    || $sessionUsername === '' || !hash_equals($config['admin_username'], $sessionUsername))
    bhApprovalReply(403,['ok'=>false,'error'=>'verified_admin_identity_required']);
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw)>4096)
    bhApprovalReply(413,['ok'=>false,'error'=>'payload_too_large']);
$data = json_decode($raw, true);
if (!is_array($data) || !is_int($data['review_id'] ?? null) ||
    $data['review_id'] < 1 || ($data['confirmed'] ?? null) !== true)
    bhApprovalReply(422,['ok'=>false,'error'=>'invalid_request']);
$csrf = (string)($_SESSION['bh_sheets_review_csrf'] ?? '');
if (strlen($csrf)!==64 || !is_string($data['csrf_token'] ?? null) ||
    !hash_equals($csrf, $data['csrf_token']))
    bhApprovalReply(403,['ok'=>false,'error'=>'invalid_csrf']);
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    $db->beginTransaction();
    $q=$db->prepare("SELECT id,activity_id,field_name,website_value,sheet_value,
        website_fingerprint,sheet_fingerprint,status
        FROM bh_sheets_sync_review_queue
        WHERE id=:id AND environment='staging' AND status='pending' FOR UPDATE");
    $q->execute([':id'=>$data['review_id']]);
    $review=$q->fetch(PDO::FETCH_ASSOC);
    if (!$review) { $db->rollBack(); bhApprovalReply(409,['ok'=>false,'error'=>'review_not_pending']); }
    $field=(string)$review['field_name'];
    if (!in_array($field,['title','price_from','age_range'],true)) {
        $db->rollBack(); bhApprovalReply(409,['ok'=>false,'error'=>'field_not_allowed']);
    }
    // Until complete ownership verification is integrated, do not approve
    // baselines for any real listing. Fixture is a draft and also fails closed.
    if ((int)$review['activity_id'] !== 999999) {
        $db->rollBack(); bhApprovalReply(403,['ok'=>false,'error'=>'fixture_only']);
    }
    $stmt=$db->prepare('SELECT status, `'.$field.'` AS current_value FROM bh_activities WHERE id=:id FOR UPDATE');
    $stmt->execute([':id'=>$review['activity_id']]);
    $listing=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$listing || $listing['status']!=='published') {
        $db->rollBack(); bhApprovalReply(403,['ok'=>false,'error'=>'non_published_protected']);
    }
    $current=(string)($listing['current_value'] ?? '');
    $website=(string)($review['website_value'] ?? '');
    $sheet=(string)($review['sheet_value'] ?? '');
    if (trim($sheet)==='' || !hash_equals((string)$review['website_fingerprint'],hash('sha256',$website))
        || !hash_equals((string)$review['website_fingerprint'],hash('sha256',$current))
        || !hash_equals((string)$review['sheet_fingerprint'],hash('sha256',$sheet))) {
        $db->rollBack(); bhApprovalReply(409,['ok'=>false,'error'=>'stale_or_blank_candidate']);
    }
    $previous=$db->prepare("SELECT version FROM bh_sheets_sync_baselines
        WHERE environment='staging' AND activity_id=:id
        ORDER BY version DESC LIMIT 1 FOR UPDATE");
    $previous->execute([':id'=>$review['activity_id']]);
    $v=$previous->fetchColumn();
    if ($v !== false) {
        $db->rollBack(); bhApprovalReply(409,['ok'=>false,'error'=>'existing_baseline_requires_version_review']);
    }
    // Approved baseline captures WEBSITE value, not incoming Sheet value.
    $fields=json_encode([$field=>$current],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    $auditId='review:'.(int)$review['id'];
    $insert=$db->prepare("INSERT INTO bh_sheets_sync_baselines
      (environment,activity_id,version,approved_by_admin_id,approved_at_utc,
       field_values_json,website_fingerprint,sheet_fingerprint,approval_audit_id)
      VALUES ('staging',:id,1,:admin,UTC_TIMESTAMP(),:fields,:website,:sheet,:audit)");
    $insert->execute([':id'=>$review['activity_id'],':admin'=>$adminId,':fields'=>$fields,
        ':website'=>$review['website_fingerprint'],':sheet'=>$review['sheet_fingerprint'],':audit'=>$auditId]);
    $baselineId=(int)$db->lastInsertId();
    // Audit approval within the same transaction; failure rolls everything back.
    $audit=$db->prepare("INSERT INTO bh_sheets_sync_baseline_approval_audit
        (baseline_id,review_id,activity_id,admin_id,admin_username,
         website_fingerprint,sheet_fingerprint)
        VALUES (:baseline,:review,:activity,:admin,:username,:website,:sheet)");
    $audit->execute([
        ':baseline'=>$baselineId, ':review'=>$review['id'],
        ':activity'=>$review['activity_id'], ':admin'=>$adminId,
        ':username'=>$sessionUsername, ':website'=>$review['website_fingerprint'],
        ':sheet'=>$review['sheet_fingerprint']
    ]);
    $db->commit();
    bhApprovalReply(200,['ok'=>true,'result'=>'baseline_approved',
        'baseline_id'=>$baselineId,'review_id'=>(int)$review['id'],'baseline_writes_performed'=>1]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Staging baseline approval failed: '.$e->getMessage());
    bhApprovalReply(503,['ok'=>false,'error'=>'baseline_approval_unavailable']);
}
