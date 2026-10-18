<?php
declare(strict_types=1);
/**
 * Trusted, private Expo free-reservation confirmation.
 * Caller must independently authorize this action; never expose directly as
 * an unauthenticated HTTP endpoint.
 */
function bh_expo_confirm_free_reservation(PDO $db,int $reservationId): array {
 if($reservationId<1)throw new InvalidArgumentException('Invalid reservation');
 $db->beginTransaction();
 try {
  $q=$db->prepare('SELECT id,event_key,booking_status,ticket_type,payment_status FROM bh_expo_reservations WHERE id=? FOR UPDATE');
  $q->execute([$reservationId]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['event_key']!=='bubba-me-2027'){$db->rollBack();return ['ok'=>false,'status'=>'not_found'];}
  if($r['booking_status']==='confirmed'){$db->commit();return ['ok'=>true,'status'=>'already_confirmed'];}
  if($r['booking_status']!=='pending'||$r['ticket_type']!=='free'||$r['payment_status']!=='not_required'){
   $db->rollBack();return ['ok'=>false,'status'=>'not_eligible'];
  }
  $q=$db->prepare("UPDATE bh_expo_reservations SET booking_status='confirmed' WHERE id=? AND booking_status='pending'");
  $q->execute([$reservationId]);
  if($q->rowCount()!==1){$db->rollBack();return ['ok'=>false,'status'=>'conflict'];}
  $db->commit();
  return ['ok'=>true,'status'=>'confirmed'];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/**
 * Explicit handoff, callable only by trusted confirmation worker.
 * Booking confirmation remains valid if SMTP fails; email status is separate.
 */
function bh_expo_confirm_and_deliver_free_reservation(PDO $db,int $reservationId): array {
 require_once __DIR__.'/expo-confirmed-family-delivery.php';
 $confirmation=bh_expo_confirm_free_reservation($db,$reservationId);
 if(!$confirmation['ok'])return $confirmation;
 $delivery=bh_expo_issue_and_send_family_qr($db,$reservationId);
 return ['ok'=>true,'booking_status'=>'confirmed','email_status'=>$delivery['status']];
}
