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
