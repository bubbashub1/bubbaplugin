<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $code,array $data): never {http_response_code($code);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(!in_array($host,['bubbahub.co.uk','staging.bubbahub.co.uk'],true))reply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin!==''?$origin!=='https://'.$host:($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!==$host))reply(403,['ok'=>false,'error'=>'invalid_origin']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800)reply(401,['ok'=>false,'error'=>'admin_session_expired']);
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))reply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>1024)reply(413,['ok'=>false,'error'=>'request_too_large']);
$data=json_decode((string)file_get_contents('php://input',false,null,0,1025),true);
$qr=is_array($data)?($data['qr_payload']??null):null;
if(!is_string($qr)||!preg_match('/^BH-EXPO-2027:[a-f0-9]{64}$/D',$qr))reply(422,['ok'=>false,'error'=>'invalid_qr']);
try{
 require_once __DIR__.'/db.php';
 require_once __DIR__.'/expo-booking-qr-core.php';
 $db=bh_mysql();
 $booking=bh_expo_resolve_booking_qr($db,$qr);
 if(!$booking)reply(404,['ok'=>false,'error'=>'booking_not_found']);
 $q=$db->prepare('SELECT adults_checked_in,children_checked_in FROM bh_expo_reservations WHERE id=?');
 $q->execute([(int)$booking['id']]);
 $checked=$q->fetch(PDO::FETCH_ASSOC);
 reply(200,['ok'=>true,'reference'=>$booking['public_reference'],'adults'=>(int)$booking['adults'],'children'=>(int)$booking['children'],'already_checked_in'=>$checked&&((int)$checked['adults_checked_in']>0||(int)$checked['children_checked_in']>0)]);
}catch(Throwable $e){error_log('Expo QR lookup failed: '.get_class($e));reply(503,['ok'=>false,'error'=>'lookup_unavailable']);}
