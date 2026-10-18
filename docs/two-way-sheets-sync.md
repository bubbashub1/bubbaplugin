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
1. Supply the master spreadsheet ID and exact worksheet/tab, plus header mapping. Published CSV is read-only and cannot support bidirectional writes.
2. Configure a Google service account with Sheets read/write access. Store credentials in staging environment/secrets, never in the repository or browser.
3. Review and apply `db/migrations/20261018_sheet_sync.sql` to the **staging database only**.
4. Confirm listing field ownership and conflict policy for leader-managed fields (schedules, venues, bookings, and organiser details).
5. Implement and test the Google Sheets API read/write adapter, database change detection, row-level transactions, idempotent retries, and a dry-run.
6. Only then wire an authenticated server-side daily scheduler and an admin-only Sync Now button.

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
