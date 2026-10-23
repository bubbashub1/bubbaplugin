<?php
declare(strict_types=1);
/**
 * Bubba & Me Expo email template / SMTP adapter.
 * Not called by the public booking endpoint yet.
 * A separate confirmed-booking workflow must handle retries and delivery.
 */
function bh_expo_confirmation_content(array $reservation): array {
    $name=trim((string)($reservation['contact_name']??''));
    $reference=trim((string)($reservation['public_reference']??''));
    $adults=(int)($reservation['adults']??0);
    $children=(int)($reservation['children']??0);
    if($name==='' || !preg_match('/^[A-F0-9]{24}$/',$reference) || $adults<1 || $adults>12 || $children<0 || $adults+$children>12){
        throw new InvalidArgumentException('Invalid Expo reservation for confirmation.');
    }
    $safeName=htmlspecialchars($name,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $safeReference=htmlspecialchars($reference,ENT_QUOTES,'UTF-8');
    $html='<!doctype html><html lang="en"><body style="font-family:Arial,sans-serif;color:#163b30;background:#f7faf6;padding:24px">'
        .'<div style="max-width:600px;margin:auto;background:#fff;border-radius:18px;padding:28px">'
        .'<h1 style="color:#195c48">Bubba &amp; Me Expo</h1>'
        .'<p>Hello '.$safeName.',</p><p>Your free visitor booking is confirmed.</p>'
        .'<p><strong>Saturday 9 January 2027</strong><br>10:30am–2:30pm<br>Acorn Centre, Torquay</p>'
        .'<p><strong>Booking reference:</strong> '.$safeReference.'</p>'
        .'<p><strong>Adult tickets:</strong> '.$adults.'<br><strong>Children attending:</strong> '.$children.'</p>'
        .'<p>Children are counted in your booking and do not need individual QR tickets.</p>'
        .'<p>Your adult QR tickets will be provided separately once ticket delivery is enabled.</p>'
        .'<p>Questions? Email contact@bubbahub.co.uk.</p>'
        .'</div></body></html>';
    $plain="Hello ".$name.",\nYour free Bubba & Me Expo booking is confirmed.\nSaturday 9 January 2027, 10:30am–2:30pm, Acorn Centre, Torquay.\nBooking reference: ".$reference."\nAdult tickets: ".$adults."\nChildren attending: ".$children."\nChildren do not need individual QR tickets.\nAdult QR tickets will be provided separately once delivery is enabled.\nQuestions: contact@bubbahub.co.uk";
    return ['subject'=>'Bubba & Me Expo — booking confirmation '.$reference,'html'=>$html,'plain'=>$plain];
}
/**
 * Call only after reservation is confirmed, with a controlled retry queue.
 * Returns SMTP delivery result, never changes database status itself.
 */
function bh_expo_send_confirmation(array $reservation): bool {
    if(($reservation['booking_status']??'')!=='confirmed')return false;
    $email=trim((string)($reservation['contact_email']??''));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return false;
    $message=bh_expo_confirmation_content($reservation);
    require_once __DIR__.'/mailer.php';
    return bh_send_smtp_mail($email,$message['subject'],$message['html'],$message['plain']);
}
