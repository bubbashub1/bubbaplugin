# Phase 2 — five-record staging import transaction and rollback contract

**Status:** DESIGN ONLY. No SQL migration, writer endpoint, import execution, or production change is authorised.

## Verified observations (18 October 2026)
- MASTER SHEET sample IDs 2–6: five source-validation candidates, zero ID/slug collisions against the ten exported staging activities.
- Staging matching preview scanned six organisers and six venues; **zero verified matches**. IDs 2, 3, 4, 5, 6 have no approved organiser/venue link.
- ID 2: organiser Cornwall Council, venue Cornwall Library and Information Service — neither name matched staging.
- ID 3 Birthwise: organiser/venue missing.
- ID 4 Fran Minifie Photography: organiser "Bubba Hub organiser (unconfirmed)", venue PPT Church Hall (no match).
- IDs 5 Phonics with Robot Reg East Devon and 6 Torbay PEEP: organiser/venue missing.
- All five source session fields empty; never invent sessions.
- Actual staging schema: bh_activities requires title and slug; bh_organisers requires organisation_name; bh_venues requires name; bh_sessions requires venue_id. No foreign keys reported. bh_activity_venues(activity_id,venue_id,is_primary) exists.
- Missing from audited staging schema: bh_activity_schedules, bh_activity_images, bh_tags, bh_activity_tags, bh_activity_age_ranges. Use only verified columns; don't auto-create these tables.
- bh_activities includes tags, weekly_schedule, image_path and age fields; don't assume related-table storage.
- Existing BH_SHEETS_WRITE_ENABLED must remain 0. MASTER SHEET and live are off limits.

## Record-by-record decisions required
| Sheet ID | Proposed activity action | Organiser action | Venue action | Sessions |
| --- | --- | --- | --- | --- |
| 2 | Draft only after approval | Manual review; create only with verified identity | Manual review; verify address/postcode | None |
| 3 | Hold pending decision on incomplete relations | Unknown; do not fabricate | Unknown; do not fabricate | None |
| 4 | Hold unconfirmed organiser | Explicit manual approval required | Manual review; verify address/postcode | None |
| 5 | Hold pending decision on incomplete relations | Unknown; do not fabricate | Unknown; do not fabricate | None |
| 6 | Hold pending decision on incomplete relations | Unknown; do not fabricate | Unknown; do not fabricate | None |

## Preconditions before any code capable of writing
1. Confirm schema defaults, unique indexes, status values and actual listing creation paths from staging; validate slug algorithm against site.
2. Decide whether organiser_id may be NULL for drafts and whether a venue-less draft is valid. If not, **block**; do not use placeholder organisers or venues.
3. Confirm the activity's ownership/leader_id/created_by rules; never assign another leader's ownership.
4. Require source fingerprints and a per-record explicit allowlist, bounded to IDs 2–6 and a maximum five records. Exclude 900001–900009 and 999999.
5. Verify no existing ID or slug under a DB transaction; use unique constraints and locks as applicable to avoid race conditions.
6. Treat missing session, organiser, venue and image data as review items, not permission to invent them.
7. Require operator review of full proposed values and source provenance; reject unexpected status, invalid URLs, overly long fields, and unsafe IDs.
8. Ensure endpoint is authenticated, POST-only, host-locked to staging, CSRF/replay-resistant where appropriate, with explicit disabled-by-default write gates.
9. Back up staging DB and document restore point before test. No automatic import triggers.

## Transaction design (not executable SQL)
- For each approved record, begin a database transaction; recheck source hash, existing ID and slug, and any organiser/venue identity claims.
- Create only approved organiser/venue records, if any, and validate ownership. Insert activity as **draft** using verified column mapping. Insert approved activity_venues link(s) and sessions only if fully sourced.
- Write bh_sheet_sync_records trace in the same transaction; confirm its exact schema, unique keys and ID mapping first.
- On *any* error, rollback the **entire** record transaction. Return safe structured errors without leaking SQL or credentials.
- Commit only after all required invariants pass; log record ID and trace ID. A retry must be idempotent and must not duplicate related entities.
- Do not attempt a multi-record batch until the one-record path is proven and audited.

## Rollback and recovery
- A transaction rollback protects failures **before commit**. After commit, recovery needs a verified staging backup plus an explicitly approved compensating action.
- Record IDs, generated organiser/venue IDs, activity_venues links, session IDs, prior row snapshots, and sync trace. Never delete a reused organiser or venue.
- A cleanup operation must check ownership, dependencies, and later edits; if uncertain, restore from backup or escalate for manual review.
- Never issue unbounded DELETE, REPLACE, TRUNCATE, DROP or automatic production rollback.
- Test failures, retry, collision, stale source hash and partial multi-table failure in an isolated staging-only test before enabling five-record writes.

## Next implementation milestone
Build a **read-only staging preflight endpoint** that returns draft eligibility, missing mandatory columns/relations, slug/ID collisions, and a proposed per-table action list. It must always report `writesPerformed:false`. Run it for IDs 2–6, then review and decide whether one **explicitly approved** staging draft insert is safe.
