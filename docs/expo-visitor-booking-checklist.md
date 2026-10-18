# Bubba & Me Expo — Visitor booking launch checklist (staging)

## Current
- Database readiness API returns database_ready=true (user verified).
- Free reservation endpoint remains **draft PR #158**, not merged.
- Booking and exhibitor forms must remain disabled until the checks below pass.
- Do not activate BUBBAHUB_EXPO_BOOKINGS_ENABLED yet.

## Development gates
- [ ] Add server-side Cloudflare Turnstile verification (secret remains in server config; never JS or GitHub).
- [ ] Add request rate limiting per client, plus server-side duplicate/idempotency protection.
- [ ] Confirm capacity policy: no limit until venue/organiser approves a safe capacity; do not promise availability.
- [ ] Add user-facing form and privacy/consent text; validate all inputs server-side.
- [ ] Send via existing SMTP only after durable reservation and secure email flow is implemented.
- [ ] Generate unique QR ticket images and email one ticket per attendee.
- [ ] Admin-only ticket lookup, check-in and replay protection; log scans.
- [ ] Cancellation and resending confirmation flow.
- [ ] Test failure handling, email retries and partial booking recovery.
- [ ] End-to-end staging test: book, receive, scan, cancel and retry.
- [ ] Only then explicitly approve opening staging booking form; separate live approval.

## What organiser needs to do
1. Confirm the total number of visitors the venue will safely permit (or that visitor bookings should be uncapped for now). Ask venue before applying a hard cap.
2. Confirm whether free tickets are required for babies/children, or whether children should simply be counted in the booking.
3. Confirm the sender address: proposed contact@bubbahub.co.uk or existing noreply@bubbahub.co.uk.
4. Check Cloudflare Turnstile is configured on **staging**; do not paste secret keys into chat.
5. When a test booking link is available, test using an email address you control.

## Privacy/security
Do not expose attendee names, emails, tokens or QR values via public APIs. Avoid collecting children's names. QR token is an access credential; do not put raw token in public URLs or logs. Keep any test personal data in staging only.
