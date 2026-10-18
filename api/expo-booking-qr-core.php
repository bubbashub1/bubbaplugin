<?php
declare(strict_types=1);
/**
 * Booking-level QR foundation for Bubba & Me Expo.
 * One token for one reservation/email, independent of the number of attendees.
 * Does not replace the legacy adult-ticket endpoints until migrated and tested.
 */
function bh_expo_booking_qr_payload(string $token): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('Invalid booking token');
    return 'BH-EXPO-2027:'.$token;
}
/** Generate a secure token only once at reservation creation; store only its SHA-256 hash. */
function bh_expo_new_booking_qr(): array {
    $token=bin2hex(random_bytes(32));
    return ['payload'=>bh_expo_booking_qr_payload($token),'token_hash'=>hash('sha256',$token)];
}
/** Find a confirmed booking by QR without exposing stored emails or token hashes. */
function bh_expo_resolve_booking_qr(PDO $db,string $payload): ?array {
    if (!preg_match('/^BH-EXPO-2027:([a-f0-9]{64})$/D',$payload,$m)) return null;
    $stmt=$db->prepare("SELECT r.id,r.contact_email,r.adults,r.children,r.public_reference,r.booking_status
      FROM bh_expo_booking_qrs q
      INNER JOIN bh_expo_reservations r ON r.id=q.reservation_id
      WHERE q.token_hash=? AND r.event_key='bubba-me-2027' AND r.booking_status='confirmed' LIMIT 1");
    $stmt->execute([hash('sha256',$m[1])]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}
