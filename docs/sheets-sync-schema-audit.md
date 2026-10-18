# Sheets sync schema audit and write-readiness checklist

This PR adds a **read-only staging schema inspection endpoint**. It does not enable Google Sheets database writes.

## How to run (after staging deployment)
1. Confirm the existing staging sync bearer token is configured server-side.
2. Send POST to `https://staging.bubbahub.co.uk/api/sheets-sync-schema-audit.php` with `Authorization: Bearer <staging-token>`. No request body is needed.
3. Review the JSON report of existing table columns and required fields. Do **not** paste the token into GitHub or a public log.
4. Determine insert requirements including slug uniqueness, organiser FK, and relationships to venues/sessions. Review exact MASTER SHEET headers separately.
5. Build transactional, idempotent inserts and updates with optimistic hashes; do not infer that table presence alone means write readiness.

## Safety
- Strict POST + staging host + bearer auth.
- Read-only INFORMATION_SCHEMA queries.
- No database changes, no live deployment, no automatic triggers.
- The report explicitly says `ready_for_automatic_writes: false`.
- The report lists column names/types and required fields but not actual records or credentials.

## One-record acceptance test (later PR)
- Sheet ID unused: create website listing with exact ID, including all required relations, and return committed ID.
- Blank sheet ID: generate database ID and write it back to the sheet.
- Website-only ID: append one matching row to the sheet, repeat with no duplicate.
- Both sides edited: produce conflict, change neither side.
- Re-run same request: idempotent, no duplicate.
- Invalid fields, missing organiser, or duplicate ID: reject with no partial writes.
