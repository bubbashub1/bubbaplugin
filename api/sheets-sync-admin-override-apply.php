<?php
declare(strict_types=1);
// Staging only. Explicitly confirmed admin override, atomic snapshots and updates.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function sync_reply(int $code,array $payload):never{http_response_code($code);echo json_encode($payload,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))sync_reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sync_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(($_SERVER['HTTP_ORIGIN']??'')!=='https://staging.bubbahub.co.uk')sync_reply(403,['ok'=>false,'error'=>'invalid_origin']);
if(!in_array(strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')),['same-origin'],true))sync_reply(403,['ok'=>false,'error'=>'invalid_fetch_site']);
$raw=file_get_contents('php://input',false,null,0,262145);
if($raw===false||strlen($raw)>262144)sync_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);
if(!is_array($data)||!is_array($data['rows']??null)||!array_is_list($data['rows'])||count($data['rows'])<1||count($data['rows'])>100||($data['confirm']??null)!=='SYNC_STAGING_NOW')sync_reply(422,['ok'=>false,'error'=>'invalid_request']);
$csrf=(string)($_SESSION['bh_sheets_review_csrf']??'');
if(strlen($csrf)!==64||!is_string($data['csrf_token']??null)||!hash_equals($csrf,$data['csrf_token']))sync_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$admin=(string)($_SESSION['bh_admin_username']??'');
if($admin===''||strlen($admin)>190)sync_reply(401,['ok'=>false,'error'=>'admin_relogin_required']);
require_once __DIR__.'/sheets-sync-preflight.php';require_once __DIR__.'/sheets-sync-field-preview.php';
$pre=bh_sync_preflight($data['rows']);$ids=[];
foreach($data['rows'] as $i=>$row){
 if(($pre['rows'][$i]['status']??'')!=='pending_database_match')sync_reply(422,['ok'=>false,'error'=>'invalid_or_duplicate_row','row'=>$i+1]);
 $id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);
 if($id===null)sync_reply(422,['ok'=>false,'error'=>'activity_id_required','row'=>$i+1]);
 $ids[]=$id;
}
sort($ids,SORT_STRING);
$key=hash('sha256',json_encode($data['rows'],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $existing=$db->prepare('SELECT id FROM bh_sheets_override_runs WHERE request_key=?');
 $existing->execute([$key]);if($existing->fetchColumn()){$db->rollBack();sync_reply(409,['ok'=>false,'error'=>'batch_already_applied']);}
 $records=[];
 foreach($ids as $id){
  $q=$db->prepare('SELECT id,title,description,age_range,price_from,booking_url FROM bh_activities WHERE id=? FOR UPDATE');
  $q->execute([$id]);$record=$q->fetch(PDO::FETCH_ASSOC);
  if(!$record){$db->rollBack();sync_reply(409,['ok'=>false,'error'=>'missing_activity','activity_id'=>$id]);}
  $records[$id]=$record;
 }
 $changes=[];
 foreach($data['rows'] as $row){
  $id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);
  $diff=bh_sync_field_preview($row,$records[$id]);
  foreach($diff as $field=>$item){
   if($item['status']==='invalid_sheet_value'){$db->rollBack();sync_reply(422,['ok'=>false,'error'=>'invalid_field','activity_id'=>$id,'field'=>$field]);}
   if($item['status']!=='different_review')continue;
   $changes[]=['id'=>$id,'field'=>$field,'old'=>$records[$id][$field],'new'=>$item['sheet_value']];
  }
 }
 if(count($changes)===0){$db->rollBack();sync_reply(200,['ok'=>true,'mode'=>'no_changes','activity_writes_performed'=>0]);}
 $run=$db->prepare('INSERT INTO bh_sheets_override_runs (request_key,admin_username,activity_count,change_count) VALUES (?,?,?,?)');
 $run->execute([$key,$admin,count($ids),count($changes)]);$runId=(int)$db->lastInsertId();
 $audit=$db->prepare('INSERT INTO bh_sheets_override_changes (run_id,activity_id,field_name,previous_value,new_value,previous_was_null) VALUES (?,?,?,?,?,?)');
 $allowed=['title','description','age_range','price_from','booking_url'];
 foreach($changes as $change){
  if(!in_array($change['field'],$allowed,true))throw new RuntimeException('disallowed_field');
  $audit->execute([$runId,$change['id'],$change['field'],$change['old'],$change['new'],$change['old']===null?1:0]);
  $update=$db->prepare('UPDATE bh_activities SET `'.$change['field'].'`=? WHERE id=?');
  $update->execute([$change['new'],$change['id']]);
 }
 $db->commit();
 sync_reply(200,['ok'=>true,'mode'=>'admin_override_applied','run_id'=>$runId,'matched_activities'=>count($ids),'activity_writes_performed'=>count($changes),'changes_saved_for_rollback'=>count($changes)]);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();sync_reply(503,['ok'=>false,'error'=>'apply_unavailable','activity_writes_performed'=>0]);}
