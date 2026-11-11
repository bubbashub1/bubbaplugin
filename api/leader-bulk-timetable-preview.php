<?php
declare(strict_types=1);
/** Bulk schedule selection preview: no writes, no term-date assumptions. */
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function bulk_reply(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_user_id']))bulk_reply(401,['ok'=>false,'error'=>'login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')bulk_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$uid=(int)$_SESSION['bh_user_id'];
 $u=$db->prepare("SELECT email,role,status FROM bh_users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();
 if(!$user||$user['role']!=='leader'||$user['status']!=='active')bulk_reply(403,['ok'=>false,'error'=>'leader_required']);
 $payload=json_decode((string)file_get_contents('php://input'),true);
 if(!is_array($payload)||!is_array($payload['schedule_ids']??null))bulk_reply(422,['ok'=>false,'error'=>'schedule_ids_required']);
 $ids=array_values(array_unique(array_map('intval',$payload['schedule_ids'])));
 if(!$ids||count($ids)>100||min($ids)<1)bulk_reply(422,['ok'=>false,'error'=>'select_1_to_100_schedules']);
 $hasUserId=(bool)$db->query("SHOW COLUMNS FROM bh_organisers LIKE 'user_id'")->fetch();
 $join=$hasUserId?'o.user_id=?':'o.email=?';
 $placeholders=implode(',',array_fill(0,count($ids),'?'));
 $q=$db->prepare("SELECT s.id,s.activity_id,s.venue_id,s.day_of_week,s.start_time,s.end_time,s.start_date,s.end_date,s.active,a.title FROM bh_activity_schedules s JOIN bh_activities a ON a.id=s.activity_id JOIN bh_organisers o ON o.id=a.organiser_id WHERE ".$join." AND s.id IN (".$placeholders.") ORDER BY a.title,s.day_of_week,s.start_time");
 $q->execute(array_merge([$hasUserId?$uid:$user['email']],$ids));$rows=$q->fetchAll();
 if(count($rows)!==count($ids))bulk_reply(403,['ok'=>false,'error'=>'some_schedules_not_owned_or_missing']);
 bulk_reply(200,['ok'=>true,'count'=>count($rows),'schedules'=>$rows,'preview_only'=>true,'changes_applied'=>false]);
}catch(Throwable $e){error_log('Bulk timetable preview: '.$e->getMessage());bulk_reply(500,['ok'=>false,'error'=>'preview_unavailable']);}
