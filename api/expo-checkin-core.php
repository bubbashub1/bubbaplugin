<?php
declare(strict_types=1);
/**
 * Bubba & Me Expo: reusable, transaction-safe ticket check-in operation.
 * Not exposed publicly. Admin authentication and CSRF verification MUST be
 * implemented by the caller before this function can be invoked.
 */
function bh_expo_check_in_adult_ticket(PDO $db,string $token): array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)){
        return ['ok'=>false,'error'=>'invalid_ticket'];
    }
    $db->beginTransaction();
    try {
        $q=$db->prepare("SELECT t.id,t.checked_in_at,r.booking_status,r.event_key
            FROM bh_expo_tickets t
            INNER JOIN bh_expo_reservations r ON r.id=t.reservation_id
            WHERE t.ticket_token=? AND t.attendee_type='adult'
            FOR UPDATE");
        $q->execute([$token]);
        $ticket=$q->fetch(PDO::FETCH_ASSOC);
        if(!$ticket){
            // Booking-level family QR: token is stored only as a SHA-256 hash.
            $q=$db->prepare("SELECT r.id FROM bh_expo_booking_qrs q
                INNER JOIN bh_expo_reservations r ON r.id=q.reservation_id
                WHERE q.token_hash=? AND r.event_key='bubba-me-2027'
                AND r.booking_status='confirmed' LIMIT 1 FOR UPDATE");
            $q->execute([hash('sha256',$token)]);
            $booking=$q->fetch(PDO::FETCH_ASSOC);
            if(!$booking){$db->rollBack();return ['ok'=>false,'error'=>'ticket_not_valid'];}
            $q=$db->prepare('INSERT IGNORE INTO bh_expo_family_qr_checkins (reservation_id,checked_in_at) VALUES (?,UTC_TIMESTAMP())');
            $q->execute([(int)$booking['id']]);
            if($q->rowCount()!==1){$db->rollBack();return ['ok'=>false,'error'=>'already_checked_in'];}
            $db->commit();
            return ['ok'=>true,'status'=>'family_checked_in'];
        }
        if($ticket['event_key']!=='bubba-me-2027' || $ticket['booking_status']!=='confirmed'){
            $db->rollBack();
            return ['ok'=>false,'error'=>'ticket_not_valid'];
        }
        if($ticket['checked_in_at']!==null){
            $db->rollBack();
            return ['ok'=>false,'error'=>'already_checked_in'];
        }
        $update=$db->prepare('UPDATE bh_expo_tickets SET checked_in_at=UTC_TIMESTAMP() WHERE id=? AND checked_in_at IS NULL');
        $update->execute([(int)$ticket['id']]);
        if($update->rowCount()!==1){
            $db->rollBack();
            return ['ok'=>false,'error'=>'already_checked_in'];
        }
        $db->commit();
        return ['ok'=>true,'status'=>'checked_in'];
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
