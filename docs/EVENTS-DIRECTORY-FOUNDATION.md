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

## Delivery sequence
1. Review and approve this schema against the live staging database structure.
2. Run the migration on staging only, after a backup and explicit approval.
3. Add authenticated leader create/edit/submit endpoints with CSRF, ownership checks, validation and rate limiting.
4. Add an admin review queue and approve/reject/return actions with audit history.
5. Connect the public Events page to published event occurrences, keeping the existing regular Listings sessions clearly separate.
6. Test leader ownership, public visibility, cancellation, recurring dates, filters, mobile layout and GMT/BST.

## Safety
This document and `database/events-directory-schema.sql` are a code-level foundation only. No database migration is executed by deployment, and no production schema or data is changed.
