# Expo QR rendering rollout (staging only)

This PR adds a private QR PNG renderer, not a public image URL or booking activation.

## Prerequisites
- Install Composer dependencies on staging (composer.json adds endroid/qr-code ^5.0); deploy a matching lockfile and vendor directory through the approved deployment process.
- Confirm PHP GD extension is installed.
- Do not log QR payloads, use third-party QR generators, or expose ticket tokens in GET URLs.
- Verify QR images decode to exactly the BH-EXPO-2027: token format and that the authenticated scanner accepts only confirmed adult tickets.

## Remaining work
- Integrate inline PNG attachments with the existing PHPMailer adapter, ideally via a dedicated mailer function and non-guessable CID, after reservation confirmation.
- Add reliable email delivery queue, retry/idempotency, and email_status transitions.
- Add authenticated admin test reservation/ticket fixture tools, cleanup, and end-to-end tests.
- Confirm that all composer dependencies are compatible with the staging PHP version.
- Keep visitor bookings disabled until all gates pass.
