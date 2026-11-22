# Leader Hub shared venue foundation

Date: 2026-11-22

## Scope

This change makes the existing Leader Hub recognise the runtime `bh_activity_venues` relationship table alongside the legacy `bh_venues.activity_id` field. It is an application-layer compatibility step only.

## Behaviour

- A leader's class response includes venues linked through either the legacy owner field or `bh_activity_venues`.
- A venue row is returned once per class even if both relationship mechanisms refer to it.
- Existing venue rows are reused when a leader selects an existing venue for a new class. The API adds an activity-to-venue relationship rather than copying the venue details into a second venue row.
- Newly created venues continue to populate the legacy `activity_id` field for compatibility and also receive a row in `bh_activity_venues`.
- Venue details are editable by a leader only when the legacy owner (if present) and all activities linked through `bh_activity_venues` belong to that leader's organiser. Cross-organisation shared venues are shown as managed by Bubba Hub and cannot be edited by an individual leader.
- Sessions remain exposed only through their legacy owning activity. The current `bh_sessions` schema contains `venue_id` but no activity or schedule attribution, so this change deliberately does not infer that sessions belong to every activity linked to a shared venue.

## Safety

- No database schema changes, backfills, bulk updates, or existing-record migrations are included.
- Existing venue and session records are not rewritten.
- The new relationship is created only when a leader creates a class and chooses an existing venue, or creates a new venue.
- The API retains a legacy-only read fallback if the shared-venue table is unavailable.
- Cross-organisation venue detail edits are rejected server-side even if a client bypasses the disabled UI control.

## Follow-up required

A future timetable phase must give sessions explicit activity/schedule attribution before sessions can be shared or migrated. Admin workflows should also be designed for resolving venue details shared across different organisations.
