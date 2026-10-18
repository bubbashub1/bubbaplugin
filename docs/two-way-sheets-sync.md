# Two-way Google Sheets sync — staging implementation gate

## Requested policy
- Daily automatic sync, plus an admin-only **Sync Now** control.
- Google Sheets is the primary *editing interface*; the Bubba Hub database remains the public serving source.
- Changes on either side are editable and reconciled by permanent `activity_id` and immutable `sheet_row_id`, never title/slug alone.
- Valid new sheet rows are published after schema validation.
- Concurrent edits on both sides create a review conflict; **neither side is overwritten**.
- Missing rows never trigger automatic deletion.
- Every attempt is logged; failed batches must not be marked successful.
- Staging only until explicit promotion.

## Prerequisites before enabling
1. Master tab confirmed: `MASTER SHEET`. The `Final` tab is a separate consolidation source and must never be modified by the sync. Confirm header mapping and the permanent activity ID column. The spreadsheet URL identifies the file, but a published CSV remains read-only and cannot support bidirectional writes.
2. Configure a Google service account with Sheets read/write access. Store credentials in staging environment/secrets, never in the repository or browser.
3. Review and apply `db/migrations/20261018_sheet_sync.sql` to the **staging database only**.
4. Confirm listing field ownership and conflict policy for leader-managed fields (schedules, venues, bookings, and organiser details).
5. Implement and test the Google Sheets API read/write adapter, database change detection, row-level transactions, idempotent retries, and a dry-run.
6. Only then wire an authenticated server-side daily scheduler and an admin-only Sync Now button.

## Existing Apps Script review (18 October 2026)
- Existing `bhPreviewSync` sends 25-row JSON batches and checks duplicate `id` values; it is read-only.
- **Critical bug:** staging URL check currently accepts `https://bubbahub.co.uk/api/` (live) while its error says staging. Require `https://staging.bubbahub.co.uk/api/` before any tests.
- Existing `bhInstallSixHourTrigger` runs previews every six hours; replace only after tested with a daily trigger, and remove old triggers to avoid duplicate jobs.
- Existing `Sync Log` and `Conflicts` tabs can be reused. Never overwrite them during setup.
- The current preview endpoint is not necessarily deployed; verify API status and token handling before enabling any writes.
- The current script has no website-to-sheet fetch/write or field-level change detection; do not label it two-way yet.

## Current status
**Foundation only — sync is deliberately not enabled.** This branch provides durable ID mappings, run logs, and conflict review schema. No automatic changes or Sheets writes occur until credentials, sheet mapping and dry-run testing are completed.

## Acceptance checks
- Two consecutive runs with no changes produce no new activities or updates.
- Edit sheet only → site updates; edit site only → sheet updates.
- Edit both sides after last sync → conflict record, neither overwritten.
- Add valid row → published once; invalid row → rejected with error.
- Remove row → no deletion.
- API failure, timeouts, partial batch → retry safely and log.
- Admin session required for Sync Now and log access.
- No live database or live Sheets changes during staging testing.
