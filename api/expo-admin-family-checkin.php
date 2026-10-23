<?php
declare(strict_types=1);
/** Staging-only authenticated partial family check-in via booking-level QR. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhFamilyReply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');bhFamilyReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')bhFamilyReply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&$origin!=='https://staging.bubbahub.co.uk')bhFamilyReply(403,['ok'=>false,'error'=>'invalid_origin']);
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin===''&&($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk'))bhFamilyReply(403,['ok'=>false,'error'=>'origin_required']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))bhFamilyReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);bhFamilyReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))bhFamilyReply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>1024)bhFamilyReply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input',false,null,0,1025),true);
if(!is_array($body))bhFamilyReply(400,['ok'=>false,'error'=>'invalid_json']);
$qr=$body['qr_payload']??null;$adults=$body['adults']??null;$children=$body['children']??null;
if(!is_string($qr)||!is_int($adults)||!is_int($children))bhFamilyReply(422,['ok'=>false,'error'=>'invalid_counts']);
require_once __DIR__.'/db.php';require_once __DIR__.'/expo-family-checkin-core.php';
try{
 $result=bh_expo_family_checkin(bh_mysql(),$qr,$adults,$children);
 bhFamilyReply($result['ok']?200:409,$result);
}catch(Throwable $e){
 error_log('Expo family check-in unavailable: '.get_class($e));
 bhFamilyReply(503,['ok'=>false,'error'=>'checkin_unavailable']);
}
