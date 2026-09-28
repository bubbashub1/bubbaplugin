<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
require_once __DIR__.'/db.php';
require_once __DIR__.'/onesignal-email.php';
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
$adminOnly=!empty($_SESSION['bh_admin_authenticated'])&&empty($_SESSION['bh_user_id']);
if(empty($_SESSION['bh_user_id'])&&!$adminOnly) lp(401,['ok'=>false,'error'=>'login_required']);
$userId=(int)($_SESSION['bh_user_id']??0); $db=bh_mysql();
$roleStmt=$db->prepare("SELECT role,status FROM bh_users WHERE id=? LIMIT 1");$roleStmt->execute([$userId]);$roleUser=$roleStmt->fetch();
if(!$adminOnly && (!$roleUser || $roleUser['status']!=='active' || ($roleUser['role']??'')!=='leader')) lp(403,['ok'=>false,'error'=>'leader_role_required','message'=>'A class leader account is required.']);
if($adminOnly){
 if($_SERVER['REQUEST_METHOD']==='GET') lp(200,['ok'=>true,'admin_mode'=>true,'organisation'=>null,'classes'=>[],'bookings'=>[]]);
 lp(403,['ok'=>false,'error'=>'admin_read_only','message'=>'Admin access can view the leader area, but leader account changes require a linked class leader account.']);
}
$hasOrgUserId=false;
try{$cc=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='user_id'");$cc->execute();$hasOrgUserId=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
$org=null;
if($hasOrgUserId){
 $o=$db->prepare("SELECT * FROM bh_organisers WHERE user_id=? LIMIT 1");$o->execute([$userId]);$org=$o->fetch();
}else{
 try{
  $uc=$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_users'");
  if((int)$uc->fetchColumn()===1){
   $u=$db->prepare("SELECT email FROM bh_users WHERE id=? LIMIT 1");$u->execute([$userId]);$user=$u->fetch();
   if($user && !empty($user['email'])){$o=$db->prepare("SELECT * FROM bh_organisers WHERE email=? LIMIT 1");$o->execute([$user['email']]);$org=$o->fetch();}
  }
 }catch(Throwable $ignored){}
}
if(!$org)lp(403,['ok'=>false,'error'=>'organiser_required','message'=>'Your account is not linked to a class leader organisation yet.']);
$oid=(int)$org['id'];
$leaderEmail='';
try{$eq=$db->prepare("SELECT email FROM bh_users WHERE id=? LIMIT 1");$eq->execute([$userId]);$leaderEmail=strtolower(trim((string)$eq->fetchColumn()));}catch(Throwable $ignored){$leaderEmail=strtolower(trim((string)($org['email']??'')));}
if($_SERVER['REQUEST_METHOD']==='GET'){
 $classes=[];
 try{
  $a=$db->prepare("SELECT * FROM bh_activities WHERE organiser_id=? AND status<>'archived' ORDER BY title");
  $a->execute([$oid]);$classes=$a->fetchAll();
 }catch(Throwable $activityError){
  lp(500,['ok'=>false,'error'=>'classes_query_failed','message'=>$activityError->getMessage()]);
 }
 foreach($classes as &$c){
  $c['venues']=[];
  try{
   $v=$db->prepare("SELECT * FROM bh_venues WHERE activity_id=? ORDER BY id");
   $v->execute([(int)$c['id']]);$c['venues']=$v->fetchAll();
  }catch(Throwable $venueError){
   // A class can still be displayed if an older database has a venue schema mismatch.
   $c['venues']=[];
  }
  foreach($c['venues'] as &$venue){
   $venue['sessions']=[];
   try{
    $s=$db->prepare("SELECT * FROM bh_sessions WHERE venue_id=? ORDER BY day_of_week,start_time");
    $s->execute([(int)$venue['id']]);$venue['sessions']=$s->fetchAll();
   }catch(Throwable $sessionError){
    // Sessions are optional; keep the venue visible rather than failing the whole portal.
    $venue['sessions']=[];
   }
  }
 }
 unset($c,$venue); $bookings=[];
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
if($action==='save_account'){
 $name=trim((string)($b['name']??''));$email=strtolower(trim((string)($b['email']??'')));
 if($name==='')lp(422,['ok'=>false,'error'=>'name_required']);
 if(!filter_var($email,FILTER_VALIDATE_EMAIL))lp(422,['ok'=>false,'error'=>'invalid_email']);
 $u=$db->prepare("UPDATE bh_users SET email=? WHERE id=?");$u->execute([$email,$userId]);
 $updated=false;
 foreach(['name','display_name','organisation_name'] as $col){
  try{$db->prepare("UPDATE bh_organisers SET $col=? WHERE id=?")->execute([$name,$oid]);$updated=true;break;}catch(Throwable $ignored){}
 }
 lp(200,['ok'=>true,'message'=>'Account details saved.']);
}
if($action==='change_password'){
 $current=(string)($b['current_password']??'');$new=(string)($b['new_password']??'');$confirm=(string)($b['confirm_password']??'');
 if(strlen($new)<8)lp(422,['ok'=>false,'error'=>'password_too_short','message'=>'Choose a password with at least 8 characters.']);
 if($new!==$confirm)lp(422,['ok'=>false,'error'=>'password_mismatch','message'=>'The passwords do not match.']);
 $q=$db->prepare("SELECT password_hash FROM bh_users WHERE id=? LIMIT 1");$q->execute([$userId]);$u=$q->fetch();
 if(!$u || !password_verify($current,(string)$u['password_hash']))lp(403,['ok'=>false,'error'=>'current_password_invalid','message'=>'Your current password is not correct.']);
 $db->prepare("UPDATE bh_users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$userId]);
 session_regenerate_id(true);$_SESSION['bh_user_id']=$userId;$_SESSION['bh_csrf']=bin2hex(random_bytes(24));
 lp(200,['ok'=>true,'csrf'=>$_SESSION['bh_csrf'],'message'=>'Password changed successfully.']);
}

$b=json_decode(file_get_contents('php://input'),true);if(!is_array($b))lp(400,['ok'=>false,'error'=>'invalid_json']);
$action=$b['action']??'';
if($action==='create_listing'){
 $title=trim((string)($b['title']??''));$description=trim((string)($b['description']??''));$category=trim((string)($b['category']??''));$age=trim((string)($b['age_range']??''));$price=($b['price_from']??'')===''?null:(float)$b['price_from'];$url=trim((string)($b['booking_url']??''));
 $venueName=trim((string)($b['venue_name']??''));$address=trim((string)($b['address']??''));$town=trim((string)($b['town']??''));$region=trim((string)($b['region']??''));$postcode=trim((string)($b['postcode']??''));$lat=trim((string)($b['latitude']??''));$lng=trim((string)($b['longitude']??''));
 if($title==='')lp(422,['ok'=>false,'error'=>'title_required']);
 if($venueName===''||$town==='')lp(422,['ok'=>false,'error'=>'venue_required']);
 $db->beginTransaction();
 try{
  // Build a unique slug because live databases may require this column.
  $baseSlug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title),'-'));
  if($baseSlug==='')$baseSlug='class';
  $slug=$baseSlug;$suffix=2;
  $slugCheck=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");
  while(true){$slugCheck->execute([$slug]);if(!$slugCheck->fetch())break;$slug=$baseSlug.'-'.$suffix++;}
  $countyExists=false;
  try{$cc=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");$cc->execute();$countyExists=((int)$cc->fetchColumn())>0;}catch(Throwable $ignored){}
  if($countyExists){
   $q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,county,price_from,booking_url,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
   $q->execute([$title,$slug,$description,$category,$age,'',$price,$url,'draft',$oid]);
  }else{
   $q=$db->prepare("INSERT INTO bh_activities (title,slug,description,category,age_range,price_from,booking_url,status,organiser_id) VALUES (?,?,?,?,?,?,?,?,?)");
   $q->execute([$title,$slug,$description,$category,$age,$price,$url,'draft',$oid]);
  }
  $activityId=(int)$db->lastInsertId();
  $v=$db->prepare("INSERT INTO bh_venues (venue_name,address,town,region,postcode,latitude,longitude,notes,activity_id) VALUES (?,?,?,?,?,?,?,?,?)");
  $v->execute([$venueName,$address,$town,$region,$postcode,$lat===''?null:$lat,$lng===''?null:$lng,'',$activityId]);
  $db->commit();

  // Send the listing confirmation after the database transaction succeeds.
  // Email delivery is non-fatal: a OneSignal/config problem must never make
  // the listing submission itself fail.
  if($leaderEmail!==''){
   $safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
   $safeOrg=htmlspecialchars((string)($org['organisation_name']??''),ENT_QUOTES,'UTF-8');
   $html='<div style="font-family:Arial,sans-serif;line-height:1.6;color:#26352b;max-width:640px;margin:0 auto">'
        .'<h1 style="color:#617261">Welcome to Bubba Hub</h1>'
        .'<p>Hi'.($safeOrg!==''?' '.$safeOrg:'').',</p>'
        .'<p>Thanks for adding <strong>'.$safeTitle.'</strong> to Bubba Hub.</p>'
        .'<p>Your listing has been submitted and is currently awaiting review. We will let you know when there is an update.</p>'
        .'<p>You can sign in to your Class Leader account at any time to manage your listings.</p>'
        .'<p>Thanks for being part of the Bubba Hub community 💚</p>'
        .'<p>The Bubba Hub team</p>'
        .'</div>';
   $plain="Welcome to Bubba Hub\n\nThanks for adding {$title} to Bubba Hub.\n\nYour listing has been submitted and is currently awaiting review. We will let you know when there is an update.\n\nThe Bubba Hub team";
   try{bh_send_onesignal_email($leaderEmail,'Welcome to Bubba Hub – your listing has been submitted',$html,$plain);}catch(Throwable $ignored){}
  }

  lp(201,['ok'=>true,'id'=>$activityId]);
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
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