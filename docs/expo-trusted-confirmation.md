# Trusted Expo free-booking confirmation (staging)
- Call bh_expo_confirm_and_deliver_free_reservation($db, $reservationId) **only from a trusted server-side worker or authenticated admin action** after validating the reservation and its capacity allocation.
- This PR intentionally does not add a public HTTP confirmation endpoint or enable /api/expo-reserve.php.
- Free bookings must be pending, have ticket_type=free, and payment_status=not_required. Cancelled bookings cannot be reconfirmed.
- Confirmation is idempotent. Email delivery is separately claimed by the existing state machine; repeated calls cannot generate a new QR after an attempt.
- SMTP errors leave the booking confirmed and require manual review of email delivery.
- Before production: apply all Expo SQL migrations on staging, connect the trusted caller, reconcile legacy adult QR tickets, verify single-email booking policy, test SMTP, partial family arrivals and giveaway bag claims.
