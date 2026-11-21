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
4. Build save-to-review-draft and full-listing review/approval flow. The first draft-save and full-listing preview foundation is now in the feature branch; final approval/publishing remains to be implemented.
5. Add safe website checking and evidence-based change/closure classification.
6. Test the activity directory end-to-end on staging, including the additive draft-table migration.
7. Retire/redirect the old confusing screens only after the replacement is tested and data is verified.
8. Only when requested later, map the other five directories and add team assignment.

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
