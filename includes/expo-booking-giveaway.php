<?php
declare(strict_types=1);

/**
 * Expo booking-level QR / giveaway rules.
 * Integration helper: does not alter existing live check-in endpoints.
 * Persist bag claims in bh_expo_giveaway_claims (see migration).
 */
function bhExpoEmailKey(string $email): string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        throw new InvalidArgumentException('Invalid registration email');
    }
    return $email;
}

/** One QR token per reservation, not per attendee. */
function bhExpoBookingQrPayload(string $reservationToken): string {
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $reservationToken)) {
        throw new InvalidArgumentException('Invalid reservation token');
    }
    return 'BH-EXPO-2027:' . $reservationToken;
}

/**
 * Claim exactly one giveaway per event + normalized email.
 * Must be called only by an authenticated, CSRF-validated admin action
 * after resolving a confirmed reservation. Never trust event/email from QR text.
 * Returns true only for the first claim; duplicate scans return false.
 */
function bhExpoClaimGiveaway(PDO $db, int $eventId, string $email, int $adminId): bool {
    if ($eventId <= 0 || $adminId <= 0) {
        throw new InvalidArgumentException('Invalid event or admin');
    }
    $key = bhExpoEmailKey($email);
    $stmt = $db->prepare('INSERT IGNORE INTO bh_expo_giveaway_claims (event_id,email_key,claimed_by_admin_id,claimed_at) VALUES (:event_id,:email_key,:admin_id,UTC_TIMESTAMP())');
    $stmt->execute([':event_id'=>$eventId, ':email_key'=>$key, ':admin_id'=>$adminId]);
    return $stmt->rowCount() === 1;
}
