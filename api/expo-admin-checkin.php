<?php
declare(strict_types=1);
/** Staging-only Expo admin ticket check-in. Never expose bearer ticket tokens in logs. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bh_expo_admin_reply(int $status,array $data): never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    header('Allow: POST');
    bh_expo_admin_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk'){
    bh_expo_admin_reply(404,['ok'=>false,'error'=>'not_found']);
}
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!=='' && $origin!=='https://staging.bubbahub.co.uk'){
    bh_expo_admin_reply(403,['ok'=>false,'error'=>'invalid_origin']);
}
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin==='' && ($referer==='' || parse_url($referer,PHP_URL_HOST)!=='staging.bubbahub.co.uk' || parse_url($referer,PHP_URL_SCHEME)!=='https')){
    bh_expo_admin_reply(403,['ok'=>false,'error'=>'origin_required']);
}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated'])){
    bh_expo_admin_reply(401,['ok'=>false,'error'=>'admin_login_required']);
}
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if($last<1 || time()-$last>1800){
    unset($_SESSION['bh_admin_authenticated'],$_SESSION['bh_admin_csrf']);
    bh_expo_admin_reply(401,['ok'=>false,'error'=>'admin_session_expired']);
}
$_SESSION['bh_admin_last_activity']=time();
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf==='' || $expected==='' || !hash_equals($expected,$csrf)){
    bh_expo_admin_reply(403,['ok'=>false,'error'=>'invalid_csrf']);
}
if((int)($_SERVER['CONTENT_LENGTH']??0)>1024){
    bh_expo_admin_reply(413,['ok'=>false,'error'=>'request_too_large']);
}
$data=json_decode((string)file_get_contents('php://input',false,null,0,1025),true);
$payload=is_array($data)?(string)($data['qr_payload']??''):'';
require_once __DIR__.'/expo-ticket-payload.php';
$token=bh_expo_parse_ticket_qr_payload($payload);
if($token===null)bh_expo_admin_reply(422,['ok'=>false,'error'=>'invalid_ticket']);
require_once __DIR__.'/db.php';
require_once __DIR__.'/expo-checkin-core.php';
try{
    $db=bh_mysql();
    $result=bh_expo_check_in_adult_ticket($db,$token);
    bh_expo_admin_reply($result['ok']?200:409,$result);
}catch(Throwable $e){
    error_log('Expo admin check-in unavailable: '.get_class($e));
    bh_expo_admin_reply(503,['ok'=>false,'error'=>'checkin_unavailable']);
}
