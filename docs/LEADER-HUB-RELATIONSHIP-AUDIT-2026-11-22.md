# Leader Hub relationship audit — staging source review

**Branch:** `feature/leader-hub-schema-audit`  
**Base:** `staging-site`  
**Purpose:** document the code-level relationships and provide a safe read-only way to confirm the actual staging database schema before designing a migration.

## Why this audit comes before the shared schema

The repository contains more than one generation of venue/schedule logic. Some public APIs contain compatibility checks for alternate relationship tables, while the Leader Hub write path still uses the legacy per-activity venue structure. The checked-in `database/schema.sql` is not sufficient evidence of the live staging schema because the application also contains runtime compatibility paths and historical migrations.

Do not create shared venue or schedule tables until the staging diagnostic has confirmed the exact installed columns and foreign keys.

## Verified code paths

| Workflow | Current source behaviour | Consequence |
|---|---|---|
| Leader Hub overview payload | `api/leader-portal.php` selects organiser-owned rows from `bh_activities`, then loads `bh_venues` with `WHERE activity_id=?`, then loads `bh_sessions` with `WHERE venue_id=?` | The Leader Hub currently treats venues as belonging to one activity and sessions as belonging to one venue |
| Add class — schedule | `create_listing` validates weekly schedule rows and stores them as JSON in `bh_activities.weekly_schedule` | This path does not create corresponding `bh_sessions` rows |
| Add class — existing venue | `create_listing` looks up `existing_venue_id`; if it is not already attached to the new activity, it inserts a new `bh_venues` row with copied address/location fields | Selecting an existing venue duplicates it instead of creating a reusable link |
| Add class — new venue | `create_listing` inserts a new `bh_venues` row with the new activity ID | New venues are tied to that activity |
| Edit class | `save_class` updates basic `bh_activities` fields; it does not update the JSON timetable or venue relationship | The Edit Class workflow is not a complete counterpart to Add Class |
| Edit venue | `save_venue` updates a venue by joining `bh_venues.activity_id` to an activity owned by the resolved organiser | Ownership is tied to the legacy activity relationship |
| Leader schedule | Leader portal payload builds schedule data from `bh_sessions` under each venue | Weekly JSON written by Add Class is not the source read by this schedule view |
| Bookings | Leader portal joins reservations → booking slots → activities → venues; ownership is checked through `bh_activities.organiser_id` | Dated booking slots are separate from the weekly JSON schedule; keep this distinction |
| Public venues API | `api/venues.php` has compatibility branches for `bh_venues.activity_id`, `bh_activity_venues`, `bh_activity_schedules`, and `bh_sessions` | The code recognises several historical schema shapes; it does not prove which one is installed or that Leader writes use it |
| Public venue detail | `api/venue.php` currently joins activities through `bh_venues.activity_id` | Public venue display still follows the legacy relationship |
| Admin venue management | `api/admin-location-manage.php` creates venues with a required activity ID; same-activity merge only; cross-activity merge explicitly rejected | Admin venue tools are not yet a shared cross-sector venue manager |
| Events | `api/leader-events.php` stores venue name/address/town text on the event and creates `bh_directory_event_listings` links to activities; it does not write the event's existing `venue_id` field | Event venue and event/listing connections are incomplete |
| Other directory sectors | `api/leader-directory-records.php` stores address/town as fields on each sector record | These records do not currently use one shared venue record through this Leader API |
| Businesses | `bh_directory_business_listings` links business storefronts to activities | This is an existing cross-record relationship, but not yet a universal typed relationship system |

## Checked-in schema observations

The core `database/schema.sql` defines:
- `bh_venues.activity_id` as a required activity reference.
- `bh_sessions.venue_id`, day of week, start/end time, duration, price and date range. The checked-in definition does not include an `activity_id` or `schedule_id` column.
- `bh_booking_slots.activity_id` and optional `venue_id`, plus dated `starts_at` and `ends_at`.
- `bh_booking_reservations` references a booking slot.

The Events schema defines:
- `bh_directory_events.venue_id` as nullable, alongside copied venue text fields.
- `bh_directory_event_occurrences` for dated occurrences.
- `bh_directory_event_listings` with an `activity_id` link.
- `bh_directory_business_listings` with an `activity_id` link.

The specialist sector records retain their own descriptive and address fields. The new shared model must preserve these specialist fields and use shared relationships for common location/schedule details.

## Read-only staging diagnostic added

New endpoint: `api/admin-leader-hub-schema-diagnostic.php`

- Requires the existing admin session.
- GET only.
- Reports existence, engine, columns, indexes and foreign-key references for the relevant Leader Hub tables.
- Reads no record contents, emails, user data, venue addresses or other personal data.
- Performs no DDL or data writes.
- Includes relationship hints so the migration plan can be based on the actual staging schema rather than assumptions.

After this PR is deployed to staging, an administrator can open:

`https://staging.bubbahub.co.uk/api/admin-leader-hub-schema-diagnostic.php`

while signed in to the staging admin account and share the returned JSON. The diagnostic output is schema metadata only.

## Decisions still required before Phase 1

1. Confirm actual staging schema for `bh_activity_venues`, `bh_activity_schedules`, `bh_venues`, `bh_sessions`, booking tables and all six sector tables.
2. Confirm whether any alternate join tables contain current records and how they are populated.
3. Decide canonical timetable model: recurring patterns, dated occurrences, and bookable slots must have distinct responsibilities.
4. Decide how the six sector record types are represented in shared venue/schedule relationship tables without polymorphic integrity gaps.
5. Define compatibility reads/writes and a conservative backfill report. Do not merge duplicate venues automatically.
6. Confirm public venue and event APIs read the same shared relationships that the Leader Hub writes.

## Safety

- This PR only adds a read-only schema diagnostic and this audit document.
- No application workflow is changed.
- No database schema or records are changed.
- No claims, submissions or approval work is included.
- Staging only. Production changes require separate explicit approval.
