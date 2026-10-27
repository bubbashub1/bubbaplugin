<?php
declare(strict_types=1);
/** Read-only pilot preflight. No approval or database mutations. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function pilotReply(int $status, array $body): never {
 http_response_code($status);
 echo json_encode($body + ['mode'=>'read_only','activity_writes_performed'=>0], JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST']??'')) !== 'bubbahub.co.uk') pilotReply(403,['ok'=>false,'error'=>'live_host_required']);
require_once __DIR__.'/admin-session-guard.php';
if (($_SERVER['REQUEST_METHOD']??'') !== 'GET') pilotReply(405,['ok'=>false,'error'=>'get_required']);
try {
 require_once __DIR__.'/db.php';
 require_once __DIR__.'/sheets-sync-baseline-guard.php';
 $db=bh_mysql();
 $q=$db->prepare('SELECT a.price_from,a.status,a.leader_id,a.created_by,o.email AS organiser_email FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=955');
 $q->execute(); $a=$q->fetch(PDO::FETCH_ASSOC);
 if (!$a) pilotReply(404,['ok'=>false,'error'=>'activity_not_found']);
 $protected=$a['status']!=='published'||$a['leader_id']!==null||$a['created_by']!==null;
 if (trim((string)($a['organiser_email']??''))!=='') {
  $q=$db->prepare("SELECT 1 FROM bh_users WHERE role='leader' AND status='active' AND LOWER(TRIM(email))=LOWER(TRIM(?)) LIMIT 1");
  $q->execute([$a['organiser_email']]); if($q->fetchColumn()) $protected=true;
 }
 $q=$db->prepare("SELECT version,field_values_json FROM bh_sheets_sync_live_baselines WHERE environment='live' AND activity_id=955 ORDER BY version DESC LIMIT 1");
 $q->execute();$baseline=$q->fetch(PDO::FETCH_ASSOC);
 $values=$baseline?json_decode((string)$baseline['field_values_json'],true):null;
 $q=$db->prepare("SELECT sheet_value,received_at_utc FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=955 AND field_name='price_from'");
 $q->execute();$snapshot=$q->fetch(PDO::FETCH_ASSOC);
 $website=$a['price_from']===null?null:(string)$a['price_from'];
 $sheet=$snapshot['sheet_value']??null;
 $base=is_array($values)?($values['price_from']??null):null;
 $status='baseline_missing';
 if ($base!==null) {
  $comparison=bh_sync_baseline_field_status(['price_from'=>$sheet],['price_from'=>$website],['price_from'=>$base],['price_from']);
  $status=$comparison['price_from']['status']??'unknown';
 }
 $time=$snapshot['received_at_utc']??null;
 $ts=is_string($time)?strtotime($time.' UTC'):false;
 $fresh=$ts!==false && $ts<=time()+300 && $ts>=time()-86400;
 pilotReply(200,['ok'=>true,'activity_id'=>955,'field'=>'price_from','protected'=>$protected,
  'baseline_version'=>$baseline?(int)$baseline['version']:null,'website_value'=>$website,
  'sheet_value'=>$sheet,'baseline_value'=>$base,'comparison_status'=>$status,
  'snapshot_received_utc'=>$time,'snapshot_fresh_24h'=>$fresh,
  'pilot_candidate'=>$protected===false&&$fresh&&$status==='sheet_changed_review',
  'note'=>'Diagnostic only. Candidate does not grant approval; the live writer remains locked.']);
} catch(Throwable $e) {
 error_log('Sheets pilot preflight: '.$e->getMessage());
 pilotReply(503,['ok'=>false,'error'=>'preflight_unavailable']);
}
