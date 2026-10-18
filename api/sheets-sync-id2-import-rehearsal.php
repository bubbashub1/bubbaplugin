<?php
declare(strict_types=1);
/** Staging-only, read-only rehearsal of Cornwall Libraries (ID 2) draft insertion. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function id2Reply(int $status,array $data):void {http_response_code($status);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')id2Reply(405,['ok'=>false,'error'=>'post_required']);
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')id2Reply(403,['ok'=>false,'error'=>'staging_only']);
$config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$secret=(string)(getenv('BH_SYNC_TOKEN')?:(is_array($config)?($config['sheets_sync_token']??''):''));
if($secret===''){$file=dirname(__DIR__).'/wp-config.php';if(is_readable($file)){$src=file_get_contents($file);if(is_string($src)&&preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/',$src,$m))$secret=$m[2];}}
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if($secret===''||!preg_match('/^Bearer (.+)$/i',$auth,$m)||!hash_equals($secret,trim($m[1])))id2Reply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input',false,null,0,4097);
if(!is_string($raw)||strlen($raw)>4096)id2Reply(413,['ok'=>false,'error'=>'payload_too_large']);
$body=json_decode($raw,true);
if(!is_array($body)||array_keys($body)!==['action','record']||$body['action']!=='rehearse_id_2'||!is_array($body['record']))id2Reply(400,['ok'=>false,'error'=>'invalid_payload']);
$r=$body['record'];
if(array_keys($r)!==['id','title','slug']||$r['id']!=='2'||$r['title']!=='Cornwall Libraries'||$r['slug']!=='cornwall-libraries')id2Reply(422,['ok'=>false,'error'=>'id2_source_mismatch']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $stmt=$db->prepare('SELECT id,slug,status FROM bh_activities WHERE id=? OR slug=? LIMIT 10');$stmt->execute([2,'cornwall-libraries']);$hits=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $trace=$db->prepare('SELECT activity_id,sheet_row_id FROM bh_sheet_sync_records WHERE activity_id=? LIMIT 10');$trace->execute([2]);$traces=$trace->fetchAll(PDO::FETCH_ASSOC);
 $cols=$db->query("SELECT COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities'")->fetchAll(PDO::FETCH_ASSOC);
 $required=[];foreach($cols as $col){if($col['IS_NULLABLE']==='NO'&&$col['COLUMN_DEFAULT']===null&&stripos((string)$col['EXTRA'],'auto_increment')===false)$required[]=$col['COLUMN_NAME'];}
 $safeColumns=count(array_diff($required,['title','slug']))===0;
 $sourceHash=hash('sha256',json_encode($r,JSON_UNESCAPED_SLASHES));
 $blockers=[];if($hits)$blockers[]='activity_id_or_slug_collision';if($traces)$blockers[]='sync_trace_exists';if(!$safeColumns)$blockers[]='additional_required_columns';
 $blockers[]='source_field_mapping_not_approved';
 $blockers[]='public_detail_endpoint_access_not_verified';
 $blockers[]='staging_backup_not_confirmed';
 $blockers[]='explicit_operator_approval_required';
 id2Reply(200,['ok'=>true,'mode'=>'ID2_DRAFT_INSERT_REHEARSAL_READ_ONLY','record'=>$r,'sourceHash'=>$sourceHash,'collisions'=>$hits,'existingSyncTrace'=>$traces,'requiredWithoutDefault'=>$required,'proposedInsert'=>['table'=>'bh_activities','id'=>2,'title'=>'Cornwall Libraries','slug'=>'cornwall-libraries','status'=>'draft','organiser_id'=>null,'leader_id'=>null,'created_by'=>null,'published_at'=>null,'venues'=>[],'sessions'=>[]],'blockers'=>$blockers,'readyForWrites'=>false,'writesPerformed'=>false]);
}catch(Throwable $e){error_log('ID2 rehearsal: '.$e->getMessage());id2Reply(500,['ok'=>false,'error'=>'rehearsal_failed']);}
