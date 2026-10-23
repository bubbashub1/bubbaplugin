<?php
declare(strict_types=1);
/**
 * Internal email delivery state machine. Never exposed as a public endpoint.
 * Prevents simultaneous workers sending the same family QR.
 * SMTP success followed by a database failure remains ambiguous: retry must
 * be handled manually or with an idempotent provider, not automatically.
 */
function bh_expo_claim_family_email_send(PDO $db,int $reservationId): bool {
 if($reservationId<1)throw new InvalidArgumentException('Invalid reservation');
 $db->beginTransaction();
 try{
  $q=$db->prepare("SELECT booking_status,event_key FROM bh_expo_reservations WHERE id=? FOR UPDATE");
  $q->execute([$reservationId]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['event_key']!=='bubba-me-2027'||$r['booking_status']!=='confirmed'){$db->rollBack();return false;}
  $db->prepare('INSERT IGNORE INTO bh_expo_family_email_delivery (reservation_id) VALUES (?)')->execute([$reservationId]);
  $q=$db->prepare('SELECT status,lease_until FROM bh_expo_family_email_delivery WHERE reservation_id=? FOR UPDATE');
  $q->execute([$reservationId]);$state=$q->fetch(PDO::FETCH_ASSOC);
  // Failed/expired attempts need explicit review to avoid duplicate email delivery.
  if($state['status']!=='pending'){$db->rollBack();return false;}
  $q=$db->prepare("UPDATE bh_expo_family_email_delivery SET status='sending',attempt_count=attempt_count+1,last_attempt_at=UTC_TIMESTAMP(),lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE reservation_id=?");
  $q->execute([$reservationId]);$db->commit();return true;
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function bh_expo_finish_family_email_send(PDO $db,int $reservationId,bool $sent): bool {
 if($reservationId<1)throw new InvalidArgumentException('Invalid reservation');
 $q=$db->prepare("UPDATE bh_expo_family_email_delivery SET status=?,lease_until=NULL,sent_at=CASE WHEN ?=1 THEN UTC_TIMESTAMP() ELSE sent_at END WHERE reservation_id=? AND status='sending'");
 $q->execute([$sent?'sent':'failed',$sent?1:0,$reservationId]);
 return $q->rowCount()===1;
}
