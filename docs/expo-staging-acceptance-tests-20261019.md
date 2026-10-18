# Expo exhibitor staging acceptance tests

**Scope:** staging only. Do not merge release PRs, deploy to live, run SQL migrations or enable public applications until gates pass.

## Gate A: database inspection
1. Open phpMyAdmin connected to the **staging** database; verify the selected database with `SELECT DATABASE()`.
2. Run `database/audits/expo-staging-schema-readonly-20261019.sql` one statement at a time.
3. Capture `SHOW CREATE TABLE` for both Expo tables. The canonical foundation expects `contact_email`, `application_status`, `public_visible`, `assigned_table`, `participation_json`, and `bh_expo_tables.id` / `event_key` / `table_number` / `status` / `exhibitor_id`.
4. If either table is missing or has the older `email` / `status` / `details_json` schema, **stop**. Plan a data-preserving migration; do not run CREATE TABLE IF NOT EXISTS and assume it repairs an existing table.
5. Investigate duplicate approved table assignments and any table-inventory mismatch before proceeding.

## Gate B: safe API verification
1. Confirm admin login works, then load the Events admin and exhibitor approval screens.
2. Confirm an unauthenticated GET to `/api/expo-admin-exhibitors.php` returns 401 and anonymous POST cannot modify records.
3. Confirm the public exhibitors endpoint lists only `approved` + `public_visible=1` records.
4. Confirm applications are **closed by default** until explicitly enabled on staging with correctly configured Turnstile keys.

## Gate C: controlled end-to-end test
1. Enable applications on **staging only** after database and Turnstile checks.
2. Submit one test application. Confirm a pending row is created and **no table is reserved**.
3. Confirm admin sees contact, insurance, participation, requirements and donation interest.
4. Approve but keep `public_visible=0`; confirm no public card.
5. Assign a free table, publish, and verify one public card with correct name/website/logo.
6. Try assigning that table to another approved exhibitor; expect conflict.
7. Reassign the first exhibitor; confirm old inventory row is released and new one is assigned.
8. Cancel or reject the test exhibitor; confirm table is released and public card disappears.
9. Check application validation, expired insurance, CSRF, origin checks, Turnstile failure and request throttling.
10. Repeat concurrent assignment tests; do not assume sequential checks prove concurrency safety.

## Gate D: release
- Confirm page and admin dependencies across PR #226, #227 and #228.
- Confirm email notification approach and application support/rollback plan.
- Keep production applications disabled until explicit launch approval.
- Obtain explicit permission before merging to main (auto-deploy) or applying production SQL.
