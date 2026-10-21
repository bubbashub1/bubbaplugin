<?php
declare(strict_types=1);
// Staging-only admin rollback: refuse if any saved new value has changed.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function rollback_reply(int $code,array $body):never{http_response_code($code);echo json_encode($body,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))rollback_reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')rollback_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(($_SERVER['HTTP_ORIGIN']??'')!=='https://staging.bubbahub.co.uk')rollback_reply(403,['ok'=>false,'error'=>'invalid_origin']);
if(strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin'))!=='same-origin')rollback_reply(403,['ok'=>false,'error'=>'invalid_fetch_site']);
$raw=file_get_contents('php://input',false,null,0,4097);if($raw===false||strlen($raw)>4096)rollback_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);if(!is_array($data)||!is_int($data['run_id']??null)||$data['run_id']<1||($data['confirm']??null)!=='ROLLBACK_STAGING_RUN')rollback_reply(422,['ok'=>false,'error'=>'invalid_request']);
$csrf=(string)($_SESSION['bh_sheets_review_csrf']??'');if(strlen($csrf)!==64||!is_string($data['csrf_token']??null)||!hash_equals($csrf,$data['csrf_token']))rollback_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$admin=(string)($_SESSION['bh_admin_username']??'');if($admin===''||strlen($admin)>190)rollback_reply(401,['ok'=>false,'error'=>'admin_relogin_required']);
$runId=$data['run_id'];
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $run=$db->prepare('SELECT id FROM bh_sheets_override_runs WHERE id=? FOR UPDATE');$run->execute([$runId]);if(!$run->fetchColumn()){$db->rollBack();rollback_reply(404,['ok'=>false,'error'=>'run_not_found']);}
 $prior=$db->prepare('SELECT id FROM bh_sheets_override_rollbacks WHERE run_id=?');$prior->execute([$runId]);if($prior->fetchColumn()){$db->rollBack();rollback_reply(409,['ok'=>false,'error'=>'already_rolled_back']);}
 $q=$db->prepare('SELECT activity_id,field_name,previous_value,new_value,previous_was_null FROM bh_sheets_override_changes WHERE run_id=? ORDER BY activity_id,field_name');$q->execute([$runId]);$changes=$q->fetchAll(PDO::FETCH_ASSOC);
 if(!$changes){$db->rollBack();rollback_reply(409,['ok'=>false,'error'=>'no_saved_changes']);}
 $allowed=['title','description','age_range','price_from','booking_url'];$locked=[];
 foreach($changes as $change){
  $id=(string)$change['activity_id'];$field=$change['field_name'];
  if(!in_array($field,$allowed,true))throw new RuntimeException('invalid_field');
  if(!isset($locked[$id])){$lock=$db->prepare('SELECT id FROM bh_activities WHERE id=? FOR UPDATE');$lock->execute([$id]);if(!$lock->fetchColumn()){$db->rollBack();rollback_reply(409,['ok'=>false,'error'=>'activity_missing','activity_id'=>$id]);}$locked[$id]=true;}
  $current=$db->prepare('SELECT `'.$field.'` FROM bh_activities WHERE id=?');$current->execute([$id]);$value=$current->fetchColumn();
  $matches=($value===null&&$change['new_value']===null)||($value!==null&&$change['new_value']!==null&&($field==='price_from'?abs((float)$value-(float)$change['new_value'])<0.001:(string)$value===(string)$change['new_value']));
  if(!$matches){$db->rollBack();rollback_reply(409,['ok'=>false,'error'=>'activity_changed_since_sync','activity_id'=>$id,'field'=>$field]);}
 }
 foreach($changes as $change){
  $restore=((int)$change['previous_was_null']===1)?null:$change['previous_value'];
  $stmt=$db->prepare('UPDATE bh_activities SET `'.$change['field_name'].'`=? WHERE id=?');$stmt->execute([$restore,$change['activity_id']]);
 }
 $audit=$db->prepare('INSERT INTO bh_sheets_override_rollbacks (run_id,admin_username,reverted_changes) VALUES (?,?,?)');$audit->execute([$runId,$admin,count($changes)]);
 $db->commit();rollback_reply(200,['ok'=>true,'mode'=>'admin_override_rolled_back','run_id'=>$runId,'reverted_changes'=>count($changes)]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();rollback_reply(503,['ok'=>false,'error'=>'rollback_unavailable']);}
