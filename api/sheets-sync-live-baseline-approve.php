<?php
declare(strict_types=1);
/**
 * Explicit, administrator-approved initial production baselines.
 * Captures CURRENT website values only. Never writes bh_activities.
 * Disabled by default; limited to five listings per request.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhLiveBaselineReply(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body + ['activity_writes_performed'=>0], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'bubbahub.co.uk')
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/sheets-sync-baseline-guard.php';
if (empty($_SESSION['bh_live_baseline_csrf']))
    $_SESSION['bh_live_baseline_csrf']=bin2hex(random_bytes(32));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET')
    bhLiveBaselineReply(200,['ok'=>true,'enabled'=>getenv('BH_SHEETS_LIVE_BASELINE_APPROVALS')==='enabled',
        'csrf_token'=>$_SESSION['bh_live_baseline_csrf'],'mode'=>'preview_only_no_writes']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST')
    bhLiveBaselineReply(405,['ok'=>false,'error'=>'post_required']);
if ((string)($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://bubbahub.co.uk')
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'invalid_origin']);
$fetchSite=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite!=='' && $fetchSite!=='same-origin')
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'invalid_fetch_site']);
if (getenv('BH_SHEETS_LIVE_BASELINE_APPROVALS') !== 'enabled')
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'approvals_disabled']);
$raw=file_get_contents('php://input',false,null,0,4097);
if (!is_string($raw)||strlen($raw)>4096)
    bhLiveBaselineReply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);
$ids=$data['activity_ids']??null;
$fields=$data['fields']??null;
$expected=$data['expected_values']??null;

$allowed=['title','price_from','age_range'];
if (!is_array($data)||($data['confirmed']??null)!==true||
    !is_array($ids)||!array_is_list($ids)||count($ids)<1||count($ids)>5||
    !is_array($fields)||!array_is_list($fields)||count($fields)<1||count($fields)>3||
    !is_array($expected)||
    count(array_unique($fields))!==count($fields)||
    array_diff($fields,$allowed)||
    count(array_unique($ids))!==count($ids)||
    count(array_filter($ids,static fn($id)=>!is_int($id)||$id<1))>0||
    !is_string($data['csrf_token']??null)||
    !hash_equals((string)$_SESSION['bh_live_baseline_csrf'],$data['csrf_token']))
    bhLiveBaselineReply(422,['ok'=>false,'error'=>'invalid_request_or_csrf']);
$username=(string)($_SESSION['bh_admin_username']??'');
if ($username===''||strlen($username)>190)
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'verified_admin_required']);
// Confirm the session username against the server-side admin credentials.
$configCandidates=array_filter([
    dirname(__DIR__,2).'/github-deploy-config.php',
    dirname((string)($_SERVER['DOCUMENT_ROOT']??'')).'/github-deploy-config.php',
    (string)($_SERVER['DOCUMENT_ROOT']??'').'/github-deploy-config.php',
    '/github-deploy-config.php'
]);
$adminConfig=null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $adminConfig=require $candidate;
        break;
    }
}
if (!is_array($adminConfig)||
    !is_string($adminConfig['admin_username']??null)||
    !hash_equals($adminConfig['admin_username'],$username))
    bhLiveBaselineReply(403,['ok'=>false,'error'=>'verified_admin_identity_required']);
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    $db->beginTransaction();
    $insert=$db->prepare("INSERT INTO bh_sheets_sync_live_baselines
      (environment,activity_id,version,approved_by_admin_id,approved_at_utc,
       field_values_json,website_fingerprint,sheet_fingerprint,approval_audit_id)
      VALUES ('live',:id,1,1,UTC_TIMESTAMP(),:fields,:website,:sheet,:audit)");
    $created=[];
    foreach ($ids as $id) {
        $stmt=$db->prepare("SELECT a.id,a.status,a.leader_id,a.created_by,a.organiser_id,
            a.title,a.price_from,a.age_range,o.email AS organiser_email
            FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id
            WHERE a.id=? FOR UPDATE");
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row||$row['status']!=='published'||$row['leader_id']!==null||$row['created_by']!==null) {
            $db->rollBack();
            bhLiveBaselineReply(409,['ok'=>false,'error'=>'listing_not_eligible','activity_id'=>$id]);
        }
        if (trim((string)($row['organiser_email']??''))!=='') {
            $owner=$db->prepare("SELECT 1 FROM bh_users WHERE role='leader' AND status='active'
              AND LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1");
            $owner->execute([$row['organiser_email']]);
            if ($owner->fetchColumn()) {
                $db->rollBack();
                bhLiveBaselineReply(409,['ok'=>false,'error'=>'leader_managed_protected','activity_id'=>$id]);
            }
        }
        $existing=$db->prepare("SELECT id FROM bh_sheets_sync_live_baselines
          WHERE environment='live' AND activity_id=? LIMIT 1 FOR UPDATE");
        $existing->execute([$id]);
        if ($existing->fetchColumn()) {
            $db->rollBack();
            bhLiveBaselineReply(409,['ok'=>false,'error'=>'baseline_already_exists','activity_id'=>$id]);
        }
        $values=[];
        foreach ($fields as $field) {
            $value=(string)($row[$field]??'');
            if (trim($value)==='') {
                $db->rollBack();
                bhLiveBaselineReply(409,['ok'=>false,'error'=>'blank_website_field','activity_id'=>$id,'field'=>$field]);
            }
            // Website value must match the explicit admin-reviewed snapshot.
            $key=(string)$id;
            $source=$db->prepare("SELECT sheet_value FROM bh_sheets_sync_live_sheet_snapshots
                WHERE activity_id=? AND field_name=?
                  AND received_at_utc >= UTC_TIMESTAMP() - INTERVAL 24 HOUR FOR UPDATE");
            $source->execute([$id,$field]);
            $sheetValue=$source->fetchColumn();
            if (!isset($expected[$key])||!is_array($expected[$key])||
                !is_string($expected[$key][$field]??null)||
                !is_string($sheetValue)||
                !hash_equals($value,$expected[$key][$field])||
                !bh_sync_field_equal($field,$value,$sheetValue)) {
                $db->rollBack();
                bhLiveBaselineReply(409,['ok'=>false,'error'=>'baseline_source_mismatch',
                    'activity_id'=>$id,'field'=>$field]);
            }
            $values[$field]=$value;
        }
        $json=json_encode($values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $fingerprint=hash('sha256',$json);
        $insert->execute([':id'=>$id,':fields'=>$json,':website'=>$fingerprint,
          ':sheet'=>$fingerprint,':audit'=>'live:initial:'.$id.':'.bin2hex(random_bytes(12))]);
        $created[]=$id;
    }
    $db->commit();
    bhLiveBaselineReply(200,['ok'=>true,'result'=>'initial_baselines_approved',
      'baseline_count'=>count($created),'activity_ids'=>$created]);
} catch(Throwable $e) {
    if (isset($db)&&$db->inTransaction()) $db->rollBack();
    error_log('Live baseline approval failed: '.$e->getMessage());
    bhLiveBaselineReply(503,['ok'=>false,'error'=>'baseline_approval_unavailable']);
}
