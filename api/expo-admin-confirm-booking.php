<?php
declare(strict_types=1);
/** Staging-only authenticated confirmation of a free Expo reservation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhExpoConfirmReply(int $code,array $body): never {http_response_code($code);echo json_encode($body,JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');bhExpoConfirmReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')bhExpoConfirmReply(404,['ok'=>false,'error'=>'not_found']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!==''&&$origin!=='https://staging.bubbahub.co.uk')bhExpoConfirmReply(403,['ok'=>false,'error'=>'invalid_origin']);
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin===''&&($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk'))bhExpoConfirmReply(403,['ok'=>false,'error'=>'origin_required']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']))bhExpoConfirmReply(401,['ok'=>false,'error'=>'admin_login_required']);
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1||time()-$last>1800){unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);bhExpoConfirmReply(401,['ok'=>false,'error'=>'admin_session_expired']);}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf===''||$expected===''||!hash_equals($expected,$csrf))bhExpoConfirmReply(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>512)bhExpoConfirmReply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input',false,null,0,513),true);
$id=$body['reservation_id']??null;
if(!is_array($body)||!is_int($id)||$id<1)bhExpoConfirmReply(422,['ok'=>false,'error'=>'invalid_reservation_id']);
require_once __DIR__.'/db.php';
require_once __DIR__.'/expo-trusted-free-confirmation.php';
try{
 $result=bh_expo_confirm_and_deliver_free_reservation(bh_mysql(),$id);
 bhExpoConfirmReply($result['ok']?200:409,$result);
}catch(Throwable $e){
 error_log('Expo admin confirmation unavailable: '.get_class($e));
 bhExpoConfirmReply(503,['ok'=>false,'error'=>'confirmation_unavailable']);
}
