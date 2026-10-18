# Bubba Hub Sheets ↔ staging: mapping and rollout contract

## Confirmed read-only reconciliation (2026-10-18)
- MASTER SHEET: 1,205 data rows, 1,205 unique positive numeric IDs, zero duplicate/blank/invalid IDs.
- Staging bh_activities export: 10 IDs (900001–900009, 999999).
- Intersection: zero. Sheet-only: 1,205. Staging-only: 10.
- This compares **IDs only**, not live data, content equality, or related tables.
- No staging or sheet writes occurred.
- Do not interpret the mismatch as missing live production listings.

## Import safety gates
1. Never write to MASTER SHEET or live site during staging development.
2. Keep BH_SHEETS_WRITE_ENABLED=0 until an approved, bounded test.
3. Dry-run every proposed insert/update with counts, record IDs, validation failures and affected related rows.
4. Do not overwrite or delete staging-only listings; test IDs 900001–900009 and 999999 must remain excluded from real sync.
5. Preserve the manually assigned Sheet `id` exactly; reject collisions, negative/noninteger/unsafe IDs, missing titles, and duplicate IDs.
6. Require explicit per-record approval and batch size limits for the first real staging writes.
7. Do not infer unchanged from matching IDs; compare field-level canonical values and persisted sync baselines.
8. Record conflicts without auto-resolving; no last-writer-wins on independently edited values.
9. Use transactions for multi-table writes and log outcome; ensure reruns are idempotent.
10. Do not create scheduled triggers until bidirectional dry-run, test writes and rollback checks pass.

## Spreadsheet columns and target areas

| MASTER SHEET columns | Proposed destination | Verification required |
| --- | --- | --- |
| id,title,description,category,featured,status | bh_activities | Confirm exact column names, status enums, slug rules |
| age_range,age_min_months,age_max_months | bh_activities + age relation if applicable | Validate units, allowed presets |
| price_from,price_per_family,price_per_session,price_free | bh_activities / pricing model | Check actual schema and semantic units |
| booking_url,booking_required,drop_in_welcome,trial_available,term_time_only,holiday_sessions,siblings_welcome | bh_activities + schedule where appropriate | Check per-session overrides |
| what_to_bring,good_to_know | bh_activities | Text length and escaping |
| image_path,image_1,image_2,image_3 | bh_activities + bh_activity_images | Preserve image order and source URLs |
| organisation_name,facebook_url,instagram_url,email,phone,website | bh_organisers | Identity matching and ownership; no accidental merging |
| venue_name,address,venue_address_line_1,venue_address_line_2,city,region,postcode,latitude,longitude,venue_phone,venue_email,venue_website,venue_notes,accessibility | bh_venues + bh_activity_venues | Normalize address; avoid false venue merges |
| tags | bh_tags + bh_activity_tags | Confirm actual tag table and separators |
| sessions | bh_sessions / bh_activity_schedules | Define serialized format, dates, venues, frequency, term-time semantics |
| county | bh_activities or normalized region | Confirm county/region rules |

This is a **proposed mapping**, not a verified schema-to-column implementation. Inspect staging schema and existing writer paths before coding imports.

## Next deliverable
- A read-only schema-aware field audit returning: supported columns, missing columns, relation-table shape, sample source values, normalization risks, and per-row validation errors.
- Follow with a preview endpoint that generates an explicit insert/update/no-op/conflict plan, with no writes.
- Only then introduce narrowly gated staging write batches.

## Explicitly out of scope now
Production sync, live DB changes, edits to MASTER SHEET, automatic triggers, bulk import, deletion, and merging unrelated organisers/venues.
