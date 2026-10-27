<?php
declare(strict_types=1);
/** Read-only readiness report. Never authorises or executes a write. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function readinessReply(int $status,array $data):never {
 http_response_code($status);
 echo json_encode($data+['mode'=>'read_only','activity_writes_performed'=>0],JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk')
 readinessReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')
 readinessReply(405,['ok'=>false,'error'=>'get_required']);
require_once __DIR__.'/sheets-sync-live-write-eligibility.php';
$settings=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if(is_readable($settings))require_once $settings;
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();$id=955;
 $q=$db->prepare('SELECT a.id,a.price_from,a.status,a.leader_id,a.created_by,o.email AS organiser_email FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=?');
 $q->execute([$id]);$a=$q->fetch(PDO::FETCH_ASSOC);
 if(!$a)readinessReply(404,['ok'=>false,'error'=>'activity_not_found']);
 $protected=$a['status']!=='published'||$a['leader_id']!==null||$a['created_by']!==null;
 if(trim((string)($a['organiser_email']??''))!==''){
  $q=$db->prepare("SELECT 1 FROM bh_users WHERE role='leader' AND status='active' AND LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1");
  $q->execute([$a['organiser_email']]);if($q->fetchColumn())$protected=true;
 }
 $q=$db->prepare("SELECT version,field_values_json FROM bh_sheets_sync_live_baselines WHERE environment='live' AND activity_id=? ORDER BY version DESC LIMIT 1");
 $q->execute([$id]);$baseline=$q->fetch(PDO::FETCH_ASSOC);
 $values=$baseline?json_decode((string)$baseline['field_values_json'],true):null;
 $q=$db->prepare("SELECT sheet_value,received_at_utc FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=? AND field_name='price_from'");
 $q->execute([$id]);$snapshot=$q->fetch(PDO::FETCH_ASSOC);
 // This report deliberately does not provide an approval or CSRF token.
 // A later POST writer must independently authenticate and re-check everything.
 $context=[
 'activity_id'=>955,'field'=>'price_from','live_enabled'=>getenv('BH_SHEETS_SYNC_LIVE_WRITES')==='enabled',
 'admin_authorized'=>true,'csrf_valid'=>false,'protected'=>$protected,
 'baseline_version'=>$baseline?(int)$baseline['version']:null,
 'snapshot_received_utc'=>$snapshot['received_at_utc']??null,
 'sheet_value'=>$snapshot['sheet_value']??null,
 'website_value'=>$a['price_from']===null?null:(string)$a['price_from'],
 'baseline_value'=>is_array($values)?($values['price_from']??null):null,
 'review_approved'=>false
 ];
 $result=bh_sync_live_write_eligibility($context);
 readinessReply(200,['ok'=>true,'activity_id'=>955,'field'=>'price_from','protected'=>$protected,
 'baseline_version'=>$context['baseline_version'],'snapshot_received_utc'=>$context['snapshot_received_utc'],
 'live_writes_enabled'=>$context['live_enabled'],'eligibility'=>$result,
 'note'=>'Read-only. No approval can be granted here. The locked apply endpoint always refuses writes.']);
} catch(Throwable $e) {
 error_log('Live sync readiness: '.$e->getMessage());
 readinessReply(503,['ok'=>false,'error'=>'readiness_unavailable']);
}
