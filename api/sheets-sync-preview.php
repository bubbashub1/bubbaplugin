<?php
declare(strict_types=1);
// Read-only Google Sheets sync preflight. Staging only until explicitly approved.
// Set BH_SHEETS_SYNC_TOKEN in server environment (never commit credentials).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_sync_reply(int $code,array $data): void {http_response_code($code);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') bh_sync_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
// Load private staging-only secret file, if present. This endpoint does not bootstrap WordPress,
// so putenv() inside wp-config.php alone would NOT be executed here.
$privateConfig=dirname(__DIR__).'/bh-sheets-sync-secret.php';
if(is_file($privateConfig)) require $privateConfig;
$expected=getenv('BH_SHEETS_SYNC_TOKEN');
$auth=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
if(!is_string($expected)||strlen($expected)<32||!preg_match('/^Bearer (.+)$/',$auth,$match)||!hash_equals($expected,$match[1]))bh_sync_reply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>1500000)bh_sync_reply(413,['ok'=>false,'error'=>'payload_too_large']);
$input=json_decode($raw,true);
if(!is_array($input)||($input['action']??'')!=='preview'||!is_array($input['rows']??null))bh_sync_reply(400,['ok'=>false,'error'=>'invalid_preview_request']);
if(count($input['rows'])>3000)bh_sync_reply(413,['ok'=>false,'error'=>'too_many_rows']);
try {require __DIR__.'/db.php';$db=bh_mysql();
  $columns=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_ASSOC);
  $names=array_column($columns,'Field');
  $ids=[];$duplicates=[];$unknown=[];$invalid=0;
  $stmt=$db->prepare('SELECT id FROM bh_activities WHERE id=? LIMIT 1');
  foreach($input['rows'] as $entry){
    if(!is_array($entry)||!is_array($entry['values']??null)){$invalid++;continue;}
    $id=trim((string)($entry['values']['id']??''));
    if($id==='')continue;
    if(!ctype_digit($id)||$id==='0'){$invalid++;continue;}
    if(isset($ids[$id])){$duplicates[]=$id;continue;}
    $ids[$id]=true;$stmt->execute([$id]);if(!$stmt->fetchColumn())$unknown[]=$id;
  }
  bh_sync_reply(200,['ok'=>true,'mode'=>'read_only','summary'=>[
    'rows_received'=>count($input['rows']),'existing_ids_found'=>count($ids)-count($unknown),
    'missing_ids'=>count($unknown),'duplicate_ids'=>count($duplicates),'invalid_rows'=>$invalid,
    'database_columns'=>$names,
    'notice'=>'Preflight only: no comparison, import, export or database writes were performed.'
  ],'unknown_ids'=>array_slice($unknown,0,25),'duplicate_ids'=>array_slice($duplicates,0,25)]);
}catch(Throwable $e){error_log('Sheets preflight error: '.$e->getMessage());bh_sync_reply(500,['ok'=>false,'error'=>'preflight_failed']);}
