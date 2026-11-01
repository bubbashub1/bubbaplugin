# Staging Gemini integration test plan
Status: pending execution; no live deployment authorised.

## Setup
- Confirm bh-staging-secrets.php exists in the shared parent directory, not in staging_html/public_html.
- Confirm API can read it under PHP hosting user; never display or log key.
- Deploy the feature to staging only after confirming staging-site deployment process and backup.

## Tests
1. Unauthenticated GET /api/admin-ai-listing-suggest.php returns 401; no CSRF token exposed.
2. Authenticated GET returns ai_configured=true without returning the secret.
3. Wrong CSRF token POST returns 403; empty input returns 422; oversized input returns 413.
4. POST with sample public class text returns review-only fields; no booking schedules.
5. AI suggestions cannot overwrite existing form fields without user confirmation.
6. Unsafe URL schemes and invalid email/phone suggestions are discarded.
7. Cooldown and request budget return 429 on excess calls.
8. Non-staging host returns 403 before admin session handling.
9. Missing private file returns ai_configured=false and POST returns 503.
10. Confirm public_html and production workflow remain unchanged.
11. Check browser network tab: API key never appears in HTML, JavaScript or JSON responses.
12. Confirm PHP syntax and staging error logs are clean.

## Release blockers
- Current three-request limit is session-scoped, not persistent per-user entitlement. Do not offer Free/Pro billing until server-side quotas and ownership verification exist.
- Confirm Gemini model availability and provider error responses under the selected Google account.
- Review retention, consent and privacy notice before storing any prompts or analytics.
