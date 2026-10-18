<?php
declare(strict_types=1);
/** Read-only staging Expo schema readiness check. Admin session required. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoSchemaReply(int $code,array $body): never {http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');expoSchemaReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')expoSchemaReply(404,['ok'=>false,'error'=>'not_found']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))expoSchemaReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);expoSchemaReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$tables=['bh_expo_reservations','bh_expo_tickets','bh_expo_booking_qrs','bh_expo_family_checkins','bh_expo_family_email_delivery','bh_expo_giveaway_claims'];
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$result=[];
 $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
 foreach($tables as $table){$q->execute([$table]);$result[$table]=(int)$q->fetchColumn()===1;}
 $ready=!in_array(false,$result,true);
 expoSchemaReply(200,['ok'=>true,'schema_ready'=>$ready,'tables'=>$result,'writes_performed'=>false]);
}catch(Throwable $e){error_log('Expo schema check unavailable: '.get_class($e));expoSchemaReply(503,['ok'=>false,'error'=>'schema_check_unavailable']);}
