# Automatic Google Sheets sync — staging implementation plan

Status: scaffold / NOT ENABLED. No live writes.

## Requirements
- Private Google Sheets API integration with service account; credentials only in server environment, never GitHub.
- Stable activity IDs and canonical field mapping from api/admin-import-export.php.
- Persist last successfully synced field values (base snapshot) and revision token per activity.
- Three-way comparison for each field: base vs Google Sheet vs current database.
  - Sheet changed only: candidate inbound update.
  - DB changed only: candidate outbound update.
  - Both changed identically: accept.
  - Both changed differently: conflict, neither side overwritten.
- Never delete missing rows or change listing ownership automatically.
- Protect organiser, venue, schedules and bookings as linked records; avoid slug-only matching.
- Dry-run preview and audit trail before enabling scheduled writes.
- Export API changes to Google Sheets with Sheets API batchUpdate and revision checks.
- Scheduled runner via server cron, locked to prevent overlapping runs, default disabled.
- Admin dashboard: last run, proposed changes, conflicts, retry, pause, audit history.
- Staging test cases: leader edit vs sheet edit, missing fields, duplicate IDs, newly created activities, stale revisions, API failures and retry idempotency.
- Explicit admin approval required before live automatic writes.

## Phases
1. Read-only staging connection and schema introspection.
2. Database migrations for sync state, base snapshots, audit records, conflict queue.
3. Dry-run diff API and admin preview.
4. Guarded inbound/outbound writes and rollback tests.
5. Scheduled staging runs and acceptance testing.
6. Separate live rollout with backup and disabled-by-default switch.

## Setup
Use a dedicated Google Cloud service account with access only to the selected spreadsheet.
Store spreadsheet ID and service-account credentials in server-side configuration.
Published CSV alone is read-only and cannot perform outbound writes.
