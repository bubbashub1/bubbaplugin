<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function stage_reply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')stage_reply(403,['ok'=>false,'error'=>'staging_only']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')stage_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$expected=(string)(getenv('BH_SYNC_TOKEN')?:getenv('BH_SHEETS_SYNC_TOKEN')?:getenv('BUBBAHUB_SHEETS_SYNC_TOKEN'));
if($expected==='' ){ $private=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';if(is_file($private)&&is_readable($private))require_once $private;$expected=(string)(getenv('BH_SYNC_TOKEN')?:getenv('BH_SHEETS_SYNC_TOKEN')?:getenv('BUBBAHUB_SHEETS_SYNC_TOKEN')); }
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if(strlen($expected)<32||!preg_match('/^Bearer ([^\x00-\x20\x7F]+)$/D',$auth,$m)||!hash_equals($expected,$m[1]))stage_reply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input',false,null,0,262145);if($raw===false||strlen($raw)>262144)stage_reply(413,['ok'=>false,'error'=>'payload_too_large']);
try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){stage_reply(400,['ok'=>false,'error'=>'invalid_json']);}
if(!is_array($data)||!is_array($data['rows']??null)||!array_is_list($data['rows'])||count($data['rows'])<1||count($data['rows'])>100)stage_reply(422,['ok'=>false,'error'=>'rows_must_be_1_to_100']);
$spreadsheet=trim((string)($data['spreadsheet']??''));$tab=trim((string)($data['tab']??''));
if($spreadsheet===''||strlen($spreadsheet)>255||$tab===''||strlen($tab)>128)stage_reply(422,['ok'=>false,'error'=>'source_required']);
require_once __DIR__.'/sheets-sync-preflight.php';$pre=bh_sync_preflight($data['rows']);
$seen=[];foreach($data['rows'] as $i=>$row){if(($pre['rows'][$i]['status']??'')!=='pending_database_match')stage_reply(422,['ok'=>false,'error'=>'invalid_or_duplicate_row','row'=>$i+1]);$id=bh_sync_normalize_id($row['activity_id']??$row['id']??null);if($id===null)stage_reply(422,['ok'=>false,'error'=>'activity_id_required','row'=>$i+1]);$row['activity_id']=$id;unset($row['id']);foreach(array_keys($row) as $field)if(!in_array($field,['activity_id','title','description','age_range','price_from','booking_url'],true))unset($row[$field]);if(!isset($row['title'])||!is_string($row['title'])||trim($row['title'])===''||strlen($row['title'])>500)stage_reply(422,['ok'=>false,'error'=>'valid_title_required','row'=>$i+1]);$data['rows'][$i]=$row;$seen[]=$id;}
if(count(array_unique($seen))!==count($seen))stage_reply(422,['ok'=>false,'error'=>'duplicate_activity_id']);
$key=hash('sha256',json_encode(['spreadsheet'=>$spreadsheet,'tab'=>$tab,'rows'=>$data['rows']],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
try{require_once __DIR__.'/db.php';$db=bh_mysql();$q=$db->prepare('INSERT INTO bh_sheets_override_batches (batch_key,source_spreadsheet,source_tab,row_count,rows_json,status) VALUES (?,?,?,?,?,\'prepared\')');$q->execute([$key,$spreadsheet,$tab,count($data['rows']),json_encode($data['rows'],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)]);stage_reply(200,['ok'=>true,'mode'=>'prepared_for_admin_review','batch_id'=>(int)$db->lastInsertId(),'rows_received'=>count($data['rows']),'activity_writes_performed'=>0]);}catch(PDOException $e){if((string)$e->getCode()==='23000')stage_reply(409,['ok'=>false,'error'=>'duplicate_batch']);stage_reply(503,['ok'=>false,'error'=>'batch_storage_unavailable']);}catch(Throwable $e){stage_reply(503,['ok'=>false,'error'=>'batch_storage_unavailable']);}
