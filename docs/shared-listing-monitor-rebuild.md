# Shared Listing Monitor — rebuild plan

## Purpose

Replace the confusing Listing Health Check experience with one simple monitoring workflow, initially for the activities directory and the existing single administrator. Keep the design extensible, but defer mapping and enabling the other five directories until the activity monitor is working well. Reuse and adapt existing Listing Checker infrastructure where it is sound; do not discard useful work or migrate/delete existing listing data.

## User experience

The main screen should answer three questions immediately:

1. What has changed?
2. What needs checking?
3. What do I need to do next?

Use plain language and a small number of views:
- **Overview** — counts for new discoveries, possible changes, possible closures, and items needing your review.
- **Needs attention** — one clear queue with simple filters for alert type and status.
- **Settings** — essential monitoring choices only; technical worker controls stay out of the everyday workflow.

Alerts are dashboard-only. No email alerts are required.

## First release scope

Build the first working version for the activities directory only. Keep the implementation modular enough that other directories can be added later, but do not spend time mapping their tables, fields, search phrases or matching rules now. That future mapping is deliberately deferred until the activity monitor is proven useful.

## Alert workflow

Use the same workflow for all directory types:

**Find → Suggest → Add to review → Review full listing → Approve.**

- A change alert opens a compact comparison pop-up showing the current value, suggested value and evidence/source.
- **Add suggested changes** copies the suggestion into a saved review draft; it must not change the public listing.
- **Edit changes** lets the administrator adjust the proposed values.
- **Review listing** shows the complete listing with proposed changes clearly highlighted.
- The administrator can accept/reject individual fields, save for later, or approve the final update.
- Only the final explicit approval updates the live listing.
- Record who approved it, when, and exactly which fields changed.
- A suspected closure is a separate alert and must never close/unpublish a listing automatically. Require explicit confirmation in the review flow.
- New discoveries remain candidates until the administrator reviews them and chooses to create/add a listing.

## Access and future team support

- Version one is restricted to the existing Bubba Hub admin login. There is currently one administrator using this workflow.
- Do not build staff assignment, a team directory, reassignment, or team-specific permissions in this phase.
- Keep review records and workflow code structured so assignment can be added later without redesigning the monitor.
- Reuse the existing admin session and security checks; do not create duplicate accounts or a new login system.

## Existing repository work to adopt

Inspect and selectively reuse:
- `admin/admin-listing-health.html` — current completeness/duplicate interface; replace its user experience rather than layering more controls onto it.
- `admin/admin-listing-review.html` and `api/admin-listing-review.php` — existing findings/review queue and safe review-status update patterns.
- `admin/admin-listing-checker-settings.html` — retain useful configuration concepts, but move technical worker controls out of the everyday screen.
- `database/listing-checker-schema.sql` — existing settings, discovery terms, run history, queue and findings tables.
- Existing discovery/check cron workers and source-evidence data — audit before reuse; improve matching and change detection rather than duplicating functioning infrastructure.

The existing findings table is activity-oriented today. Extend the model in a backward-compatible way (or add monitor tables) so the first release works safely with activities. Preserve all existing rows and legacy scan history.

## Closure and website monitoring rules

A website problem can alert the administrator to a possible closure, but it must never close or unpublish a listing automatically.

Classify website/source outcomes separately:

- **Possible closure — dead link:** the saved website URL is broken, returns a persistent not-found/gone response (such as HTTP 404/410), the domain no longer resolves, or the domain appears to have expired. Save the exact URL, technical result, check time and evidence. A dead link is a reason to investigate, not proof that the business has closed.
- **Closure indicated — explicit announcement:** an official website or public business-controlled source clearly says the business has permanently closed, has ceased trading, or announces a permanent closure. Save the relevant wording/snippet, source URL and check time. This is stronger evidence, but still requires the administrator to confirm.
- **Temporary closure:** wording such as closed today, closed for holidays, seasonal break, temporarily closed, or a temporary pause must not be treated as a permanent closure. Record it as a temporary status/change where useful.
- **Unable to verify:** timeouts, connection failures, TLS/certificate problems, bot protection, access denied/rate limiting, server errors (5xx), and other transient access problems are not closure evidence. Keep these distinct from possible closure.
- **Repeated dead-link evidence:** repeat checks can increase an alert's priority, but retain each check result and timestamp so the administrator can see the pattern. Do not silently convert repeated technical failures into a confirmed closure.
- **Other official evidence:** where practical, check another public business-controlled source (for example an official social page) before raising confidence. Do not infer closure from unrelated third-party comments or search snippets alone.
- **Human decision required:** every closure alert opens the evidence for review. Only an explicit administrator action may change a listing's status; no scan, rule, timeout or source response may unpublish or delete a listing.

Website fetching must also be hardened: allow only public HTTP(S) destinations, validate every redirect, block localhost/private/reserved IP ranges and unsafe schemes, verify TLS, and enforce strict connection, response-size and redirect limits.

## Safety and reliability

- No automatic live edits, closures, deletions or new public listings.
- Keep source URL, check time, evidence/snippet, technical result and confidence with each finding.
- Distinguish **possible closure**, **closure indicated**, **temporary closure** and **unable to verify**.
- Deduplicate repeat alerts for the same underlying change while retaining the check history.
- Make scans resumable and rate-limited; keep the schedule configurable.
- Use UK time for any schedule display.
- Keep the monitoring dashboard useful on desktop and mobile.

## Delivery sequence

1. Audit the existing activity tables, APIs, workers, review data and admin authentication.
2. Design the activity-first schema and service boundaries; preserve existing checker data.
3. Build the simple combined dashboard and alert detail pop-up.
4. Implement the dashboard, private change drafts, full-listing preview, explicit field approval and audit log. These foundations are in the feature branch and await staging validation.
5. Add the read-only website-check worker and private check-history tables. The first worker version is now in the feature branch; it checks saved activity website URLs and creates private findings only. It does not edit listing data.
6. Connect website-check findings to the dashboard, add controlled new-listing discovery review, and improve page-content change detection.
7. Test the activity directory end-to-end on staging, including both additive migrations, worker security, closure classification, approval conflict handling and dashboard visibility.
8. Retire/redirect the old confusing screens only after the replacement is tested and data is verified.
9. Only when requested later, map the other five directories and add team assignment.

## Acceptance checks

- Only an authenticated administrator can access the monitor and review actions.
- Adding suggested changes creates a draft only; the public listing remains unchanged.
- Final approval updates only the saved proposed fields, only after explicit confirmation, and records the administrator, before/after values, source and approval time.
- Approval is blocked if any affected live field has changed since the draft snapshot was created.
- A dead website link raises a possible-closure alert but never closes a listing.
- Explicit permanent-closure wording is shown as strong evidence but still requires administrator confirmation.
- Temporary closure language is not misclassified as permanent closure.
- Timeouts, blocks, TLS problems and 5xx responses are classified as unable to verify.
- Repeated dead-link checks retain their individual evidence and timestamps.
- Existing activities, venues, findings and scan history remain intact.
- The activity monitor is modular enough to support later directory adapters without requiring a rebuild.


## Website monitoring implementation notes (2026-11-22)

The first website-check worker is `api/listing-monitor-website-cron.php` and is CLI-only. Its additive schema is `database/migrations/20261122_create_listing_monitor_website_checks.sql`.

- It reads `bh_activities.website` and writes only to the private Listing Monitor state/history tables and existing findings table.
- It uses bounded batches, connection/overall timeouts, a 1 MB response cap, a small redirect limit, HTTPS certificate verification, public-address checks and pinned IPv4 resolution for each requested host.
- HTTP 404/410 create **possible closure** findings; explicit permanent-closure wording creates **closure indicated** findings; temporary-closure wording is kept separate. DNS lookup failures remain **unable to verify** in this first pass because a DNS failure alone cannot reliably distinguish an expired domain from a temporary resolver problem.
- Every result is recorded in website history. Findings are refreshed rather than duplicated for the same activity/outcome.
- A healthy response resolves outstanding unreviewed website alerts for that activity; previously reviewed decisions are left alone. The consecutive dead-check counter resets after any non-dead result.
- Website decision rules now live in a small side-effect-free helper (`api/listing-monitor-website-rules.php`) so they can be tested offline without contacting real websites or touching the database. The automated test script covers normal responses, 404/410, permanent and temporary closure wording, access/rate-limit/server errors, public/private IP ranges and absolute/protocol-relative/root-relative/path-relative/query-only redirects. Unexpected redirect statuses are not treated as healthy.
- GitHub Actions runs the offline rules test alongside PHP lint and the existing security guards. Latest tested code commit `7ba10d4992a61667144e5a10c5b03a530a5b3cd2` passed the Listing Checker PHP/security workflow; staging and database integration tests remain outstanding.
- The worker currently uses the existing `health_enabled` / `health_paused` settings as its master on/off switch and uses the existing batch-size setting. It is not yet scheduled. Website findings now open in the dashboard review pop-up with the captured evidence and an explicit note that no listing change is proposed.
- Do not enable or schedule it on production. First deploy to staging, apply both Listing Monitor migrations there, inspect the worker and run controlled test URLs. In particular, verify redirect handling, DNS behavior, and that the monitor never changes `bh_activities`.
- This first pass checks availability and explicit closure wording only. It does not yet compare page content over time to detect ordinary text/price/session changes. That remains a later monitoring enhancement.


## Database integration tests (2026-11-22)

A disposable MySQL integration suite now runs in GitHub Actions using its own temporary MySQL 8.0 service and a database named `listing_monitor_test`. It does not connect to staging or production.

- Applies the existing Listing Checker schema and both additive Listing Monitor migrations against MySQL.
- Checks website state/history inserts and findings insert/refresh/recovery against the actual table definitions and enum values.
- Confirms a healthy recovery resolves outstanding findings but preserves an administrator-approved decision.
- Exercises private draft storage, explicit field approval, audit insertion and stale-snapshot rejection.
- Confirms website-check persistence leaves the public activity fields untouched.
- The tests exposed a MySQL update-order issue: when reopening a resolved finding, `reviewed_at` must be cleared before `review_status` changes from `resolved` to `new`. The worker query has been corrected and the integration test checks this exact behavior.
- Confirmed the real admin session identity key is `bh_admin_username`; approval audit records use that value.
- Latest tested code/workflow commit `dd00c0b906116b3c7183645e176742cf526688b0` passed PHP lint, security guards, offline website-rule tests and disposable MySQL integration tests in GitHub Actions run `45021371000`.

Database integration is still not the same as a full staging/browser test. The PR remains a draft; no migration has been applied to staging or live, and the website worker remains disabled and unscheduled.
