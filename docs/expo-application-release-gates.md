# Expo exhibitor applications: pre-release checks

**Status: CLOSED by default; not yet tested end-to-end.** The production release branch must not be merged until this checklist is complete.

1. Confirm the **actual** staging exhibitor table columns. The endpoint uses the foundation schema: `contact_email`, `application_status`, `preferred_table`, `event_key`, `public_visible`. The older `email/status/table_number` schema is incompatible. Back up existing rows before any migration.
2. Ensure the deployment exposes environment variables `BH_EXPO_APPLICATIONS_OPEN`, `BH_TURNSTILE_SITE_KEY`, `BH_TURNSTILE_SECRET_KEY`. A Turnstile key stored only in `wp-config.php` does **not** automatically become an environment variable. Do not paste secrets into GitHub or this document.
3. Leave `BH_EXPO_APPLICATIONS_OPEN=false` until staging passes. GET should return HTTP 503 with `applications_not_yet_open`; no POST should write a row.
4. With staging-only environment keys and `BH_EXPO_APPLICATIONS_OPEN=true`, GET should return a CSRF token and the **public** Turnstile site key. Complete challenge; submit one test application. Confirm one new row with `application_status=pending`, `public_visible=0`, `preferred_table` recorded but **no table reserved**.
5. Verify missing/expired Turnstile tokens, missing/wrong CSRF, cross-origin POST, missing consent and immediate repeat submission are rejected. Confirm an existing applicant's private contact information is never exposed by the public exhibitor API.
6. Admin approves and explicitly publishes an exhibitor. Confirm only approved + visible exhibitors appear on the public event page. Verify reject/unpublish removes the public listing.
7. **Notifications are not implemented by this PR.** Confirm the required SMTP confirmation/admin notification design and add it before opening to the public if notifications are a launch requirement.
8. Verify form appearance on mobile and that all referenced stylesheets are present. Verify public routes and hosting environment mapping. Check PHP syntax and database prepared statement compatibility.
9. Perform production backup and **separate** schema migration with rollback plan; only then approve a production merge. Do not merge the full staging branch.

Known limitations: session-based 120-second throttling is not a distributed rate limiter; use Turnstile plus server/WAF rate limiting. Turnstile verification requires outbound HTTPS access to Cloudflare. Successful submissions rotate the CSRF token, so a new page load is needed for another application.
