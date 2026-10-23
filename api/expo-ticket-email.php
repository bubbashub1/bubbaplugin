<?php
declare(strict_types=1);
/**
 * Private Expo confirmed adult ticket email sender.
 * Caller must authorize recipient and confirmed reservation; never public.
 * Do not call until retry/idempotency and email_status workflow exists.
 */
function bh_expo_send_confirmed_ticket_email(PDO $db,array $reservation): bool {
    if(($reservation['booking_status']??'')!=='confirmed')return false;
    $reservationId=(int)($reservation['id']??0);
    $email=trim((string)($reservation['contact_email']??''));
    if($reservationId<1 || !filter_var($email,FILTER_VALIDATE_EMAIL))return false;
    require_once __DIR__.'/expo-confirmation.php';
    require_once __DIR__.'/expo-ticket-payload.php';
    require_once __DIR__.'/expo-qr-image.php';
    $tickets=bh_expo_confirmed_adult_ticket_tokens($db,$reservationId);
    if(count($tickets)!==(int)($reservation['adults']??0) || !$tickets)return false;
    $message=bh_expo_confirmation_content($reservation);
    $html=$message['html'];
    $html=str_replace(
        'Your adult QR tickets will be provided separately once ticket delivery is enabled.',
        'Your individual adult QR tickets are attached below. Each ticket can be scanned once at the entrance.',
        $html
    );
    $html=str_replace('</div></body></html>','<div style="margin-top:22px"><h2>Adult QR tickets</h2>', $html);
    $attachments=[];
    foreach($tickets as $index=>$payload){
        $cid='expo-adult-'.($index+1);
        $attachments[]=['cid'=>$cid,'name'=>'Bubba-Me-Adult-'.($index+1).'.png','bytes'=>bh_expo_adult_ticket_qr_png($payload)];
        $html.='<p><strong>Adult ticket '.($index+1).'</strong><br><img src="cid:'.$cid.'" alt="Adult '.($index+1).' QR ticket" width="320" height="320"></p>';
    }
    $html.='</div></div></body></html>';
    $plain=str_replace('Adult QR tickets will be provided separately once delivery is enabled.','Your adult QR tickets are included as inline email images. Each is valid for one check-in.',$message['plain']);
    require_once __DIR__.'/expo-ticket-mailer.php';
    return bh_expo_send_qr_mail($email,$message['subject'],$html,$plain,$attachments);
}
