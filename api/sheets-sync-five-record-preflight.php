<?php
declare(strict_types=1);
/** Bubba Hub staging-only, authenticated, read-only preflight. NO database writes. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function preflightReply(int $status,array $body):void {http_response_code($status);echo json_encode($body,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')preflightReply(405,['ok'=>false,'error'=>'post_required']);
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')preflightReply(403,['ok'=>false,'error'=>'staging_only']);
$config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$secret=(string)(getenv('BH_SYNC_TOKEN')?:(is_array($config)?($config['sheets_sync_token']??''):''));
if($secret===''){
 $path=dirname(__DIR__).'/wp-config.php';
 if(is_readable($path)){
  $src=file_get_contents($path);
  if(is_string($src)&&preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/',$src,$m))$secret=$m[2];
 }
}
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if($secret===''||!preg_match('/^Bearer (.+)$/i',$auth,$m)||!hash_equals($secret,trim($m[1])))preflightReply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input',false,null,0,8193);
if(!is_string($raw)||strlen($raw)>8192)preflightReply(413,['ok'=>false,'error'=>'payload_too_large']);
$input=json_decode($raw,true);
if(!is_array($input)||($input['action']??'')!=='five_record_preflight'||!isset($input['records'])||!is_array($input['records'])||count($input['records'])!==5)preflightReply(400,['ok'=>false,'error'=>'exactly_five_records_required']);
$expected=['2','3','4','5','6'];$ids=[];$slugs=[];
foreach($input['records'] as $r){
 if(!is_array($r)||!isset($r['id'],$r['title'],$r['slug'])||!is_string($r['id'])||!is_string($r['title'])||!is_string($r['slug']))preflightReply(400,['ok'=>false,'error'=>'invalid_record']);
 if(!preg_match('/^[1-9][0-9]*$/',$r['id'])||strlen($r['title'])<1||strlen($r['title'])>255||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$r['slug'])||strlen($r['slug'])>190)preflightReply(400,['ok'=>false,'error'=>'invalid_fields']);
 $ids[]=$r['id'];$slugs[]=$r['slug'];
}
if($ids!==$expected||count(array_unique($slugs))!==5)preflightReply(400,['ok'=>false,'error'=>'unexpected_ids_or_duplicate_slugs']);
try{
 require_once __DIR__.'/db.php';$pdo=bh_mysql();
 $columns=$pdo->query("SELECT COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities'")->fetchAll(PDO::FETCH_ASSOC);
 $available=array_column($columns,'COLUMN_NAME');
 foreach(['id','title','slug','status','organiser_id'] as $field)if(!in_array($field,$available,true))preflightReply(503,['ok'=>false,'error'=>'required_schema_missing']);
 $required=[];foreach($columns as $c)if($c['IS_NULLABLE']==='NO'&&$c['COLUMN_DEFAULT']===null&&stripos((string)$c['EXTRA'],'auto_increment')===false)$required[]=$c['COLUMN_NAME'];
 $orgCol=$columns[array_search('organiser_id',$available,true)];$orgNullable=$orgCol['IS_NULLABLE']==='YES';
 $idsSql=implode(',',array_fill(0,5,'?'));
 $stmt=$pdo->prepare("SELECT id,slug FROM bh_activities WHERE id IN ($idsSql) OR slug IN ($idsSql) LIMIT 50");
 $stmt->execute(array_merge(array_map('intval',$ids),$slugs));$collisions=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $indexRows=$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' ORDER BY INDEX_NAME,SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
 $results=[];foreach($input['records'] as $r){
  $hits=array_values(array_filter($collisions,static fn($c)=>(string)$c['id']===$r['id']||(string)$c['slug']===$r['slug']));
  $blockers=[];if($hits)$blockers[]='id_or_slug_collision';
  if(!$orgNullable)$blockers[]='organiser_id_required_for_draft';
  $blockers[]='organiser_and_venue_identity_not_approved';
  $blockers[]='draft_status_and_ownership_rules_not_verified';
  $results[]=['id'=>$r['id'],'title'=>$r['title'],'slug'=>$r['slug'],'collisions'=>$hits,'proposedStatus'=>'draft','eligibleForWrite'=>false,'blockers'=>$blockers,'proposedTableActions'=>['bh_activities'=>'INSERT_DRAFT_CANDIDATE_ONLY','bh_organisers'=>'MANUAL_REVIEW','bh_venues'=>'MANUAL_REVIEW','bh_activity_venues'=>'NO_ACTION','bh_sessions'=>'NO_ACTION','bh_sheet_sync_records'=>'PLAN_ONLY']];
 }
 preflightReply(200,['ok'=>true,'mode'=>'FIVE_RECORD_DB_PREFLIGHT_READ_ONLY','records'=>$results,'activityRequiredWithoutDefault'=>$required,'organiserIdNullable'=>$orgNullable,'indexes'=>$indexRows,'readyForWrites'=>false,'writesPerformed'=>false,'masterSheetModified'=>false,'liveWebsiteTouched'=>false]);
}catch(Throwable $e){error_log('Sheets preflight failed: '.$e->getMessage());preflightReply(500,['ok'=>false,'error'=>'preflight_failed']);}
