<?php
declare(strict_types=1);
// Staging-only admin override preview. No listing writes.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function override_reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))override_reply(403,['ok'=>false,'error'=>'staging_only']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')override_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if(($_SERVER['HTTP_ORIGIN']??'')!=='https://staging.bubbahub.co.uk')override_reply(403,['ok'=>false,'error'=>'invalid_origin']);
$csrf=(string)($_SESSION['bh_sheets_review_csrf']??'');
$raw=file_get_contents('php://input',false,null,0,262145);
if($raw===false||strlen($raw)>262144)override_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);
if(!is_array($data)||!is_array($data['rows']??null)||!array_is_list($data['rows'])||count($data['rows'])>100)override_reply(422,['ok'=>false,'error'=>'invalid_rows']);
if(strlen($csrf)!==64||!is_string($data['csrf_token']??null)||!hash_equals($csrf,$data['csrf_token']))override_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
require_once __DIR__.'/sheets-sync-preflight.php';require_once __DIR__.'/sheets-sync-field-preview.php';
$pre=bh_sync_preflight($data['rows']);$ids=[];
foreach($data['rows'] as $i=>$row){if(($pre['rows'][$i]['status']??'')!=='pending_database_match')continue;$id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);if($id!==null)$ids[$id]=true;}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$found=[];
 foreach(array_chunk(array_keys($ids),50) as $chunk){$q=$db->prepare('SELECT id,title,description,age_range,price_from,booking_url FROM bh_activities WHERE id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');$q->execute($chunk);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $record)$found[(string)$record['id']]=$record;}
 $changes=[];$skipped=[];$matched=0;
 foreach($data['rows'] as $i=>$row){
  $id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);
  if(($pre['rows'][$i]['status']??'')!=='pending_database_match'||$id===null||!isset($found[$id])){$skipped[]=['row'=>$i+1,'reason'=>($pre['rows'][$i]['status']??'invalid')==='pending_database_match'?'missing_activity':($pre['rows'][$i]['status']??'invalid')];continue;}
  $matched++;
  foreach(bh_sync_field_preview($row,$found[$id]) as $field=>$comparison){
   if($comparison['status']==='invalid_sheet_value'){$skipped[]=['row'=>$i+1,'field'=>$field,'reason'=>'invalid_sheet_value'];continue;}
   if($comparison['status']!=='different_review')continue;
   $changes[]=['activity_id'=>$id,'field'=>$field,'before'=>$comparison['website_value'],'after'=>$comparison['sheet_value']];
  }
 }
 override_reply(200,['ok'=>true,'mode'=>'admin_override_preview_only','apply_enabled'=>false,'activity_writes_performed'=>0,'matched_activities'=>$matched,'changes_count'=>count($changes),'changes'=>$changes,'skipped'=>$skipped]);
}catch(Throwable $e){override_reply(503,['ok'=>false,'error'=>'preview_unavailable','activity_writes_performed'=>0]);}
