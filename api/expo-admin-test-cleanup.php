<?php
declare(strict_types=1);
/** Remove a staging Expo test fixture after QR check-in testing. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $code,array $body): never {http_response_code($code);echo json_encode($body);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){header('Allow: POST');reply(405,['ok'=>false,'error'=>'method_not_allowed']);}
if(($_SERVER['HTTP_HOST']??'')!=='staging.bubbahub.co.uk')reply(404,['ok'=>false]);
if(($_SERVER['HTTP_ORIGIN']??'')!=='https://staging.bubbahub.co.uk')reply(403,['ok'=>false,'error'=>'invalid_origin']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated']) || (int)($_SESSION['bh_admin_last_activity']??0)<time()-1800)reply(401,['ok'=>false,'error'=>'admin_login_required']);
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
if($csrf==='' || empty($_SESSION['bh_admin_csrf']) || !hash_equals($_SESSION['bh_admin_csrf'],$csrf))reply(403,['ok'=>false,'error'=>'invalid_csrf']);
$_SESSION['bh_admin_last_activity']=time();
session_write_close();
if((int)($_SERVER['CONTENT_LENGTH']??0)>256)reply(413,['ok'=>false]);
$body=json_decode((string)file_get_contents('php://input',false,null,0,257),true);
$id=filter_var($body['reservation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id || ($body['confirm']??'')!=='DELETE_STAGING_TEST')reply(422,['ok'=>false,'error'=>'invalid_request']);
require_once __DIR__.'/db.php';
try{
    $db=bh_mysql();
    $stmt=$db->prepare("DELETE FROM bh_expo_reservations
        WHERE id=? AND event_key='bubba-me-2027'
        AND contact_name='STAGING TEST — DO NOT ADMIT'
        AND contact_email='test-fixture@invalid.example'
        AND booking_status='confirmed' AND email_status='not_sent'");
    $stmt->execute([$id]);
    reply($stmt->rowCount()===1?200:404,['ok'=>$stmt->rowCount()===1,'deleted'=>$stmt->rowCount()===1]);
}catch(Throwable $e){
    error_log('Expo fixture cleanup failed: '.get_class($e));
    reply(503,['ok'=>false,'error'=>'cleanup_unavailable']);
}
