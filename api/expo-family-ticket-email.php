<?php
declare(strict_types=1);
/**
 * Private Expo family QR email delivery.
 * The QR is a bearer credential. Call only from a controlled confirmation
 * workflow with a freshly issued, securely retained payload. Never log it.
 * This function does not mark the booking confirmed or email sent.
 */
function bh_expo_send_family_qr_email(PDO $db,array $reservation,string $qrPayload): bool {
 if(($reservation['booking_status']??'')!=='confirmed')return false;
 $id=(int)($reservation['id']??0);
 $email=trim((string)($reservation['contact_email']??''));
 if($id<1||!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
 require_once __DIR__.'/expo-booking-qr-core.php';
 $stage='resolve_booking';
 try {
 $booking=bh_expo_resolve_booking_qr($db,$qrPayload);
 if(!$booking||(int)$booking['id']!==$id)return false;
 require_once __DIR__.'/expo-confirmation.php';
 require_once __DIR__.'/expo-qr-image.php';
 require_once __DIR__.'/expo-ticket-mailer.php';
 $stage='confirmation_template';
 $message=bh_expo_confirmation_content($reservation);
 $html=$message['html'];
 $html=str_replace('Your adult QR tickets will be provided separately once ticket delivery is enabled.',
  'Show this single family QR at the entrance. Staff will record adults and children arriving, including separate arrivals.',$html);
 $html=str_replace('</div></body></html>',
  '<div style="margin-top:20px"><h2>Your family entry QR</h2><p><img src="cid:expo-family" alt="Family booking QR" width="320" height="320"></p><p>One QR per booking. Keep this email private.</p></div></div></body></html>',$html);
 $plain=str_replace('Adult QR tickets will be provided separately once delivery is enabled.',
  'One family QR is included as an inline image. Show it on arrival.',$message['plain']);
 $stage='render_qr_png';
 $png=bh_expo_adult_ticket_qr_png($qrPayload); // Shared strict payload syntax and server-side PNG renderer.
 $stage='smtp_mailer';
 return bh_expo_send_qr_mail($email,$message['subject'],$html,$plain,
  [['cid'=>'expo-family','name'=>'Bubba-Me-Family-QR.png','bytes'=>$png]]);
 } catch (Throwable $e) {
  error_log('Expo family email step failed: stage='.$stage.' class='.get_class($e));
  throw $e;
 }
}
