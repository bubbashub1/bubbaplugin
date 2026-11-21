# Bubba Hub Events Directory

## Purpose
Build a dedicated Events Directory alongside (not inside or instead of) the existing Activities Directory. Events are standalone records with their own public detail pages and dates. Existing activity listings and recurring class sessions remain unchanged.

## Confirmed product decisions
- Support one-off events and recurring events.
- Support multi-day events as separate dated occurrences under one event page.
- Class leaders can link events to their existing Bubba Hub activity listings.
- One event may link to more than one listing owned by the same organiser.
- Events and activities remain separate records; linking is a relationship, not a duplicate listing.
- Leader-submitted events remain drafts/pending review and are not public until approved.
- Admin approval is required before publication.
- Preserve the cancelled Bubba & Me Expo notice and do not re-open its registrations.

## Data model
- `bh_events`: event details, organiser ownership, venue/location, category, age guidance, ticketing mode, recurrence metadata and moderation status.
- `bh_event_occurrences`: explicit start/end date-times for each occurrence. One-off events have one occurrence; multi-day and recurring events have multiple occurrences.
- `bh_event_activities`: many-to-many relationship between events and the organiser's own activity listings.
- Do not use the Activities table as an events table or overload weekly activity sessions as event dates.
- URL import may assist data entry later, but the leader must review and confirm imported details.

## Leader linking rules
- Show only activities owned by the authenticated leader's linked organiser profile in the event wizard.
- Permit linking one or more of those listings, or leaving an event unlinked.
- Server-side ownership checks are mandatory on create, update and link operations; never trust organiser or activity IDs supplied by the browser.
- A linked event should show a clear “From [listing name]” link to each associated activity.
- Activity pages may show an “Upcoming events” section for approved, current linked events.
- If an activity is archived or unlinked, the event remains intact; do not cascade-delete events.

## Public directory
- Dedicated Events navigation and directory page, separate from Activities.
- Search/filter by date range, town, region, category, age suitability and free/paid.
- List and calendar views; sort upcoming occurrences chronologically.
- Show only approved/published events and scheduled occurrences in public results.
- Hide past occurrences from upcoming results while retaining them on event detail pages where useful.
- Cancelled events remain identifiable as cancelled and must not appear bookable.
- Event detail page includes description, dates, venue, age guidance, price/ticketing and external booking link where provided.

## Admin
- Review queue for new and edited event submissions.
- Approve, reject/request changes, cancel, archive and edit.
- Admin can see organiser, linked activity listings and all occurrence dates.
- Keep an audit trail of moderation decisions and status changes, using established approval/audit conventions where compatible.

## Delivery stages
1. Database foundation and migration.
2. Secure events API (public read; authenticated leader create/edit; admin moderation).
3. Public Events Directory and event detail page.
4. Leader event wizard, including date/recurrence entry and activity-link selector.
5. Admin event approval and management.
6. Activity-page cross-links, family saves/planner and calendar integration.

## Security and implementation requirements
- Reuse existing session/authentication and organiser ownership patterns.
- Enforce role, account status, ownership, CSRF and input validation server-side.
- Use parameterised SQL, escaped output, safe image upload handling and rate limits.
- Store dates consistently and display in UK local time; test daylight-saving transitions.
- No production migration without explicit approval. Apply and test on staging first.
- Do not modify existing activity rows or change the current Activities API response.

## Staging acceptance criteria
- Existing activity search, listing pages, planner and weekly schedules behave exactly as before.
- One-off event with one occurrence is published only after admin approval.
- Multi-day event displays each date under one detail page.
- Recurring event stores and displays multiple explicit occurrences correctly.
- Leader can link their own listing(s) to an event.
- Leader cannot link another organiser's listing, even by tampering with request IDs.
- Event can be submitted without a linked activity.
- Public directory excludes drafts, pending events and cancelled occurrences from upcoming/bookable results.
- Cancelled events remain visible with a clear cancelled label.
- Admin can review and approve/reject submissions.
- Mobile and desktop directory, filters, date entry and detail pages pass.
- No regression to the existing Activities Directory.
