<?php
declare(strict_types=1);
/** Staging-only, admin-authenticated pending free Expo test reservation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhExpoTestReply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');bhExpoTestReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')bhExpoTestReply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin!==''&&$origin!=='https://staging.bubbahub.co.uk')bhExpoTestReply(403,['ok'=>false,'error'=>'invalid_origin']);
if($origin===''&&($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk'))bhExpoTestReply(403,['ok'=>false,'error'=>'origin_required']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))bhExpoTestReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);bhExpoTestReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))bhExpoTestReply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>2048)bhExpoTestReply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input',false,null,0,2049),true);
if(!is_array($body))bhExpoTestReply(422,['ok'=>false,'error'=>'invalid_request']);
$name=trim((string)($body['name']??''));
$email=trim((string)($body['email']??''));
$adults=filter_var($body['adults']??null,FILTER_VALIDATE_INT);
$children=filter_var($body['children']??null,FILTER_VALIDATE_INT);
if(strlen($name)<2||strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||$adults===false||$adults<1||$adults>12||$children===false||$children<0||$children>11||$body['consent']!==true)bhExpoTestReply(422,['ok'=>false,'error'=>'invalid_booking_details']);
require_once __DIR__.'/db.php';
try {
 $db=bh_mysql();
 $reference=strtoupper(bin2hex(random_bytes(12)));
 $q=$db->prepare("INSERT INTO bh_expo_reservations (public_reference,event_key,contact_name,contact_email,adults,children,ticket_type,amount_pence,payment_status,booking_status,email_status,consent_at) VALUES (?,'bubba-me-2027',?,?,?,?,'free',0,'not_required','pending','not_sent',UTC_TIMESTAMP())");
 $q->execute([$reference,$name,$email,$adults,$children]);
 $id=(int)$db->lastInsertId();
 bhExpoTestReply(201,['ok'=>true,'reservation_id'=>$id,'booking_status'=>'pending','email_sent'=>false,'test_only'=>true]);
} catch(Throwable $e) {
 error_log('Expo staging test reservation unavailable: '.get_class($e));
 bhExpoTestReply(503,['ok'=>false,'error'=>'test_reservation_unavailable']);
}
