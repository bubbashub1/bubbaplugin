<?php
declare(strict_types=1);
/** Read-only admin-session token issuer for Expo confirmation and check-in. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhExpoSessionReply(int $status,array $data): never {
 http_response_code($status);
 echo json_encode($data,JSON_UNESCAPED_SLASHES);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');bhExpoSessionReply(405,['ok'=>false,'error'=>'method_not_allowed']);}
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(!in_array($host,['bubbahub.co.uk','staging.bubbahub.co.uk'],true))bhExpoSessionReply(404,['ok'=>false,'error'=>'not_found']);
if(session_status()!==PHP_SESSION_ACTIVE){
 ini_set('session.use_strict_mode','1');
 ini_set('session.use_only_cookies','1');
 session_name('BUBBAHUB_ADMINSESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
if(empty($_SESSION['bh_admin_authenticated']))bhExpoSessionReply(401,['ok'=>false,'error'=>'admin_login_required']);
if(empty($_SESSION['bh_admin_csrf'])||!is_string($_SESSION['bh_admin_csrf'])){
 $_SESSION['bh_admin_csrf']=bin2hex(random_bytes(32));
}
$_SESSION['bh_admin_last_activity']=time();
bhExpoSessionReply(200,['ok'=>true,'csrf'=>$_SESSION['bh_admin_csrf']]);
