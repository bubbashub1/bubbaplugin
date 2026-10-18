# Expo exhibitor schema compatibility gate (staging first)

## Why this gate exists
Two existing migrations define **incompatible** tables with the same names. Do not rerun either blindly or merge both into production.

- `database/migrations/2026-10-18-expo-foundation-staging.sql` creates `bh_expo_exhibitors` with `event_key`, `contact_email`, `application_status`, `public_visible`, `preferred_table`, `submitted_at`. The Events admin approval API expects **this** layout.
- `database/expo-exhibitors-staging.sql` creates `bh_expo_exhibitors` with `email`, `status`, `table_number`, `details_json`, `created_at`. The older staging application submission endpoint expects **this other** layout.
- `CREATE TABLE IF NOT EXISTS` will not reconcile columns. Existing records must not be discarded.

## Staging-only read-only inspection
Run in phpMyAdmin against **SCSTAGING-353034334ce5**:

```sql
SHOW CREATE TABLE bh_expo_exhibitors;
SHOW CREATE TABLE bh_expo_tables;
SELECT COUNT(*) AS existing_exhibitors FROM bh_expo_exhibitors;
SHOW COLUMNS FROM bh_expo_exhibitors;
SHOW COLUMNS FROM bh_expo_tables;
```

No data is modified by these commands. Preserve the results before selecting an additive migration.

## Required reconciliation
1. Choose one canonical schema. Prefer the Expo foundation schema for the existing Events admin UI; map older `email` → `contact_email`, `status` → `application_status` (`pending_review` → `pending`), `table_number` → `preferred_table`, `created_at` → `submitted_at`. Move `details_json` values into appropriate columns without losing original data.
2. The public application endpoint and admin review endpoint must use the **same** schema and event key.
3. Never auto-approve applications from a keyword check. A human review must control approval and public visibility. Table preference is not a reservation.
4. Additive, reversible migration only; back up data, test on staging, check duplicate table allocation and pending application retention.
5. Confirm admin authentication, CSRF, same-origin validation, no public personal-data leakage, and one complete application → review → approval → public exhibitor listing flow.
6. Only then prepare a production-specific migration and isolated release PR against `main`. Do not deploy staging-only migration scripts to live.

## Current release status
**BLOCKED pending actual staging schema inspection.** PR #225 is not ready for merge. PR #226's public submission endpoint is not yet enabled. Production `main` remains unchanged.
