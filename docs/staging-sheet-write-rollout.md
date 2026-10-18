# Staging two-way Sheets write implementation — gated rollout

## Status
This PR adds a **fail-closed** staging write preflight, a website-to-sheet export adapter, and a single-listing test plan. The API deliberately returns `501 schema_mapping_not_approved` rather than inserting a partial activity. **Do not enable it as a working two-way sync.**

## Required before implementing DB inserts
1. Inspect `SHOW CREATE TABLE bh_activities`, required foreign keys, defaults, and all listing relationships. The existing directory uses linked schedules, venues and organisers; writing only a title is not sufficient.
2. Create an explicit allowlist mapping from actual MASTER SHEET headers to actual database fields; reject unknown values.
3. Apply the existing sync migration on staging only after backup.
4. Store a stable sheet-row UUID and synced hashes; on first encounter compare values and require review if both sides contain different content.
5. Add transactional, idempotent create/update with a unique ID check, explicit-ID inserts and DB-generated IDs for blank sheet IDs. Return the committed ID for sheet writeback.
6. Add a paginated, authenticated website export with a cursor, then the Apps Script append/upsert routine keyed by ID. Never append the same ID twice.
7. Only after staging tests, add per-field conflict detection, safe merge and run logging. No deletion based on missing rows.
8. For the one-listing test, use an unused manually approved staging ID; confirm DB schema, required fields, token, row mapping, rollback, and exact ID. Test site-created record export, repeat run, and conflict.
9. Never expose the bearer token in logs or GitHub. Do not run against live or install a trigger.

## Explicit staging test
- Write API is disabled by default: POST returns 503.
- With `BH_SHEETS_WRITE_ENABLED=1`, `BH_SHEETS_TEST_ACTIVITY_ID=<approved unused ID>` and token configured, only `create_one` with that ID is accepted.
- Existing IDs return 409; unapproved IDs return 403.
- Approved unused ID returns 501 until schema mapping is implemented and reviewed. This is intentional; it does not create a listing.
