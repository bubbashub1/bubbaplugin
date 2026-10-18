<?php
declare(strict_types=1);
/**
 * Bubba & Me Expo: free-ticket reservation API.
 * DISABLED by default. Do not enable until anti-bot, rate limiting,
 * email confirmation, cancellation and end-to-end tests are complete.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expoBookingReply(int $code,array $body): never {
 http_response_code($code);
 echo json_encode($body,JSON_UNESCAPED_SLASHES);
 exit;
}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
 header('Allow: POST');
 expoBookingReply(405,['ok'=>false,'error'=>'method_not_allowed']);
}
if(!defined('BUBBAHUB_EXPO_BOOKINGS_ENABLED') || constant('BUBBAHUB_EXPO_BOOKINGS_ENABLED')!==true){
 expoBookingReply(503,['ok'=>false,'error'=>'bookings_not_open']);
}
if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)expoBookingReply(413,['ok'=>false,'error'=>'request_too_large']);
$raw=file_get_contents('php://input',false,null,0,8193);
$data=json_decode($raw?:'',true);
if(!is_array($data))expoBookingReply(400,['ok'=>false,'error'=>'invalid_json']);
$name=trim((string)($data['contact_name']??''));
$email=trim((string)($data['contact_email']??''));
$adult=$data['adults']??null;
$child=$data['children']??null;
$consent=$data['contact_consent']??false;
if(mb_strlen($name)<2||mb_strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190
 || !is_int($adult)||!is_int($child)||$adult<0||$child<0||($adult+$child)<1||($adult+$child)>12||$consent!==true){
 expoBookingReply(422,['ok'=>false,'error'=>'invalid_booking_details']);
}
try {
 require __DIR__.'/db.php';
 $db=bh_mysql();
 $db->beginTransaction();
 $ref=strtoupper(bin2hex(random_bytes(12)));
 $q=$db->prepare("INSERT INTO bh_expo_reservations (public_reference,event_key,contact_name,contact_email,adults,children,ticket_type,amount_pence,payment_status,booking_status,email_status,consent_at) VALUES (?,'bubba-me-2027',?,?,?,?,'free',0,'not_required','pending','not_sent',UTC_TIMESTAMP())");
 $q->execute([$ref,$name,$email,$adult,$child]);
 $reservationId=(int)$db->lastInsertId();
 $ticket=$db->prepare('INSERT INTO bh_expo_tickets (reservation_id,ticket_token,attendee_type) VALUES (?,?,?)');
 foreach(['adult'=>$adult,'child'=>$child] as $type=>$count){
  for($i=0;$i<$count;$i++)$ticket->execute([$reservationId,bin2hex(random_bytes(32)),$type]);
 }
 $db->commit();
 // Never claim confirmation before email delivery is implemented.
 expoBookingReply(202,['ok'=>true,'reference'=>$ref,'status'=>'pending','message'=>'Reservation recorded but not confirmed.']);
} catch(Throwable $error){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 error_log('Expo reservation failed: '.get_class($error));
 expoBookingReply(503,['ok'=>false,'error'=>'reservation_unavailable']);
}
