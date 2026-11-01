<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__.'/admin-session-guard.php';
require __DIR__.'/db.php';
function reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
try{
 $db=bh_mysql();
 $_SESSION['bh_venue_csrf']??=bin2hex(random_bytes(32));
 if($_SERVER['REQUEST_METHOD']==='GET')reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_venue_csrf']]);
 if($_SERVER['REQUEST_METHOD']!=='POST')reply(405,['ok'=>false,'error'=>'Method not allowed']);
 $data=json_decode(file_get_contents('php://input'),true);
 if(!is_array($data))reply(400,['ok'=>false,'error'=>'Invalid JSON']);
 if(!hash_equals($_SESSION['bh_venue_csrf'],(string)($data['csrf']??'')))reply(403,['ok'=>false,'error'=>'Invalid security token']);
 $action=(string)($data['action']??'');
 if($action==='bulk_delete_preview'||$action==='bulk_delete'){
  $ids=$data['ids']??null;
  if(!is_array($ids)||count($ids)<1||count($ids)>100)reply(422,['ok'=>false,'error'=>'Select 1–100 venues']);
  $clean=[];foreach($ids as $id){$v=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$v)reply(422,['ok'=>false,'error'=>'Invalid venue ID']);$clean[]=(int)$v;}
  $clean=array_values(array_unique($clean));if(count($clean)!==count($ids))reply(422,['ok'=>false,'error'=>'Duplicate venue IDs']);
  $marks=implode(',',array_fill(0,count($clean),'?'));$db->beginTransaction();
  $q=$db->prepare("SELECT v.id,v.venue_name,v.activity_id,COUNT(s.venue_id) AS sessions FROM bh_venues v LEFT JOIN bh_sessions s ON s.venue_id=v.id WHERE v.id IN ($marks) GROUP BY v.id,v.venue_name,v.activity_id FOR UPDATE");
  $q->execute($clean);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
  if(count($rows)!==count($clean)){$db->rollBack();reply(409,['ok'=>false,'error'=>'Selection changed: one or more venues no longer exist']);}
  $eligible=[];$blocked=[];foreach($rows as $row){if((int)$row['sessions']>0)$blocked[]=['id'=>(int)$row['id'],'name'=>$row['venue_name'],'sessions'=>(int)$row['sessions']];else $eligible[]=['id'=>(int)$row['id'],'name'=>$row['venue_name']];}
  if($action==='bulk_delete_preview'){$db->rollBack();reply(200,['ok'=>true,'eligible'=>$eligible,'blocked'=>$blocked]);}
  if(($data['confirm']??'')!=='DELETE'){$db->rollBack();reply(422,['ok'=>false,'error'=>'Type DELETE to confirm']);}
  if($blocked){$db->rollBack();reply(409,['ok'=>false,'error'=>'Some selected venues now have linked sessions. No venues deleted. Refresh preview and remap those sessions individually.','blocked'=>$blocked]);}
  $q=$db->prepare("DELETE FROM bh_venues WHERE id IN ($marks)");$q->execute($clean);$deleted=$q->rowCount();$db->commit();reply(200,['ok'=>true,'deleted'=>$deleted]);
 }
 if($action==='bulk_update'){
  $ids=$data['ids']??null;$changes=$data['changes']??null;
  if(!is_array($ids)||count($ids)<1||count($ids)>100||!is_array($changes)||count($changes)<1)reply(422,['ok'=>false,'error'=>'Select 1–100 venues and at least one field']);
  $cleanIds=[];foreach($ids as $id){$v=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$v)reply(422,['ok'=>false,'error'=>'Invalid venue ID']);$cleanIds[]=(int)$v;}
  $cleanIds=array_values(array_unique($cleanIds));if(count($cleanIds)!==count($ids))reply(422,['ok'=>false,'error'=>'Duplicate venue IDs']);
  $allowed=['town'=>120,'region'=>120,'postcode'=>20,'latitude'=>null,'longitude'=>null];$fields=[];$values=[];
  foreach($changes as $key=>$value){
   if(!array_key_exists($key,$allowed)||!is_scalar($value)&&$value!==null)reply(422,['ok'=>false,'error'=>'Unsupported field']);
   if($key==='latitude'||$key==='longitude'){
    if($value!==null&&$value!==''&&(!is_numeric($value)||($key==='latitude'&&abs((float)$value)>90)||($key==='longitude'&&abs((float)$value)>180)))reply(422,['ok'=>false,'error'=>'Invalid coordinates']);
    $values[$key]=($value===null||$value==='')?null:(float)$value;
   }else{$v=trim((string)$value);if(mb_strlen($v)>$allowed[$key])reply(422,['ok'=>false,'error'=>'Field too long: '.$key]);$values[$key]=$v;}
   $fields[]=$key.'=?';
  }
  if(array_key_exists('latitude',$values)!==array_key_exists('longitude',$values))reply(422,['ok'=>false,'error'=>'Both coordinate fields must be supplied']);
  if(array_key_exists('latitude',$values)&&(($values['latitude']===null)!==($values['longitude']===null)))reply(422,['ok'=>false,'error'=>'Both coordinates must be set or cleared together']);
  $marks=implode(',',array_fill(0,count($cleanIds),'?'));$db->beginTransaction();
  $q=$db->prepare("SELECT id FROM bh_venues WHERE id IN ($marks) FOR UPDATE");$q->execute($cleanIds);$found=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  if(count($found)!==count($cleanIds)){$db->rollBack();reply(409,['ok'=>false,'error'=>'One or more venues no longer exist; nothing changed']);}
  $sql='UPDATE bh_venues SET '.implode(',',$fields).' WHERE id IN ('.$marks.')';
  $q=$db->prepare($sql);$q->execute(array_merge(array_values($values),$cleanIds));$updated=$q->rowCount();$db->commit();
  reply(200,['ok'=>true,'selected'=>count($cleanIds),'changed_rows'=>$updated]);
 }
 if(in_array($action,['create','update'],true)){
  $fields=[];
  foreach(['venue_name'=>190,'address'=>1000,'town'=>120,'region'=>120,'postcode'=>20] as $key=>$max){
   $value=trim((string)($data[$key]??''));if(mb_strlen($value)>$max)reply(422,['ok'=>false,'error'=>'Field too long: '.$key]);$fields[$key]=$value;
  }
  if($fields['venue_name']==='')reply(422,['ok'=>false,'error'=>'Venue name is required']);
  $coords=[];
  foreach(['latitude'=>[-90,90],'longitude'=>[-180,180]] as $key=>$bounds){
   $raw=trim((string)($data[$key]??''));
   if($raw!==''&&(!is_numeric($raw)||(float)$raw<$bounds[0]||(float)$raw>$bounds[1]))reply(422,['ok'=>false,'error'=>'Invalid '.$key]);
   $coords[$key]=$raw===''?null:(float)$raw;
  }
  if(($coords['latitude']===null)!==($coords['longitude']===null))reply(422,['ok'=>false,'error'=>'Both coordinates are required together']);
  if($action==='create'){
   $activityId=filter_var($data['activity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
   if(!$activityId)reply(422,['ok'=>false,'error'=>'Choose an existing activity before creating a venue']);
   $check=$db->prepare('SELECT id FROM bh_activities WHERE id=?');$check->execute([$activityId]);
   if(!$check->fetchColumn())reply(404,['ok'=>false,'error'=>'Activity not found']);
   $sql='INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude) VALUES (?,?,?,?,?,?,?,?)';
   $db->prepare($sql)->execute([$activityId,$fields['venue_name'],$fields['address'],$fields['town'],$fields['region'],$fields['postcode'],$coords['latitude'],$coords['longitude']]);
   reply(201,['ok'=>true,'id'=>(int)$db->lastInsertId()]);
  }
  $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id)reply(422,['ok'=>false,'error'=>'Invalid venue ID']);
  $sql='UPDATE bh_venues SET venue_name=?,address=?,town=?,region=?,postcode=?,latitude=?,longitude=? WHERE id=?';
  $db->prepare($sql)->execute([$fields['venue_name'],$fields['address'],$fields['town'],$fields['region'],$fields['postcode'],$coords['latitude'],$coords['longitude'],$id]);
  reply(200,['ok'=>true,'id'=>$id]);
 }
 if($action==='delete_preview'||$action==='delete_remap'){
  $id=filter_var($data['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id)reply(422,['ok'=>false,'error'=>'Invalid venue ID']);
  $db->beginTransaction();
  $q=$db->prepare('SELECT id,activity_id,venue_name,town,postcode FROM bh_venues WHERE id=? FOR UPDATE');$q->execute([$id]);$source=$q->fetch(PDO::FETCH_ASSOC);
  if(!$source){$db->rollBack();reply(404,['ok'=>false,'error'=>'Venue not found']);}
  $q=$db->prepare('SELECT COUNT(*) FROM bh_sessions WHERE venue_id=?');$q->execute([$id]);$sessions=(int)$q->fetchColumn();
  $q=$db->prepare('SELECT title FROM bh_activities WHERE id=?');$q->execute([$source['activity_id']]);$activityTitle=$q->fetchColumn();$activity=$activityTitle!==false?(string)$activityTitle:null;
  $q=$db->prepare("SELECT id,venue_name,town,postcode FROM bh_venues WHERE activity_id=? AND id<>? ORDER BY CASE WHEN LOWER(TRIM(venue_name))=LOWER(TRIM(?)) THEN 0 WHEN postcode=? AND postcode<>'' THEN 1 WHEN town=? AND town<>'' THEN 2 ELSE 3 END,venue_name LIMIT 20");
  $q->execute([$source['activity_id'],$id,$source['venue_name'],$source['postcode'],$source['town']]);$candidates=$q->fetchAll(PDO::FETCH_ASSOC);
  if($action==='delete_preview'){$db->rollBack();reply(200,['ok'=>true,'venue'=>$source,'listing'=>$activity,'has_listing'=>$activity!==null,'sessions'=>$sessions,'candidates'=>$candidates]);}
  $target=filter_var($data['target_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(($data['confirm']??'')!=='DELETE'||($sessions>0&&!$target)){$db->rollBack();reply(422,['ok'=>false,'error'=>'Type DELETE and select a replacement when sessions are linked']);}
  if($target){
   if($target===$id){$db->rollBack();reply(422,['ok'=>false,'error'=>'Choose a different venue']);}
   $q=$db->prepare('SELECT activity_id FROM bh_venues WHERE id=? FOR UPDATE');$q->execute([$target]);$targetActivity=$q->fetchColumn();
   if($targetActivity===false||(int)$targetActivity!==(int)$source['activity_id']){$db->rollBack();reply(409,['ok'=>false,'error'=>'Replacement must be another venue attached to the same listing']);}
   $q=$db->prepare('UPDATE bh_sessions SET venue_id=? WHERE venue_id=?');$q->execute([$target,$id]);
  }
  $q=$db->prepare('DELETE FROM bh_venues WHERE id=?');$q->execute([$id]);
  $db->commit();reply(200,['ok'=>true,'sessions_remapped'=>$sessions]);
 }
 if($action==='merge'){
  $source=filter_var($data['source_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  $target=filter_var($data['target_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$source||!$target||$source===$target)reply(422,['ok'=>false,'error'=>'Choose two different venues']);
  if(($data['confirm']??'')!=='MERGE')reply(422,['ok'=>false,'error'=>'Type MERGE to confirm']);
  $db->beginTransaction();
  $q=$db->prepare('SELECT id,activity_id FROM bh_venues WHERE id IN (?,?) FOR UPDATE');$q->execute([$source,$target]);
  $rows=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$rows[(int)$row['id']]=$row;
  if(count($rows)!==2){$db->rollBack();reply(404,['ok'=>false,'error'=>'Venue not found']);}
  if((int)$rows[$source]['activity_id']!==(int)$rows[$target]['activity_id']){$db->rollBack();reply(409,['ok'=>false,'error'=>'These venues belong to different activities. Cross-activity merging needs a shared venue model; no records changed.']);}
  $q=$db->prepare('UPDATE bh_sessions SET venue_id=? WHERE venue_id=?');$q->execute([$target,$source]);$moved=$q->rowCount();
  $q=$db->prepare('DELETE FROM bh_venues WHERE id=?');$q->execute([$source]);
  $db->commit();reply(200,['ok'=>true,'sessions_moved'=>$moved]);
 }
 reply(400,['ok'=>false,'error'=>'Unknown action']);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();reply(500,['ok'=>false,'error'=>'Venue operation failed; no partial merge was saved']);}
