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
// This API must never be used without a confirmed safe event capacity.\nif(!defined('BUBBAHUB_EXPO_VISITOR_CAPACITY') || !is_int(constant('BUBBAHUB_EXPO_VISITOR_CAPACITY')) || constant('BUBBAHUB_EXPO_VISITOR_CAPACITY')<1){\n expoBookingReply(503,['ok'=>false,'error'=>'capacity_not_configured']);\n}\nif((int)($_SERVER['CONTENT_LENGTH']??0)>8192)expoBookingReply(413,['ok'=>false,'error'=>'request_too_large']);
$raw=file_get_contents('php://input',false,null,0,8193);
$data=json_decode($raw?:'',true);
if(!is_array($data))expoBookingReply(400,['ok'=>false,'error'=>'invalid_json']);
$name=trim((string)($data['contact_name']??''));
$email=trim((string)($data['contact_email']??''));
$adult=$data['adults']??null;
$child=$data['children']??null;
$consent=$data['contact_consent']??false;
if(strlen($name)<2||strlen($name)>150||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190
 || !is_int($adult)||!is_int($child)||$adult<1||$child<0||($adult+$child)<1||($adult+$child)>12||$consent!==true){
 expoBookingReply(422,['ok'=>false,'error'=>'invalid_booking_details']);
}
try {
 require __DIR__.'/db.php';
 $db=bh_mysql();
 // Serialize capacity checks across requests using a database advisory lock.\n $lock=$db->prepare('SELECT GET_LOCK(?,5)');\n $lock->execute(['expo-capacity-bubba-me-2027']);\n if((int)$lock->fetchColumn()!==1)expoBookingReply(503,['ok'=>false,'error'=>'reservation_busy']);\n $db->beginTransaction();\n $count=$db->query("SELECT COALESCE(SUM(adults+children),0) FROM bh_expo_reservations WHERE event_key='bubba-me-2027' AND booking_status IN ('pending','confirmed')");\n if((int)$count->fetchColumn()+$adult+$child>constant('BUBBAHUB_EXPO_VISITOR_CAPACITY')){\n  $db->rollBack();\n  $db->query("SELECT RELEASE_LOCK('expo-capacity-bubba-me-2027')");\n  expoBookingReply(409,['ok'=>false,'error'=>'capacity_reached']);\n }
 $ref=strtoupper(bin2hex(random_bytes(12)));
 $q=$db->prepare("INSERT INTO bh_expo_reservations (public_reference,event_key,contact_name,contact_email,adults,children,ticket_type,amount_pence,payment_status,booking_status,email_status,consent_at) VALUES (?,'bubba-me-2027',?,?,?,?,'free',0,'not_required','pending','not_sent',UTC_TIMESTAMP())");
 $q->execute([$ref,$name,$email,$adult,$child]);
 $reservationId=(int)$db->lastInsertId();
 $ticket=$db->prepare('INSERT INTO bh_expo_tickets (reservation_id,ticket_token,attendee_type) VALUES (?,?,?)');
 for($i=0;$i<$adult;$i++)$ticket->execute([$reservationId,bin2hex(random_bytes(32)),'adult']);
 $db->commit();\n $db->query("SELECT RELEASE_LOCK('expo-capacity-bubba-me-2027')");
 // Never claim confirmation before email delivery is implemented.
 expoBookingReply(202,['ok'=>true,'reference'=>$ref,'status'=>'pending','message'=>'Reservation recorded but not confirmed.']);
} catch(Throwable $error){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();\n if(isset($db)&&$db instanceof PDO){try{$db->query("SELECT RELEASE_LOCK('expo-capacity-bubba-me-2027')");}catch(Throwable $ignored){}}
 error_log('Expo reservation failed: '.get_class($error));
 expoBookingReply(503,['ok'=>false,'error'=>'reservation_unavailable']);
}
