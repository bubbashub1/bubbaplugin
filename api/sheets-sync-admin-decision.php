<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function decision_reply(int $code,array $data):never {http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))decision_reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');decision_reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!=='https://staging.bubbahub.co.uk')decision_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$fetchSite=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''));
if($fetchSite!=='' && $fetchSite!=='same-origin')decision_reply(403,['ok'=>false,'error'=>'invalid_fetch_site']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)decision_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$raw=file_get_contents('php://input',false,null,0,4097);
if($raw===false||strlen($raw)>4096)decision_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);
if(!is_array($data)||!is_int($data['review_id']??null)||$data['review_id']<1||!in_array($data['decision']??null,['approved','rejected'],true))decision_reply(422,['ok'=>false,'error'=>'invalid_decision']);
$token=(string)($_SESSION['bh_sheets_review_csrf']??'');
if(strlen($token)!==64||!is_string($data['csrf_token']??null)||!hash_equals($token,$data['csrf_token']))decision_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$username=(string)($_SESSION['bh_admin_username']??'');
if($username===''||strlen($username)>190)decision_reply(401,['ok'=>false,'error'=>'admin_relogin_required']);
try {
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $stmt=$db->prepare("SELECT id,activity_id,field_name,website_value,sheet_value,website_fingerprint,sheet_fingerprint FROM bh_sheets_sync_review_queue WHERE id=? AND environment='staging' AND status='pending' FOR UPDATE");
 $stmt->execute([$data['review_id']]);$review=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$review){$db->rollBack();decision_reply(409,['ok'=>false,'error'=>'review_not_pending']);}
 $allowed=['title','description','age_range','price_from','booking_url'];
 if(!in_array($review['field_name'],$allowed,true)){$db->rollBack();decision_reply(409,['ok'=>false,'error'=>'invalid_review_field']);}
 $query=$db->prepare('SELECT `'.$review['field_name'].'` AS current_value FROM bh_activities WHERE id=?');
 $query->execute([$review['activity_id']]);$current=$query->fetch(PDO::FETCH_ASSOC);
 $currentValue=$current?(string)($current['current_value']??''):null;
 $original=(string)($review['website_value']??'');
 $proposed=(string)($review['sheet_value']??'');
 $unchanged=$currentValue!==null&&hash_equals((string)$review['website_fingerprint'],hash('sha256',$currentValue))&&hash_equals((string)$review['website_fingerprint'],hash('sha256',$original))&&hash_equals((string)$review['sheet_fingerprint'],hash('sha256',$proposed));
 $decision=$unchanged?$data['decision']:'stale';
 $update=$db->prepare("UPDATE bh_sheets_sync_review_queue SET status=?,reviewed_by_admin_username=?,reviewed_at_utc=UTC_TIMESTAMP() WHERE id=? AND status='pending'");
 $update->execute([$decision,$username,$review['id']]);
 if($update->rowCount()!==1)throw new RuntimeException('decision_race');
 $audit=$db->prepare('INSERT INTO bh_sheets_sync_review_audit (review_id,activity_id,field_name,decision,reviewer_username,website_fingerprint,sheet_fingerprint) VALUES (?,?,?,?,?,?,?)');
 $audit->execute([$review['id'],$review['activity_id'],$review['field_name'],$decision,$username,$review['website_fingerprint'],$review['sheet_fingerprint']]);
 $db->commit();
 decision_reply($unchanged?200:409,['ok'=>$unchanged,'status'=>$decision,'review_id'=>(int)$review['id'],'activity_writes_performed'=>0]);
} catch(Throwable $e){
 if(isset($db)&&$db->inTransaction())$db->rollBack();
 decision_reply(503,['ok'=>false,'error'=>'decision_unavailable','activity_writes_performed'=>0]);
}
