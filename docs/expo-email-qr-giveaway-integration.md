# Expo: one QR and one giveaway per registered email

**Staging integration only.** No public endpoint or existing check-in behaviour is changed by this PR.

- A confirmed reservation receives **one** cryptographically random QR token, regardless of adult/child headcount.
- The booking reference remains available as a manual lookup fallback.
- Each confirmed booking's email is normalized using trim + lowercase. Duplicate active reservations for the same event/email must be blocked by the reservation service; this helper alone does not enforce that.
- Giveaway claims are independent of attendance. Partial arrival never resets bag collection.
- The unique database key enforces at most one giveaway claim per event/email, including concurrent staff scans.
- Admin-only claim endpoint must authenticate, check CSRF, resolve the QR token server-side, validate the confirmed reservation and use its stored email, then call bhExpoClaimGiveaway. Do not take the email from a client-supplied QR.
- Use the existing QR PNG renderer and email attachment implementation to send one QR per reservation; do not expose tokens in logs.
- Do not use giveaway claim as an attendance check-in.
- On cancellation/rebooking, preserve giveaway claim history and decide eligibility server-side.

## Staging acceptance tests
1. Book 2 adults + 2 children with one email: one QR, four places.
2. Scan and check in one adult; scan again and check in remainder; never exceed booked quantities.
3. Claim bag once; second claim returns false, including concurrent requests.
4. Book with same email case/whitespace variation: duplicate registration blocked.
5. Different emails get independent QR and giveaway eligibility.
6. Cancelled/unconfirmed booking cannot check in or claim a bag.
7. Email shows scannable QR PNG and fallback booking reference.
8. Unauthorized or CSRF-less giveaway request is rejected.

**Deployment:** review and integrate with existing reservation/check-in APIs and SMTP template; apply SQL on staging; run tests before enabling or merging to live.
