<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
require_once __DIR__.'/db.php';
session_start();
register_shutdown_function(function(){
 $e=error_get_last();
 if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
  if(!headers_sent()) header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'leader_portal_fatal','message'=>$e['message']],JSON_UNESCAPED_SLASHES);
 }
});

function lp(int $s,array $d): void{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['bh_user_id'])) lp(401,['ok'=>false,'error'=>'login_required']);
$userId=(int)$_SESSION['bh_user_id']; $db=bh_mysql();
$o=$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1");$o->execute([$userId]);$org=$o->fetch();
if(!$org) lp(403,['ok'=>false,'error'=>'organiser_required']);
$oid=(int)$org['id'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $a=$db->prepare("SELECT id,title,description,category,age_range,county,price_from,booking_url,image_path,status FROM bh_activities WHERE organiser_id=? AND status<>'archived' ORDER BY title");$a->execute([$oid]);$classes=$a->fetchAll();
 foreach($classes as &$c){$v=$db->prepare("SELECT id,venue_name,address,town,region,postcode,latitude,longitude,notes FROM bh_venues WHERE activity_id=? ORDER BY id");$v->execute([(int)$c['id']);$c['venues']=$v->fetchAll();foreach($c['venues'] as &$venue){$s=$db->prepare("SELECT id,day_of_week,start_time,end_time,price,term_time_only,frequency,start_date,end_date FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");$s->execute([(int)$venue['id']]);$venue['sessions']=$s->fetchAll();}}
 unset($c,$venue);
 $bookings=[];
 try {
  $bookingCheck=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_booking_reservations','bh_booking_slots','bh_users')");
  if((int)$bookingCheck->fetchColumn()===3){
   $r=$db->prepare("SELECT br.id,br.status,br.quantity,br.created_at,bs.starts_at,bs.ends_at,bs.activity_id,bs.venue_id,a.title,v.venue_name,u.email FROM bh_booking_reservations br JOIN bh_booking_slots bs ON bs.id=br.slot_id JOIN bh_activities a ON a.id=bs.activity_id JOIN bh_venues v ON v.id=bs.venue_id JOIN bh_users u ON u.id=br.user_id WHERE a.organiser_id=? ORDER BY bs.starts_at DESC,br.id DESC");
   $r->execute([$oid]);
   $bookings=$r->fetchAll();
  }
 } catch(Throwable $bookingError) {
  // Booking data is optional for the leader dashboard. Do not prevent classes/venues loading.
  $bookings=[];
 }
 lp(200,['ok'=>true,'organisation'=>$org,'classes'=>$classes,'bookings'=>$bookings]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')lp(405,['ok'=>false,'error'=>'method_not_allowed']);
$b=json_decode(file_get_contents('php://input'),true);if(!is_array($b))lp(400,['ok'=>false,'error'=>'invalid_json']);
$action=$b['action']??'';
if($action==='save_class'){
 $id=(int)($b['id']??0);$title=trim((string)($b['title']??''));$description=trim((string)($b['description']??''));$category=trim((string)($b['category']??''));$age=trim((string)($b['age_range']??''));$price=($b['price_from']??'')===''?null:(float)$b['price_from'];$url=trim((string)($b['booking_url']??''));
 if($title==='')lp(422,['ok'=>false,'error'=>'title_required']);
 $q=$db->prepare("UPDATE bh_activities SET title=?,description=?,category=?,age_range=?,price_from=?,booking_url=? WHERE id=? AND organiser_id=?");$q->execute([$title,$description,$category,$age,$price,$url,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'class_not_found']);lp(200,['ok'=>true]);
}
if($action==='save_venue'){
 $id=(int)($b['id']??0);$fields=['venue_name','address','town','region','postcode','latitude','longitude','notes'];$vals=[];foreach($fields as $f)$vals[]=trim((string)($b[$f]??''));$q=$db->prepare("UPDATE bh_venues v JOIN bh_activities a ON a.id=v.activity_id SET v.venue_name=?,v.address=?,v.town=?,v.region=?,v.postcode=?,v.latitude=NULLIF(?,''),v.longitude=NULLIF(?,''),v.notes=? WHERE v.id=? AND a.organiser_id=?");$q->execute([...$vals,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'venue_not_found']);lp(200,['ok'=>true]);
}
if($action==='save_booking'){
 $id=(int)($b['id']??0);$status=$b['status']??'';if(!in_array($status,['reserved','confirmed','cancelled','attended'],true))lp(422,['ok'=>false,'error'=>'invalid_status']);$q=$db->prepare("UPDATE bh_booking_reservations br JOIN bh_booking_slots bs ON bs.id=br.slot_id JOIN bh_activities a ON a.id=bs.activity_id SET br.status=? WHERE br.id=? AND a.organiser_id=?");$q->execute([$status,$id,$oid]);if(!$q->rowCount())lp(404,['ok'=>false,'error'=>'booking_not_found']);lp(200,['ok'=>true]);
}
lp(400,['ok'=>false,'error'=>'unknown_action']);
} catch (Throwable $e) {
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'leader_portal_error','message'=>$e->getMessage()], JSON_UNESCAPED_SLASHES);
 exit;
}