<?php
declare(strict_types=1);
/** Token-authenticated Master Sheet snapshot intake. NEVER updates activities. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $code,array $data):never {
 http_response_code($code);echo json_encode($data+['activity_writes_performed'=>0]);exit;
}
if (strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk')
 reply(403,['ok'=>false,'error'=>'live_host_required']);
if (($_SERVER['REQUEST_METHOD']??'')!=='POST')
 reply(405,['ok'=>false,'error'=>'post_required']);
$private=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if (!is_readable($private)) reply(503,['ok'=>false,'error'=>'sync_not_configured']);
require_once $private;
$token=(string)(getenv('BH_SHEETS_SYNC_TOKEN')?:'');
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if (strlen($token)<32||!preg_match('/^Bearer\\s+(.+)$/i',$auth,$m)||
 !hash_equals($token,trim($m[1]))) reply(401,['ok'=>false,'error'=>'unauthorized']);
if (getenv('BH_SHEETS_LIVE_SNAPSHOT_INTAKE')!=='enabled')
 reply(403,['ok'=>false,'error'=>'snapshot_intake_disabled']);
$raw=file_get_contents('php://input',false,null,0,65537);
if (!is_string($raw)||strlen($raw)>65536) reply(413,['ok'=>false,'error'=>'payload_too_large']);
$data=json_decode($raw,true);
$rows=$data['rows']??null;
$allowed=['title','price_from','age_range'];
if (!is_array($data)||($data['action']??null)!=='store_sheet_snapshot'||
 !is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>25)
 reply(422,['ok'=>false,'error'=>'invalid_request']);
try {
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $q=$db->prepare("INSERT INTO bh_sheets_sync_live_sheet_snapshots
 (activity_id,field_name,sheet_value,received_at_utc,source_run_id)
 VALUES (?,?,?,UTC_TIMESTAMP(),?)
 ON DUPLICATE KEY UPDATE sheet_value=VALUES(sheet_value),
 received_at_utc=VALUES(received_at_utc),source_run_id=VALUES(source_run_id)");
 $delete=$db->prepare('DELETE FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id=? AND field_name=?');
 $run='master:'.bin2hex(random_bytes(16));$stored=0;$cleared=0;
 foreach($rows as $row) {
  if (!is_array($row)||!is_int($row['id']??null)||$row['id']<1||
   !is_array($row['fields']??null)) throw new InvalidArgumentException('invalid_row');
  foreach($row['fields'] as $field=>$value) {
   if (!in_array($field,$allowed,true)||!is_string($value)||strlen($value)>2000)
    throw new InvalidArgumentException('invalid_field');
   if (trim($value)==='') {
    // Explicit blank invalidates a previous Sheet snapshot, NOT the website or approved baseline.
    // Omitted fields do not enter this loop and therefore retain their snapshots.
    $delete->execute([$row['id'],$field]);
    $cleared += $delete->rowCount();
    continue;
   }
   $q->execute([$row['id'],$field,$value,$run]);$stored++;
  }
 }
 $db->commit();reply(200,['ok'=>true,'stored_fields'=>$stored,'cleared_stale_snapshots'=>$cleared,'source_run_id'=>$run]);
} catch(InvalidArgumentException $e) {
 if (isset($db)&&$db->inTransaction())$db->rollBack();
 reply(422,['ok'=>false,'error'=>'invalid_row_or_field']);
} catch(Throwable $e) {
 if (isset($db)&&$db->inTransaction())$db->rollBack();
 error_log('Live sheet snapshot intake: '.$e->getMessage());
 reply(503,['ok'=>false,'error'=>'snapshot_unavailable']);
}
