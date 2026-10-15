<?php
declare(strict_types=1);
/** Read-only preview of a one-day timetable exception. No changes are applied. */
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function exception_reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_user_id']))exception_reply(401,['ok'=>false,'error'=>'login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')exception_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$uid=(int)$_SESSION['bh_user_id'];
 $u=$db->prepare("SELECT email,role,status FROM bh_users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();
 if(!$user||$user['role']!=='leader'||$user['status']!=='active')exception_reply(403,['ok'=>false,'error'=>'leader_required']);
 $scheduleId=(int)($_GET['schedule_id']??0);$date=(string)($_GET['date']??'');
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
 if($scheduleId<1||!$dt||$dt->format('Y-m-d')!==$date)exception_reply(422,['ok'=>false,'error'=>'valid_schedule_and_date_required']);
 $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
 $ownerJoin=$hasUserId?'o.user_id=:uid':'o.email=:email';
 $sql="SELECT s.id,s.activity_id,s.venue_id,s.day_of_week,s.start_time,s.end_time,s.start_date,s.end_date,s.active,a.title FROM bh_activity_schedules s JOIN bh_activities a ON a.id=s.activity_id JOIN bh_organisers o ON o.id=a.organiser_id WHERE s.id=:schedule_id AND ".$ownerJoin." LIMIT 1";
 $q=$db->prepare($sql);$q->execute($hasUserId?['schedule_id'=>$scheduleId,'uid'=>$uid]:['schedule_id'=>$scheduleId,'email'=>$user['email']]);$schedule=$q->fetch();
 if(!$schedule)exception_reply(404,['ok'=>false,'error'=>'schedule_not_found']);
 $day=(int)$dt->format('N');$storedDay=(int)$schedule['day_of_week'];
 $matchesDay=($storedDay===$day||$storedDay===$day%7);
 $inRange=(!$schedule['start_date']||$date>=$schedule['start_date'])&&(!$schedule['end_date']||$date<=$schedule['end_date']);
 exception_reply(200,['ok'=>true,'schedule'=>$schedule,'date'=>$date,'occurs_on_date'=>(bool)$schedule['active']&&$matchesDay&&$inRange,'preview_only'=>true]);
}catch(Throwable $e){error_log('Session exception preview: '.$e->getMessage());exception_reply(500,['ok'=>false,'error'=>'preview_unavailable']);}
