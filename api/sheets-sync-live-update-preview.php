<?php
declare(strict_types=1);
/**
 * Read-only live Sheet update preview. Intentionally limited to activity 955.
 * This endpoint NEVER writes activities, baselines or audit records.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function previewReply(int $code,array $data):never {
 http_response_code($code);
 echo json_encode($data+['activity_writes_performed'=>0,'mode'=>'preview_only'],JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk')previewReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')previewReply(405,['ok'=>false,'error'=>'get_required']);
require_once __DIR__.'/sheets-sync-baseline-guard.php';
$settings=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if(is_file($settings)&&is_readable($settings))require_once $settings;
$id=955;
$fields=['title','price_from','age_range'];
$simulate=array_key_exists('simulate_field',$_GET)||array_key_exists('simulate_value',$_GET);
$simulateField=$simulate?(string)($_GET['simulate_field']??''):'';
$simulateValue=$simulate?(string)($_GET['simulate_value']??''):'';
if($simulate && (!in_array($simulateField,$fields,true)||!is_string($_GET['simulate_value']??null)||strlen($simulateValue)>200))
 previewReply(422,['ok'=>false,'error'=>'invalid_simulation_input']);
try{
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $q=$db->prepare("SELECT a.id,a.title,a.price_from,a.age_range,a.status,a.leader_id,a.created_by,o.email AS organiser_email FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=?");
 $q->execute([$id]);$activity=$q->fetch(PDO::FETCH_ASSOC);
 if(!$activity)previewReply(404,['ok'=>false,'error'=>'activity_not_found']);
 $protected=$activity['status']!=='published'||$activity['leader_id']!==null||$activity['created_by']!==null;
 if(trim((string)($activity['organiser_email']??''))!==''){
   $q=$db->prepare("SELECT 1 FROM bh_users WHERE role='leader' AND status='active' AND LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1");
   $q->execute([$activity['organiser_email']]);
   if($q->fetchColumn())$protected=true;
 }
 $q=$db->prepare("SELECT version,field_values_json,approved_at_utc FROM bh_sheets_sync_live_baselines WHERE environment='live' AND activity_id=? ORDER BY version DESC LIMIT 1");
 $q->execute([$id]);$baseline=$q->fetch(PDO::FETCH_ASSOC);
 $trusted=$baseline?json_decode((string)$baseline['field_values_json'],true):null;
 if(!is_array($trusted))$trusted=null;
 $q=$db->prepare("SELECT field_name,sheet_value,received_at_utc FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=? AND field_name IN ('title','price_from','age_range')");
 $q->execute([$id]);$snap=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$snap[$r['field_name']]=$r;
 $results=[];
 foreach($fields as $field){
   $current=(string)($activity[$field]??'');
   $snapshot=$snap[$field]??null;
   $incoming=$snapshot===null?null:(string)$snapshot['sheet_value'];
   $isSimulated=$simulate && $simulateField===$field;
   if($isSimulated)$incoming=$simulateValue;
   $fresh=$snapshot!==null && strtotime((string)$snapshot['received_at_utc'].' UTC')>=time()-86400;
   // Simulated values have no real snapshot timestamp; they are evaluated only as hypothetical inputs.
   if($isSimulated)$fresh=true;
   $hasBaseline=$trusted!==null&&array_key_exists($field,$trusted);
   $status='no_snapshot';
   if($protected)$status='protected_listing';
   elseif(!$hasBaseline)$status='baseline_required';
   elseif(!$fresh)$status='snapshot_expired';
   elseif(trim((string)$incoming)==='')$status='blank_sheet_ignored';
   else {
      $comparison=bh_sync_baseline_field_status([$field=>$incoming],[$field=>$current],$trusted,[$field]);
      $status=$comparison[$field]['status']??'review_required';
   }
   $results[$field]=[
      'website_value'=>$current,
      'sheet_value'=>$incoming,
      'baseline_value'=>$hasBaseline?(string)$trusted[$field]:null,
      'snapshot_received_utc'=>$snapshot['received_at_utc']??null,
      'status'=>$status,
      'simulated'=>$isSimulated,
      'would_write'=>false
   ];
 }
 previewReply(200,['ok'=>true,'activity_id'=>$id,'title'=>(string)$activity['title'],
  'protected'=>$protected,'baseline_version'=>$baseline?(int)$baseline['version']:null,
  'live_writes_enabled'=>getenv('BH_SHEETS_SYNC_LIVE_WRITES')==='enabled',
  'fields'=>$results,
  'simulation'=>$simulate?['field'=>$simulateField,'value'=>$simulateValue,'hypothetical_only'=>true]:null,
  'note'=>$simulate?'Hypothetical input only. No Sheet snapshot or activity was changed.':'Read-only preview. No changes are authorised or performed.']);
}catch(Throwable $e){error_log('Live Sheet update preview: '.$e->getMessage());previewReply(503,['ok'=>false,'error'=>'preview_unavailable']);}
