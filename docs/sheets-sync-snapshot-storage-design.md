# Stage 5b — approved snapshot storage design (staging only)

This PR contains **schema validation and tests only**. It creates no database tables, endpoints, files, snapshots, or writes.

## Proposed secure storage

- Future private database table `bh_sheets_sync_baselines` with: environment, activity_id, version, approved_by_admin_id, approved_at_utc, field_values_json, website_fingerprint, sheet_fingerprint, and creation audit ID.
- Restrict writes to a new CSRF-protected, admin-session-only approval endpoint. **Never** grant snapshot approval using the Google Apps Script bearer token.
- Server must read the current activity from the database, present the sheet proposal and website values, and capture the approved common snapshot only after explicit administrator confirmation.
- Do not trust snapshot values, approval IDs, timestamps, or environment provided by the browser or spreadsheet; generate these server-side. The validator is for internal records, not authorization.
- Store immutable, versioned records; preserve history and audit metadata; avoid placing snapshots under a public web root.
- Enforce staging environment, field allowlist, input limits, same activity ID, valid admin identity, and optimistic locking against website changes between preview and approval.
- Treat missing, mismatched, stale or unapproved baselines as `baseline_required` / manual review. No automatic updates.
- Require a separately reviewed database migration and explicit admin UI before enabling persistence. No production changes.

The existing preview endpoint remains read-only and cannot accept client-supplied baselines as authoritative.
