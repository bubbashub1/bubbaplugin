<?php
declare(strict_types=1);
/** Admin-only staging Expo fixture creator. Does not send email or open bookings. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoFixtureReply(int $status,array $body): never {
    http_response_code($status);
    echo json_encode($body,JSON_UNESCAPED_SLASHES);
    exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
    header('Allow: POST');
    expoFixtureReply(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if(strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk'){
    expoFixtureReply(404,['ok'=>false,'error'=>'not_found']);
}
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
if($origin!=='https://staging.bubbahub.co.uk'){
    expoFixtureReply(403,['ok'=>false,'error'=>'invalid_origin']);
}
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$last=(int)($_SESSION['bh_admin_last_activity']??0);
if(empty($_SESSION['bh_admin_authenticated']) || $last<1 || time()-$last>1800){
    expoFixtureReply(401,['ok'=>false,'error'=>'admin_login_required']);
}
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
$expected=(string)($_SESSION['bh_admin_csrf']??'');
if($csrf==='' || $expected==='' || !hash_equals($expected,$csrf)){
    expoFixtureReply(403,['ok'=>false,'error'=>'invalid_csrf']);
}
$_SESSION['bh_admin_last_activity']=time();
session_write_close();
if((int)($_SERVER['CONTENT_LENGTH']??0)>512){
    expoFixtureReply(413,['ok'=>false,'error'=>'request_too_large']);
}
$data=json_decode((string)file_get_contents('php://input',false,null,0,513),true);
if(!is_array($data) || ($data['confirm']??'')!=='CREATE_STAGING_TEST'){
    expoFixtureReply(422,['ok'=>false,'error'=>'confirmation_required']);
}
$adults=filter_var($data['adults']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>4]]);
$children=filter_var($data['children']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>8]]);
if($adults===false || $children===false || $adults+$children>12){
    expoFixtureReply(422,['ok'=>false,'error'=>'invalid_attendees']);
}
require_once __DIR__.'/db.php';
$db=null;
try{
    $db=bh_mysql();
    $db->beginTransaction();
    $reference=strtoupper(bin2hex(random_bytes(12)));
    $stmt=$db->prepare("INSERT INTO bh_expo_reservations
        (public_reference,event_key,contact_name,contact_email,adults,children,ticket_type,amount_pence,payment_status,booking_status,email_status,consent_at)
        VALUES (?,'bubba-me-2027','STAGING TEST — DO NOT ADMIT','test-fixture@invalid.example',?,?,'free',0,'not_required','confirmed','not_sent',UTC_TIMESTAMP())");
    $stmt->execute([$reference,$adults,$children]);
    $id=(int)$db->lastInsertId();
    $insert=$db->prepare("INSERT INTO bh_expo_tickets(reservation_id,ticket_token,attendee_type) VALUES (?,?,'adult')");
    $payloads=[];
    require_once __DIR__.'/expo-ticket-payload.php';
    for($i=0;$i<$adults;$i++){
        $token=bin2hex(random_bytes(32));
        $insert->execute([$id,$token]);
        $payloads[]=bh_expo_ticket_qr_payload($token);
    }
    $db->commit();
    expoFixtureReply(201,['ok'=>true,'test_only'=>true,'reservation_id'=>$id,'reference'=>$reference,'adults'=>$adults,'children'=>$children,'qr_payloads'=>$payloads,'message'=>'Staging-only test reservation created. No email sent. Delete fixture after testing.']);
}catch(Throwable $e){
    if($db instanceof PDO && $db->inTransaction())$db->rollBack();
    error_log('Expo staging fixture creation failed: '.get_class($e));
    expoFixtureReply(503,['ok'=>false,'error'=>'test_fixture_unavailable']);
}
