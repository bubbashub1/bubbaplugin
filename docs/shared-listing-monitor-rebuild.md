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

## Shared six-directory foundation

Build the first working version for activities only. Keep the implementation modular enough that other directories can be added later, but do not spend time mapping their tables, fields, search phrases or matching rules now. That future mapping is deliberately deferred until the activity monitor is proven useful.

## Alert workflow

Use the same workflow for all directory types:

**Find → Suggest → Add to review → Review full listing → Approve.**

- A change alert opens a compact comparison pop-up showing the current value, suggested value and evidence/source.
- **Add suggested changes** copies the suggestion into a saved review draft; it must not change the public listing.
- **Edit changes** lets an authorised reviewer adjust the proposed values.
- **Review listing** shows the complete listing with proposed changes clearly highlighted.
- The reviewer can accept/reject individual fields, save for later, or approve the final update.
- Only the final explicit approval updates the live listing.
- Record who approved it, when, and exactly which fields changed.
- A suspected closure is a separate alert and must never close/unpublish a listing automatically. Require explicit confirmation in the review flow.
- New discoveries remain candidates until a person reviews them and chooses to create/add a listing.

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

The existing findings table is activity-oriented today. Extend the model in a backward-compatible way (or add shared monitor tables) so findings can reference any directory type and source record. Preserve all existing rows and legacy scan history.

## Safety and reliability

- No automatic live edits, closures, deletions or new public listings.
- Keep source URL, check time, evidence/snippet and confidence with each finding.
- Treat unavailable or blocked sources as **not checked**, not as proof that a listing has closed.
- Deduplicate repeat alerts for the same underlying change.
- Make scans resumable and rate-limited; keep the schedule configurable.
- Use UK time for any schedule display.
- Keep the monitoring dashboard useful on desktop and mobile.

## Delivery sequence

1. Audit the existing activity tables, APIs, workers, review data and admin authentication.
2. Design the shared schema and directory adapters; preserve existing checker data.
3. Build the simple combined dashboard and alert detail pop-up.
4. Build save-to-review-draft and full-listing review/approval flow.
5. Test the activity directory end-to-end on staging.
6. Retire/redirect the old confusing screens only after the replacement is tested and data is verified.
7. Only when requested later, map the other five directories and add team assignment.

## Acceptance checks

- Only an authenticated administrator can access the monitor and review actions.
- Adding suggested changes creates a draft only; the public listing remains unchanged.
- Final approval updates only accepted fields and records an audit trail.
- A suspected closure never closes a listing without explicit confirmation.
- Existing activities, venues, findings and scan history remain intact.
- The activity monitor is modular enough to support later directory adapters without requiring a rebuild.
