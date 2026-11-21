# Shared Listing Monitor — rebuild plan

## Purpose

Replace the confusing Listing Health Check experience with one simple, shared monitoring workflow that can eventually cover all six Bubba Hub directories. Reuse and adapt existing Listing Checker infrastructure where it is sound; do not discard useful work or migrate/delete existing listing data.

## User experience

The main screen should answer three questions immediately:

1. What has changed?
2. What needs checking?
3. Who is looking after it?

Use plain language and a small number of views:
- **Overview** — counts for new discoveries, possible changes, possible closures, and items assigned to the team.
- **Needs attention** — one combined queue with simple filters for directory, alert type, status, and assigned person.
- **Directory settings** — advanced configuration kept out of the everyday workflow.

Alerts are dashboard-only. No email alerts are required.

## Shared six-directory foundation

Build a single monitoring and review engine with directory-specific configuration. Each monitored item/finding must identify its directory type and source listing. Directory configuration can define:
- display name and enabled state;
- search phrases, geographic coverage and permitted source types;
- listing identity/matching rules;
- fields that can be monitored and reviewed.

Do not hard-code the engine to activities or assume all six directories have identical fields. Start with the existing activities/listings integration, then enable other directories one at a time after their data models and fields are mapped.

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

## Team assignment

Every alert/review item can be assigned to an existing authorised Bubba Hub admin/team member.

- Provide a simple **Assign to** control using the existing admin/team account directory; do not create a separate set of duplicate staff accounts.
- Show the assignee and assignment status clearly on the alert.
- Support unassigned, assigned, in progress, waiting for information, and completed states.
- Allow reassignment by authorised admins and retain an assignment history (who assigned/reassigned it and when).
- Assignment does not grant extra permissions: only users who already have the relevant admin permission may edit, approve or publish.
- Keep optional notes/activity history with the item so the team can hand work over without losing context.
- Keep the everyday screen uncluttered; due dates and advanced workflow controls can be added only if they remain genuinely simple.

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

1. Audit existing tables, APIs, workers, directory data models and admin/team permissions.
2. Design the shared schema and directory adapters; preserve existing checker data.
3. Build the simple combined dashboard and alert detail pop-up.
4. Build save-to-review-draft and full-listing review/approval flow.
5. Add assignment, reassignment and activity history using existing team accounts.
6. Test the activity directory end-to-end on staging.
7. Map and enable the remaining five directories one at a time.
8. Retire/redirect the old confusing screens only after the replacement is tested and data is verified.

## Acceptance checks

- A finding can be assigned and reassigned to an existing team member.
- A team member can see what is assigned to them.
- Adding suggested changes creates a draft only; the public listing remains unchanged.
- Final approval updates only accepted fields and records an audit trail.
- A suspected closure never closes a listing without explicit confirmation.
- Existing activities, venues, findings and scan history remain intact.
- The same core workflow works for all six directory types without duplicating the monitoring engine.
