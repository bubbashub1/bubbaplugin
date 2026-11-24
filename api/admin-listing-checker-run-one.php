<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_lcro_reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if(empty($_SESSION['bh_admin_authenticated'])) bh_lcro_reply(401,['ok'=>false,'error'=>'Admin login required.']);
$_SESSION['bh_listing_checker_one_csrf']??=bin2hex(random_bytes(32));
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST'){
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
 $parts=$origin!==''?parse_url($origin):false;
 if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||strtolower((string)($parts['host']??''))!==preg_replace('/:\\d+$/','',$host)||in_array(strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??'')),['cross-site'],true)) bh_lcro_reply(403,['ok'=>false,'error'=>'This request could not be verified. Refresh the page and try again.']);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>8192) bh_lcro_reply(413,['ok'=>false,'error'=>'Request too large.']);
 $input=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($input)||!hash_equals((string)$_SESSION['bh_listing_checker_one_csrf'],(string)($input['csrf']??''))) bh_lcro_reply(403,['ok'=>false,'error'=>'Security check failed. Refresh the page and try again.']);
 $activityId=(int)($input['id']??0);
}else{
 if($method!=='GET') bh_lcro_reply(405,['ok'=>false,'error'=>'GET or POST required.']);
 $activityId=(int)($_GET['id']??0);
}
if($activityId<1) bh_lcro_reply(422,['ok'=>false,'error'=>'A valid listing is required.']);
require_once __DIR__.'/db.php';
try{
 $db=bh_mysql();
 foreach(['bh_listing_checker_settings','bh_listing_check_queue','bh_listing_check_findings'] as $table){
  $check=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$check->execute([$table]);
  if(!(int)$check->fetchColumn()) bh_lcro_reply(503,['ok'=>false,'error'=>'Listing Checker database migration is required.']);
 }
 $activity=$db->prepare('SELECT id FROM bh_activities WHERE id=?');$activity->execute([$activityId]);
 if(!$activity->fetchColumn()) bh_lcro_reply(404,['ok'=>false,'error'=>'Listing not found.']);
 if($method==='POST'){
  $lock=(int)$db->query("SELECT GET_LOCK('bh_listing_checker_manual_one',0)")->fetchColumn();
  if($lock!==1) bh_lcro_reply(409,['ok'=>false,'error'=>'Another manual listing check is being queued. Please try again shortly.']);
  try{
   $settings=[];foreach($db->query("SELECT setting_key,setting_value FROM bh_listing_checker_settings WHERE setting_key IN('health_run_now','health_manual_target_id')")->fetchAll(PDO::FETCH_ASSOC) as $row)$settings[$row['setting_key']]=$row['setting_value'];
   $activeTarget=(int)($settings['health_manual_target_id']??0);
   if(($settings['health_run_now']??'0')==='1'&&$activeTarget>0&&$activeTarget!==$activityId) bh_lcro_reply(409,['ok'=>false,'error'=>'Another individual check is already queued. Let that check finish before starting another.']);
   $db->beginTransaction();
   $queue=$db->prepare("INSERT INTO bh_listing_check_queue(check_type,target_id,target_term_id,status,attempts,next_check_at,last_error) VALUES('health',?,0,'pending',0,'2000-01-01 00:00:00',NULL) ON DUPLICATE KEY UPDATE status='pending',attempts=0,next_check_at='2000-01-01 00:00:00',last_error=NULL");
   $queue->execute([$activityId]);
   $save=$db->prepare("INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
   $save->execute(['health_manual_target_id',(string)$activityId]);$save->execute(['health_run_now','1']);
   $db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  finally{$db->query("SELECT RELEASE_LOCK('bh_listing_checker_manual_one')");}
  bh_lcro_reply(200,['ok'=>true,'message'=>'Individual check queued and prioritised for the next server worker run.']);
 }
 $queue=$db->prepare("SELECT status,last_checked_at,next_check_at,last_error FROM bh_listing_check_queue WHERE check_type='health' AND target_id=? AND target_term_id=0 LIMIT 1");$queue->execute([$activityId]);$queueRow=$queue->fetch(PDO::FETCH_ASSOC)?:null;
 $findings=$db->prepare("SELECT finding_type,review_status,checked_at FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? ORDER BY checked_at DESC,id DESC LIMIT 40");$findings->execute([$activityId]);
 $labels=['missing_description'=>'Missing description','missing_website'=>'Missing website','missing_booking_link'=>'Missing booking link','missing_email'=>'Missing email','missing_telephone'=>'Missing telephone','missing_facebook'=>'Missing Facebook page','missing_instagram'=>'Missing Instagram profile','missing_age_range'=>'Missing age range','missing_category'=>'Missing category','missing_location'=>'Missing location','missing_postcode'=>'Missing postcode','missing_image'=>'Missing listing image','invalid_url'=>'Invalid website or social URL','invalid_email'=>'Invalid email address','invalid_postcode'=>'Postcode format needs checking','past_session_date'=>'Past session date','stale_listing'=>'Listing may be out of date','possible_duplicate'=>'Possible duplicate listing','expired_active_schedules'=>'Active timetable has expired'];
 $rows=$findings->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['label']=$labels[$row['finding_type']]??ucwords(str_replace('_',' ',$row['finding_type']));unset($row);
 bh_lcro_reply(200,['ok'=>true,'csrf'=>$_SESSION['bh_listing_checker_one_csrf'],'queue'=>$queueRow,'findings'=>$rows]);
}catch(Throwable $e){error_log('Individual listing health check: '.$e->getMessage());bh_lcro_reply(500,['ok'=>false,'error'=>'Unable to load or queue this listing check.']);}
