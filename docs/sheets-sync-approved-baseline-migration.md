# Staging approved baseline migration — manual DBA checklist

**Do not execute on live.** This PR only adds a SQL file. Deploying the file does not execute SQL.

1. Confirm phpMyAdmin is connected to the **staging** database (not production). Verify the selected database name independently before executing.
2. Back up the staging database. Confirm the backup is restorable.
3. Inspect existing `bh_sheets_sync_baselines` table if present. `CREATE TABLE IF NOT EXISTS` does not validate a pre-existing schema.
4. Review server MySQL/MariaDB version and support for CHECK constraints and JSON_VALID.
5. Run `db/migrations/20261021_staging_approved_sheet_baselines.sql` manually on staging only after approval.
6. Verify table columns and indexes, and confirm it contains **zero rows**.
7. Keep all existing Google Sheets previews read-only. Do not add a baseline approval/write endpoint until admin authentication, CSRF, idempotency, audit and website fingerprint locking have been reviewed.

**Design notes:** baseline history is append-only by application convention, not by database privilege or trigger. Future application code must never UPDATE/DELETE approved snapshots. `approval_audit_id` is reserved for an authenticated approval event; never accept it directly from Google Sheets. Database CHECK constraints may not be enforced on older database engines. No auto-sync or admin approval UI is implemented in this PR.
