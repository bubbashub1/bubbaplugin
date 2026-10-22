# Admin and leader authentication stability audit

Scope: `api/auth.php`, `admin-auth.php`, admin user directory, leader registration. **Do not run destructive tests on production accounts.**

## Verified by code inspection (2026-10-15)
- Email verification GET renders a confirmation form; POST consumes token. This protects against automatic email link scanners.
- Already-verified users receive a successful response when an old verification token is opened.
- Resend is rate-limited by IP, email and user, and responds generically for unknown accounts.
- Resend currently inserts the verification token before attempting SMTP delivery. A failed send can leave an unused token record; do not assume that a token's presence means an email was delivered.
- Admin user directory is read-only and uses a distinct admin session cookie.
- The rate limiter returns HTTP 429 with Retry-After but the user-facing message is not a precise countdown.

## Staging test matrix (must pass before live auth changes)
1. Fresh leader registers -> receives correct staging URL -> GET displays confirmation -> POST verifies -> leader can sign in.
2. Automated link preview/GET does not mark token used.
3. Opening the same link twice shows an already-verified success state.
4. Expired token: user can request a new email; old token never verifies an unverified account.
5. Invalid and unknown email resend requests reveal no account existence.
6. SMTP outage: explicit delivery error, no claim of successful email delivery, no secret tokens in logs.
7. Multiple resends: newest delivered token works; document expected validity of older links.
8. Too many attempts: HTTP 429 with Retry-After; user has understandable recovery guidance.
9. Phone email app -> browser confirmation -> login redirect works without an auth loop.
10. Admin session remains separate from family and leader sessions; no role escalation.

## Release gates
- Check PHP lint and existing automated tests.
- Validate real `bh_users`, `bh_email_verifications`, `bh_rate_limits` schemas on staging.
- Record backup and rollback route.
- Keep login lockouts in place; do not disable verification to work around SMTP problems.
- Verify staging first, then merge narrowly scoped code changes and confirm production deployment.

Related: #55 and #52.
