<?php
declare(strict_types=1);
// Read-only comparison of one prepared master-sheet batch to current staging activities.
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function compare_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??''))))compare_reply(403,['ok'=>false,'error'=>'staging_only']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){header('Allow: GET');compare_reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
require_once __DIR__.'/admin-session-guard.php';
$id=filter_input(INPUT_GET,'batch_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)compare_reply(422,['ok'=>false,'error'=>'invalid_batch_id']);
try{
 require_once __DIR__.'/db.php';require_once __DIR__.'/sheets-sync-preflight.php';require_once __DIR__.'/sheets-sync-field-preview.php';
 $db=bh_mysql();$q=$db->prepare("SELECT id,row_count,rows_json,source_spreadsheet,source_tab FROM bh_sheets_override_batches WHERE id=? AND status='prepared' LIMIT 1");$q->execute([$id]);$batch=$q->fetch(PDO::FETCH_ASSOC);
 if(!$batch)compare_reply(404,['ok'=>false,'error'=>'batch_not_found']);
 $rows=json_decode((string)$batch['rows_json'],true);
 if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>100||count($rows)!==(int)$batch['row_count'])compare_reply(409,['ok'=>false,'error'=>'invalid_batch_payload']);
 $pre=bh_sync_preflight($rows);$ids=[];
 foreach($rows as $i=>$row){if(!is_array($row))continue;if(($pre['rows'][$i]['status']??'')!=='pending_database_match')continue;$activityId=bh_sync_normalize_id($row['activity_id']??$row['id']??null);if($activityId!==null)$ids[$activityId]=true;}
 $found=[];foreach(array_chunk(array_keys($ids),50) as $chunk){$sql='SELECT id,title,description,age_range,price_from,booking_url FROM bh_activities WHERE id IN ('.implode(',',array_fill(0,count($chunk),'?')).')';$stmt=$db->prepare($sql);$stmt->execute($chunk);foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $record)$found[(string)$record['id']]=$record;}
 $out=[];$counts=['matched'=>0,'unchanged'=>0,'different'=>0,'invalid'=>0,'missing'=>0,'skipped'=>0];
 foreach($rows as $i=>$row){
  $activityId=is_array($row)?bh_sync_normalize_id($row['activity_id']??$row['id']??null):null;
  $reason=(string)($pre['rows'][$i]['status']??'invalid_row');
  if($reason!=='pending_database_match'||$activityId===null){$counts['skipped']++;$out[]=['row'=>$i+1,'activity_id'=>$activityId,'status'=>'skipped','reason'=>$reason,'fields'=>[]];continue;}
  if(!isset($found[$activityId])){$counts['missing']++;$out[]=['row'=>$i+1,'activity_id'=>$activityId,'status'=>'missing_activity','fields'=>[]];continue;}
  $counts['matched']++;$fields=bh_sync_field_preview($row,$found[$activityId]);
  foreach($fields as $field=>$detail){if($detail['status']==='same')$counts['unchanged']++;elseif($detail['status']==='different_review')$counts['different']++;else $counts['invalid']++;}
  $out[]=['row'=>$i+1,'activity_id'=>$activityId,'status'=>'matched','fields'=>$fields];
 }
 compare_reply(200,['ok'=>true,'mode'=>'prepared_batch_comparison_only','environment'=>'staging','batch_id'=>(int)$batch['id'],'source_spreadsheet'=>$batch['source_spreadsheet'],'source_tab'=>$batch['source_tab'],'activity_writes_performed'=>0,'approval_enabled'=>false,'counts'=>$counts,'rows'=>$out]);
}catch(Throwable $e){compare_reply(503,['ok'=>false,'error'=>'comparison_unavailable','activity_writes_performed'=>0]);}
