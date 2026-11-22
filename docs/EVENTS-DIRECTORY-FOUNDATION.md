# Events directory foundation

## Purpose
Create a genuine one-off and recurring Events directory without repurposing the existing Listings tables or the legacy Bubba & Me Expo event-details record.

## Data model
- `bh_directory_events`: event identity, organiser ownership, descriptive content, location snapshot, pricing/booking details and moderation state.
- `bh_directory_event_occurrences`: one row per scheduled date/time. Recurring events are represented by multiple occurrence rows, not by repeating the event listing.
- `bh_directory_event_listings`: optional many-to-many links to existing `bh_activities`. Linking an event to a class does not copy or duplicate the activity.
- `bh_directory_event_reviews`: event-specific review history.

## Publication rules
- New leader submissions start as `pending`; only admins can publish.
- Public queries must return only `published` events with future, scheduled occurrences.
- A cancellation changes the occurrence status to `cancelled`; it must not silently delete the event or its history.
- Organiser ownership must be resolved from the authenticated session and the organiser record on the server. Never accept an organiser ID as proof of ownership from the browser.
- Updates to published events should return to review when material fields change.
- Rejected/returned submissions remain private to their owner and admins.

## Location and time
- The schema stores a location snapshot so event venues can be displayed even though the legacy `bh_venues` table is activity-linked and is not yet a safe shared destination table.
- Store event date-times in the `Europe/London` timezone. Test GMT/BST transitions and events spanning midnight before release.
- Latitude/longitude are optional; do not geocode or publish an unverified location automatically.

## Delivery status (staging-site)
- [x] Schema proposal created; not installed.
- [x] Leader create/draft/submit endpoint created with session authentication, CSRF, same-origin checks, field validation and server-resolved organiser ownership.
- [x] Linked Listings selector and server-side check that every linked activity belongs to the same organiser.
- [x] Admin review queue and approve/reject/return-for-edits actions with review history.
- [x] Public Events feed limited to published events with future scheduled occurrences.
- [x] Public Events page keeps one-off events separate from regular Listings sessions.
- [x] Leader Hub navigation includes Add an Event.
- [x] Shared Leader My Directory entries list includes Events and the other directory sectors.
- [x] Safe in-place editing/resubmission for owned event drafts and returned events implemented in PR #1093; awaiting staging validation.
- [ ] Cancellation/rescheduling controls and rate limiting.
- [ ] Schema review against the actual staging database, backup and explicit approval before installation.
- [ ] End-to-end tests after schema installation, including cross-Leader ownership attempts, public visibility, cancellation, multiple occurrences, mobile layout and GMT/BST.

## Delivery sequence
1. Review the schema against the staging database and confirm the exact organiser/user linkage.
2. Obtain explicit approval, take a staging backup and install the schema on staging only.
3. Complete the remaining event owner controls: cancellation/rescheduling, then validate the shared My Directory entries list and draft editing on staging.
4. Run end-to-end tests with at least two distinct Leader accounts; verify one Leader cannot read, change or link another Leader's records.
5. Test GMT/BST boundaries, event dates spanning midnight, public filters and mobile presentation.
6. Only after staging sign-off, prepare a separate production migration and deployment plan.

## Safety
This document and `database/events-directory-schema.sql` are a code-level foundation only. No database migration is executed by deployment, and no production schema or data is changed.
