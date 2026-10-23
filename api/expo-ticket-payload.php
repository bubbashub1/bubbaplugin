<?php
declare(strict_types=1);
/**
 * Expo adult ticket QR payload builder.
 * Ticket tokens are bearer credentials: never expose through public APIs,
 * logs, analytics or third-party QR generation services.
 */
function bh_expo_ticket_qr_payload(string $token): string {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)){
        throw new InvalidArgumentException('Invalid Expo ticket token');
    }
    return 'BH-EXPO-2027:'.$token;
}
/** Strict parser for a future authenticated on-site scanning endpoint. */
function bh_expo_parse_ticket_qr_payload(string $payload): ?string {
    if(!preg_match('/^BH-EXPO-2027:([a-f0-9]{64})$/D',$payload,$matches)){
        return null;
    }
    return $matches[1];
}
/** Only confirmed adult tickets may be delivered to the reservation owner. */
function bh_expo_confirmed_adult_ticket_tokens(PDO $db,int $reservationId): array {
    if($reservationId<1)throw new InvalidArgumentException('Invalid reservation');
    $stmt=$db->prepare("SELECT t.ticket_token
        FROM bh_expo_tickets t
        INNER JOIN bh_expo_reservations r ON r.id=t.reservation_id
        WHERE t.reservation_id=?
          AND r.event_key='bubba-me-2027'
          AND r.booking_status='confirmed'
          AND t.attendee_type='adult'
        ORDER BY t.id ASC");
    $stmt->execute([$reservationId]);
    $tokens=$stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach($tokens as $token)bh_expo_ticket_qr_payload((string)$token);
    return array_map('bh_expo_ticket_qr_payload',$tokens);
}
