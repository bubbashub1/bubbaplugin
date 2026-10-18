<?php
declare(strict_types=1);
/**
 * Private one-time confirmed booking QR issuance + email workflow.
 * Invoke only from a trusted booking confirmation worker after confirmation.
 * A failed/ambiguous send MUST be reconciled manually; never rotate an
 * already issued token automatically (the original may have reached inbox).
 */
function bh_expo_issue_and_send_family_qr(PDO $db,int $reservationId): array {
 if($reservationId<1)throw new InvalidArgumentException('Invalid reservation');
 require_once __DIR__.'/expo-booking-qr-core.php';
 require_once __DIR__.'/expo-family-email-delivery-core.php';
 require_once __DIR__.'/expo-family-ticket-email.php';
 // Claim delivery before generating a bearer credential. Claim helper locks
 // the confirmed reservation and blocks repeated attempts.
 if(!bh_expo_claim_family_email_send($db,$reservationId))
   return ['ok'=>false,'status'=>'not_pending_or_not_confirmed'];
 $stage='load_reservation';
 try{
  $q=$db->prepare("SELECT id,public_reference,event_key,booking_status,contact_name,contact_email,adults,children FROM bh_expo_reservations WHERE id=?");
  $q->execute([$reservationId]);$reservation=$q->fetch(PDO::FETCH_ASSOC);
  if(!$reservation||$reservation['event_key']!=='bubba-me-2027'||$reservation['booking_status']!=='confirmed')
   throw new RuntimeException('Booking not confirmed');
  $stage='create_qr';
  $qr=bh_expo_new_booking_qr();
  $stage='persist_qr';
  $q=$db->prepare('INSERT IGNORE INTO bh_expo_booking_qrs (reservation_id,token_hash) VALUES (?,?)');
  $q->execute([$reservationId,$qr['token_hash']]);
  if($q->rowCount()!==1)throw new RuntimeException('Booking QR already issued');
  // The QR payload lives in memory only; the database stores a hash.
  $stage='render_and_send_email';
  $sent=bh_expo_send_family_qr_email($db,$reservation,$qr['payload']);
  $stage='finalize_delivery';
  $updated=bh_expo_finish_family_email_send($db,$reservationId,$sent);
  if(!$updated)throw new RuntimeException('Email status could not be finalized');
  return ['ok'=>$sent,'status'=>$sent?'sent':'failed_manual_review'];
 }catch(Throwable $e){
  // If SMTP succeeded but state persistence failed, this status is ambiguous.
  // Do not retry automatically or generate another QR.
  try{bh_expo_finish_family_email_send($db,$reservationId,false);}catch(Throwable $ignored){}
  // Log only a bounded stage label and exception class; never exception messages,
  // SQL, recipient email, QR payload or credentials.
  error_log('Expo family QR delivery requires review: stage='.$stage.' class='.get_class($e));
  return ['ok'=>false,'status'=>'manual_review_required'];
 }
}
