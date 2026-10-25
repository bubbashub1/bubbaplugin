# Bubba Hub Sheets sync: staging validation checklist

This pull request introduces a **read-only** activity export and two spreadsheet-bound Apps Script diagnostics. It does **not** implement database writes or install any trigger.

## Deployment order

1. Deploy `api/sheets-sync-export.php` to **staging only** (without merging the PR into production).
2. Confirm `bh-sheets-sync-secret.php` is outside the public webroot on staging and sets the `BH_SHEETS_SYNC_TOKEN` environment variable.
3. Copy `hourly-sync-readonly-monitor.gs` and `hourly-sync-change-preview.gs` into the Apps Script project attached to the Master Sheet.
4. Ensure Script Properties contains `BH_SYNC_TOKEN` for staging. **Never paste the token into GitHub, screenshots or chat.**
5. In Apps Script, manually run `bhHourlySyncReadOnlyCheck`, then `bhPreviewHourlySyncChanges`. Inspect the Execution log.
6. Check `mode: read_only` and `writesPerformed: false`, that all pages are fetched, and that IDs and counts look plausible.
7. Inspect proposed field changes carefully, especially numeric formatting (e.g. `2` vs `2.00`), leader edits, empty fields and known staging test records.
8. Do **not** run `bhInstallHourlySyncMonitor` until the manual checks are accepted. That installer schedules repeated read-only checks, not database updates.

## Failure handling

- HTTP 401: verify the staging token and whether the server forwards Authorization headers.
- HTTP 404: export endpoint is not deployed at the staging URL.
- HTTP 503: staging secret file or token not configured correctly.
- HTTP 500: inspect staging PHP error logs and confirm `bh_activities` fields exist.
- Invalid pagination or duplicate IDs: stop; do not enable triggers.
- If staging and live share the same token, rotate to **separate tokens** before production rollout.

## Release gates for future live updates

- Approved field allowlist and conflict/ownership rules.
- Persisted baseline/version per activity to prevent overwriting class-leader edits.
- Transactional audit + rollback tested on staging.
- Batch caps, idempotency, error notifications and manual pause switch.
- Explicit approval before enabling any live-write endpoint.

**Do not merge this PR as a production release until the staging endpoint has been tested.**
