# Expo confirmation → family QR handoff (staging)
1. Apply database migrations: expo-booking-qr.sql and expo-family-email-delivery.sql, plus family check-in and giveaway migrations.
2. A **trusted, authenticated confirmation workflow** must validate the pending reservation, capacity, and payment status before setting booking_status=confirmed. Do not expose this function publicly.
3. Once confirmed, call bh_expo_issue_and_send_family_qr($db, $reservationId) exactly once. It claims the pending delivery state, creates one random booking QR, stores only its SHA-256 hash, and sends one inline PNG to the registered email.
4. The function does not auto-retry failed/ambiguous sends. A delivered email can be followed by an interrupted status write. Manually reconcile; do not silently issue a second QR.
5. Existing per-adult QR ticket issuance in expo-reserve.php remains active. Do not open public bookings or replace the scanner until the legacy migration is completed.
6. Test: confirmed booking sends one family QR; repeat invocation blocked; family scanner resolves QR; partial check-in bounded; giveaway once per normalized email; missing SMTP config fails closed.
7. No end-to-end deployment or tests have been performed by this PR. The public booking endpoint is still disabled by default.
