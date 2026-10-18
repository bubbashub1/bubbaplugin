<?php
declare(strict_types=1);
/** Issue a short-lived session-bound CSRF token to an authenticated staging admin. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk'){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']) || (int)($_SESSION['bh_admin_last_activity']??0)<time()-1800){
 http_response_code(401);echo json_encode(['ok'=>false,'error'=>'admin_login_required']);exit;
}
$_SESSION['bh_admin_last_activity']=time();
if(empty($_SESSION['bh_admin_csrf']))$_SESSION['bh_admin_csrf']=bin2hex(random_bytes(32));
echo json_encode(['ok'=>true,'csrf'=>$_SESSION['bh_admin_csrf']]);
