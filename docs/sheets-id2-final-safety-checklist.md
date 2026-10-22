# PR #221 — ID 2 final staging safety gate

**No writes or automatic import.** The public `api/activities.php` endpoint includes `LOWER(TRIM(COALESCE(a.status,''))) IN ('published','publish')` in its WHERE clause even for `id` and `slug` lookups. The public `activity.html` page also has fallback loaders, so the API checks below **do not prove every public route is safe**.

## Manual test sequence
1. Merge this PR to `staging-site` and wait for successful deployment.
2. Copy `integrations/google-apps-script/id2-final-safety-audit.gs` into the existing Apps Script project. Run `bhId2FinalSafetyAudit`. Expect four successful empty results; if any fails, stop.
3. Open a **logged-out/incognito** browser and test `https://staging.bubbahub.co.uk/activity/bubba-hub-sheet-sync-test-999999` and `https://staging.bubbahub.co.uk/activity.html?slug=bubba-hub-sheet-sync-test-999999`. Draft ID 999999 must **not** display. Also test the public directory search. If any draft is shown, stop and fix the public route before import.
4. Review the full `nonEmptySourceColumns` log for Cornwall Libraries ID 2. Explicitly approve each source column's mapping or deferral. The existing rehearsal only maps ID/title/slug and intentionally omits other fields; that is **not** a full import.
5. In eWebAll phpMyAdmin, select **staging database only** and export a SQL backup (structure + data), store securely, and verify the backup's database name and download. Never run a backup/export against live by mistake. Confirm restore procedure and backup timestamp.
6. Re-run ID 2 collision check immediately before any future insert. Require a unique slug strategy, idempotency and source-hash verification. Obtain separate explicit approval for a one-record staging draft insert.

## Stop conditions
- Any public endpoint displays the existing draft test listing.
- Missing/changed source ID or title; source fields not reviewed.
- No verified staging backup.
- Any collision, duplicate slug or pre-existing sync trace.
- Any unknown ownership, organiser/venue match, or sessions invented.
- Any request to enable live or update MASTER SHEET.

## Current state
`BH_SHEETS_WRITE_ENABLED=0`. No actual ID 2 insert authorised. PR #221 provides a manual read-only public API test and a source-column inventory, **not** an executable writer.
