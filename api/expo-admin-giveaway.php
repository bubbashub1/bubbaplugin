<?php
declare(strict_types=1);
/** Staging-only, authenticated giveaway claim. One claim per event + registered email. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoBagReply(int $code,array $data): never {
 http_response_code($code); echo json_encode($data,JSON_UNESCAPED_SLASHES); exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');expoBagReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')expoBagReply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!=='' && $origin!=='https://staging.bubbahub.co.uk')expoBagReply(403,['ok'=>false,'error'=>'invalid_origin']);
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin==='' && ($referer==='' || parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk' || parse_url($referer,PHP_URL_SCHEME)!=='https'))expoBagReply(403,['ok'=>false,'error'=>'origin_required']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))expoBagReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1 || time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);expoBagReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))expoBagReply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>1024)expoBagReply(413,['ok'=>false,'error'=>'request_too_large']);
$data=json_decode((string)file_get_contents('php://input',false,null,0,1025),true);
$payload=is_array($data)?(string)($data['qr_payload']??''):'';
require_once __DIR__.'/expo-ticket-payload.php';
$token=bh_expo_parse_ticket_qr_payload($payload);
if($token===null)expoBagReply(422,['ok'=>false,'error'=>'invalid_ticket']);
require_once __DIR__.'/db.php';
require_once dirname(__DIR__).'/includes/expo-booking-giveaway.php';
try {
 $db=bh_mysql();
 $db->beginTransaction();
 // QR resolves to a stored ticket, then a confirmed reservation. Never trust a client-supplied email.
 $q=$db->prepare("SELECT r.id,r.contact_email,r.event_key,r.booking_status FROM bh_expo_tickets t JOIN bh_expo_reservations r ON r.id=t.reservation_id WHERE t.ticket_token=? FOR UPDATE");
 $q->execute([$token]);
 $booking=$q->fetch(PDO::FETCH_ASSOC);
 if(!$booking || $booking['event_key']!=='bubba-me-2027' || $booking['booking_status']!=='confirmed'){
  $db->rollBack();expoBagReply(409,['ok'=>false,'error'=>'booking_not_confirmed']);
 }
 // The event id is derived from a fixed server-side mapping, not supplied by the client.
 // Keep it consistent with the event ID used by all Expo giveaway claims.
 $eventId=20270109;
 $email=bhExpoEmailKey((string)$booking['contact_email']);
 $adminId=(int)($_SESSION['bh_admin_user_id']??$_SESSION['admin_user_id']??0);
 if($adminId<=0){$db->rollBack();expoBagReply(403,['ok'=>false,'error'=>'admin_id_unavailable']);}
 $claimed=bhExpoClaimGiveaway($db,$eventId,$email,$adminId);
 $db->commit();
 expoBagReply(200,['ok'=>true,'status'=>$claimed?'collected':'already_collected','claimed'=>$claimed]);
} catch(Throwable $e) {
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 error_log('Expo giveaway claim unavailable: '.get_class($e));
 expoBagReply(503,['ok'=>false,'error'=>'giveaway_unavailable']);
}
