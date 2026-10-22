<?php
declare(strict_types=1);
/** Read-only health assessment. No external HTTP requests or database writes. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');session_start();
function health_reply(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated'])) health_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET') health_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $id=max(0,(int)($_GET['id']??0));
 if(!$id) health_reply(422,['ok'=>false,'error'=>'activity_id_required']);
 $q=$db->prepare("SELECT a.id,a.title,a.description,a.booking_url,a.booking_required,a.image_path,a.age_range,a.organiser_id,COUNT(DISTINCT v.id) AS venue_count,COUNT(DISTINCT s.id) AS schedule_count,MAX(s.end_date) AS last_schedule_end FROM bh_activities a LEFT JOIN bh_venues v ON v.activity_id=a.id LEFT JOIN bh_activity_schedules s ON s.activity_id=a.id AND s.active=1 WHERE a.id=? GROUP BY a.id");
 $q->execute([$id]);$a=$q->fetch();
 if(!$a) health_reply(404,['ok'=>false,'error'=>'listing_not_found']);
 $findings=[];
 $add=static function(string $rule,string $message,string $severity='warning')use(&$findings):void{$findings[]=['rule'=>$rule,'severity'=>$severity,'message'=>$message];};
 if(trim((string)$a['title'])==='')$add('missing_title','Activity title is missing.','critical');
 if(trim(strip_tags((string)$a['description']))==='')$add('missing_description','Add a helpful class description.');
 if((int)$a['venue_count']===0)$add('missing_venue','No venue is linked.');
 if((int)$a['schedule_count']===0)$add('missing_schedule','No active weekly schedule is linked.');
 if((int)$a['booking_required']===1 && trim((string)$a['booking_url'])==='')$add('missing_booking_url','Booking is required but no booking link is set.');
 if(trim((string)$a['image_path'])==='')$add('missing_image','Add a listing image.','info');
 if(trim((string)$a['age_range'])==='')$add('missing_age_range','Add an age range.');
 if(!empty($a['last_schedule_end']) && $a['last_schedule_end']<date('Y-m-d'))$add('expired_schedule','All dated schedules appear to have ended.');
 health_reply(200,['ok'=>true,'activity_id'=>$id,'findings'=>$findings,'score'=>max(0,100-count($findings)*12),'read_only'=>true]);
}catch(Throwable $e){error_log('Listing health: '.$e->getMessage());health_reply(500,['ok'=>false,'error'=>'health_check_unavailable']);}
