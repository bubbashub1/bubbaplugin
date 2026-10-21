<?php
declare(strict_types=1);
/** Staging-only proposal intake. Writes ONLY bh_sheets_sync_review_queue, never activities. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_preview_reply(int $status,array $payload): never {
 http_response_code($status);
 echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {
 header('Allow: POST');bh_preview_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if (!preg_match('/^staging\\.bubbahub\\.co\\.uk(?::[0-9]{1,5})?$/D',strtolower((string)($_SERVER['HTTP_HOST']??'')))) {
 bh_preview_reply(403,['ok'=>false,'error'=>'staging_only']);
}
$expected = getenv('BH_SHEETS_SYNC_TOKEN');
if (!is_string($expected) || $expected === '') {
    $expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
}
if (!is_string($expected) || $expected === '') {
    $privateConfig = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
    if (is_file($privateConfig) && is_readable($privateConfig)) {
        require_once $privateConfig;
    }
    $expected = getenv('BH_SHEETS_SYNC_TOKEN');
    if (!is_string($expected) || $expected === '') {
        $expected = getenv('BUBBAHUB_SHEETS_SYNC_TOKEN');
    }
}
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!is_string($expected) || strlen($expected) < 32 ||
    !preg_match('/^Bearer ([^\x00-\x20\x7F]+)$/D', $authorization, $matches) ||
    !hash_equals($expected, $matches[1])) {
    bh_preview_reply(401, ['ok'=>false,'error'=>'unauthorized']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
$raw = file_get_contents('php://input', false, null, 0, 262145);
if ($raw === false || strlen($raw) > 262144) {
    bh_preview_reply(413, ['ok'=>false,'error'=>'payload_too_large']);
}
try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
catch (JsonException $e) { bh_preview_reply(400, ['ok'=>false,'error'=>'invalid_json']); }
if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows']) || !array_is_list($data['rows'])) {
    bh_preview_reply(400, ['ok'=>false,'error'=>'rows_array_required']);
}
if (count($data['rows']) > 100) {
    bh_preview_reply(422, ['ok'=>false,'error'=>'too_many_rows']);
}
foreach ($data['rows'] as $row) {
    if (!is_array($row) || array_is_list($row)) {
        bh_preview_reply(422, ['ok'=>false,'error'=>'invalid_row']);
    }
}

require_once __DIR__.'/sheets-sync-preflight.php';
require_once __DIR__.'/sheets-sync-field-preview.php';
require_once __DIR__.'/db.php';
$preflight=bh_sync_preflight($data['rows']);
$ids=[];
foreach ($data['rows'] as $index=>$row) {
 if (($preflight['rows'][$index]['status']??'')!=='pending_database_match') continue;
 $id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);
 if ($id!==null) $ids[$id]=true;
}
if (!$ids) bh_preview_reply(422,['ok'=>false,'error'=>'no_explicit_activity_ids']);
try {
 $db=bh_mysql();
 $db->beginTransaction();
 $found=[];
 foreach(array_chunk(array_keys($ids),50) as $chunk){
  $stmt=$db->prepare('SELECT id,title,description,age_range,price_from,booking_url FROM bh_activities WHERE id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');
  $stmt->execute($chunk);
  foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $existing) $found[(string)$existing['id']]=$existing;
 }
 $insert=$db->prepare("INSERT INTO bh_sheets_sync_review_queue (environment,activity_id,field_name,website_value,sheet_value,website_fingerprint,sheet_fingerprint,status,source_run_id) VALUES ('staging',?,?,?,?,?,?,'pending',?) ON DUPLICATE KEY UPDATE id=id");
 $runId='sheets-'.hash('sha256',json_encode($data['rows'],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
 $created=0;$matched=0;$skipped=0;
 foreach($data['rows'] as $index=>$row){
  if (($preflight['rows'][$index]['status']??'')!=='pending_database_match') {$skipped++;continue;}
  $id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);
  if ($id===null||!isset($found[$id])) {$skipped++;continue;}
  $matched++;
  foreach(bh_sync_field_preview($row,$found[$id]) as $field=>$comparison){
   if (($comparison['status']??'')!=='different_review') continue;
   $current=(string)$comparison['website_value'];$proposed=(string)$comparison['sheet_value'];
   $insert->execute([$id,$field,$current,$proposed,hash('sha256',$current),hash('sha256',$proposed),$runId]);
   if ($insert->rowCount()===1) $created++;
  }
 }
 $db->commit();
 bh_preview_reply(200,['ok'=>true,'mode'=>'queued_for_review','activity_writes_performed'=>0,'review_rows_created'=>$created,'matched_activities'=>$matched,'skipped_rows'=>$skipped,'source_run_id'=>$runId]);
} catch(Throwable $e) {
 if(isset($db)&&$db->inTransaction()) $db->rollBack();
 bh_preview_reply(503,['ok'=>false,'error'=>'review_queue_unavailable','activity_writes_performed'=>0]);
}
