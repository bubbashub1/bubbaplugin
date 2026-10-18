<?php
declare(strict_types=1);
/** Staging-only, token-authenticated read-only audit of activity draft/ownership conventions. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function draftAuditReply(int $code,array $data):void {http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')draftAuditReply(405,['ok'=>false,'error'=>'post_required']);
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')draftAuditReply(403,['ok'=>false,'error'=>'staging_only']);
$config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$secret=(string)(getenv('BH_SYNC_TOKEN')?:(is_array($config)?($config['sheets_sync_token']??''):''));
if($secret===''){$file=dirname(__DIR__).'/wp-config.php';if(is_readable($file)){$source=file_get_contents($file);if(is_string($source)&&preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/',$source,$m))$secret=$m[2];}}
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if($secret===''||!preg_match('/^Bearer (.+)$/i',$auth,$m)||!hash_equals($secret,trim($m[1])))draftAuditReply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input',false,null,0,1025);
if(!is_string($raw)||strlen($raw)>1024)draftAuditReply(413,['ok'=>false,'error'=>'payload_too_large']);
$input=json_decode($raw,true);
if(!is_array($input)||($input['action']??'')!=='draft_ownership_audit'||array_diff(array_keys($input),['action']))draftAuditReply(400,['ok'=>false,'error'=>'invalid_payload']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $cols=$db->query("SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME IN ('id','status','leader_id','created_by','organiser_id','published_at','title','slug') ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
 $colNames=array_column($cols,'COLUMN_NAME');
 foreach(['id','status','organiser_id','leader_id','created_by'] as $name)if(!in_array($name,$colNames,true))draftAuditReply(503,['ok'=>false,'error'=>'expected_column_missing']);
 $groups=$db->query("SELECT COALESCE(status,'(NULL)') AS status,COUNT(*) AS count,SUM(organiser_id IS NULL) AS without_organiser,SUM(leader_id IS NULL) AS without_leader,SUM(created_by IS NULL) AS without_creator FROM bh_activities GROUP BY status ORDER BY status")->fetchAll(PDO::FETCH_ASSOC);
 $stmt=$db->prepare("SELECT id,status,organiser_id IS NULL AS organiser_missing,leader_id IS NULL AS leader_missing,created_by IS NULL AS creator_missing,published_at IS NULL AS publication_date_missing FROM bh_activities WHERE id IN (900001,900002,900003,900004,900005,900006,900007,900008,900009,999999) ORDER BY id LIMIT 10");
 $stmt->execute();$samples=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $venueCount=$db->query("SELECT COUNT(*) FROM bh_activity_venues")->fetchColumn();
 $draftRows=$db->query("SELECT COUNT(*) FROM bh_activities WHERE LOWER(TRIM(COALESCE(status,'')))='draft'")->fetchColumn();
 draftAuditReply(200,['ok'=>true,'mode'=>'DRAFT_OWNERSHIP_AUDIT_READ_ONLY','activityColumns'=>$cols,'statusGroups'=>$groups,'testRecordFlags'=>$samples,'activityVenueLinkCount'=>(int)$venueCount,'draftActivityCount'=>(int)$draftRows,'directoryPublishedOnly'=>true,'sourceReviewRequired'=>true,'readyForWrites'=>false,'writesPerformed'=>false]);
}catch(Throwable $e){error_log('Draft ownership audit: '.$e->getMessage());draftAuditReply(500,['ok'=>false,'error'=>'draft_ownership_audit_failed']);}
