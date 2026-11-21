<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');session_start();
function aer(int $c,array $d):never{http_response_code($c);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))aer(401,['ok'=>false,'error'=>'admin_login_required']);
require_once __DIR__.'/db.php';$db=bh_mysql();
$exists=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_directory_events'");
if(!(int)$exists->fetchColumn())aer(503,['ok'=>false,'error'=>'events_schema_not_installed','message'=>'Events database setup is not installed on staging yet.']);
if($_SERVER['REQUEST_METHOD']==='GET'){
 $_SESSION['bh_admin_events_csrf']??=bin2hex(random_bytes(32));
 $rows=$db->query("SELECT e.id,e.title,e.summary,e.description,e.category,e.venue_name,e.town,e.region,e.status,e.submitted_at,e.created_at,o.organisation_name FROM bh_directory_events e LEFT JOIN bh_organisers o ON o.id=e.organiser_id WHERE e.status IN ('pending','draft') ORDER BY CASE e.status WHEN 'pending' THEN 0 ELSE 1 END,e.submitted_at,e.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
 aer(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_events_csrf'],'events'=>$rows]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')aer(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)aer(403,['ok'=>false,'error'=>'invalid_origin']);
$b=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($b)||empty($_SESSION['bh_admin_events_csrf'])||!is_string($b['csrf']??null)||!hash_equals($_SESSION['bh_admin_events_csrf'],$b['csrf']))aer(403,['ok'=>false,'error'=>'invalid_csrf']);
$id=filter_var($b['event_id']??null,FILTER_VALIDATE_INT);$decision=(string)($b['decision']??'');$note=trim((string)($b['note']??''));
if(!$id||$id<1||!in_array($decision,['approved','rejected','returned_for_edits'],true)||strlen($note)>2000)aer(422,['ok'=>false,'error'=>'invalid_review']);
try{
 $db->beginTransaction();$q=$db->prepare("SELECT id,status FROM bh_directory_events WHERE id=? FOR UPDATE");$q->execute([$id]);$event=$q->fetch(PDO::FETCH_ASSOC);
 if(!$event||!in_array($event['status'],['pending','draft'],true)){$db->rollBack();aer(409,['ok'=>false,'error'=>'event_not_reviewable']);}
 $newStatus=$decision==='approved'?'published':($decision==='rejected'?'rejected':'draft');
 $q=$db->prepare("UPDATE bh_directory_events SET status=?,reviewed_at=NOW(),review_note=? WHERE id=?");$q->execute([$newStatus,$note?:null,$id]);
 $q=$db->prepare("INSERT INTO bh_directory_event_reviews (event_id,reviewer_user_id,decision,note) VALUES (?,?,?,?)");$q->execute([$id,(int)($_SESSION['bh_user_id']??0)?:null,$decision,$note?:null]);
 $db->commit();aer(200,['ok'=>true,'event_id'=>$id,'status'=>$newStatus,'message'=>'Event review saved.']);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Admin event review: '.$e->getMessage());aer(500,['ok'=>false,'error'=>'event_review_failed']);}
