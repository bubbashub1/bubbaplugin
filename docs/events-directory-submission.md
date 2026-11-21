# Bubba Hub Events — Unified Directory Integration

## Purpose
Events are one specialist section within the wider Bubba Hub Directory platform. The four planned primary sections are Listings, Events, Businesses and Nurseries. Events have their own records, dates, detail pages, moderation and search filters, while sharing the Directory's navigation, location infrastructure and visual patterns.

The central architecture is documented in docs/directory-platform-architecture.md.

## Confirmed event requirements
- Support one-off events and recurring events.
- Support multi-day events as separate dated occurrences under one event page.
- Class leaders can link events to their existing Bubba Hub Listings.
- One event may link to more than one Listing owned by the same organiser.
- Events and Listings remain separate records; linking is a relationship, not a duplicate listing.
- Leader-submitted events remain drafts/pending review and are not public until approved.
- Admin approval is required before publication.
- Preserve the cancelled Bubba & Me Expo notice and do not re-open its registrations.

## Data model
- bh_events: event details, organiser ownership, venue/location, category, age guidance, ticketing mode, recurrence metadata and moderation status.
- bh_event_occurrences: explicit start/end date-times for each occurrence. One-off events have one occurrence; multi-day and recurring events have multiple occurrences.
- bh_event_activities: many-to-many relationship between events and the organiser's own activity listings.
- Do not use the Activities table as an events table or overload weekly activity sessions as event dates.
- Reuse existing venue/location infrastructure where compatible; do not create a duplicate venue directory.
- URL import may assist data entry later, but the leader must review and confirm imported details.

## Leader linking rules
- Show only Listings owned by the authenticated leader's linked organiser profile in the event wizard.
- Permit linking one or more of those Listings, or leaving an event unlinked.
- Server-side ownership checks are mandatory on create, update and link operations; never trust organiser or activity IDs supplied by the browser.
- A linked event should show a clear “From [listing name]” link to each associated Listing.
- Activity pages may show an “Upcoming events” section for approved, current linked events.
- If an activity is archived or unlinked, the event remains intact; do not cascade-delete events.

## Directory experience
- Events is a dedicated section within the central Bubba Hub Directory, not a separate disconnected directory platform.
- The main Directory will eventually offer Listings, Events, Businesses and Nurseries, with a global search and section-specific search.
- The Events section supports search/filter by date range, town, region, category, age suitability and free/paid.
- Provide list and calendar views; sort upcoming occurrences chronologically.
- Show only approved/published events and scheduled occurrences in public results.
- Hide past occurrences from upcoming results while retaining them on event detail pages where useful.
- Cancelled events remain identifiable as cancelled and must not appear bookable.
- Event detail page includes description, dates, venue, age guidance, price/ticketing and external booking link where provided.
- Reuse shared Directory navigation, location, accessibility and responsive UI patterns.

## Admin
- Review queue for new and edited event submissions.
- Approve, reject/request changes, cancel, archive and edit.
- Admin can see organiser, linked Listings and all occurrence dates.
- Keep an audit trail of moderation decisions and status changes, using established approval/audit conventions where compatible.

## Delivery stages
1. Database foundation and migration.
2. Secure Events API (public read; authenticated leader create/edit; admin moderation).
3. Events section and event detail page within the shared Directory shell.
4. Leader event wizard, including date/recurrence entry and Listing-link selector.
5. Admin event approval and management.
6. Listing-page cross-links, family saves/planner and calendar integration.
7. Integrate approved Events into unified Directory search without changing existing Listings API response contracts.

## Security and implementation requirements
- Reuse existing session/authentication and organiser ownership patterns.
- Enforce role, account status, ownership, CSRF and input validation server-side.
- Use parameterised SQL, escaped output, safe image upload handling and rate limits.
- Store dates consistently and display in UK local time; test daylight-saving transitions.
- No production migration without explicit approval. Apply and test on staging first.
- Do not modify existing activity rows or change the current Activities API response.
- Do not add Business or Nursery schemas as part of the Events implementation; those require separate schema audits and designs.

## Staging acceptance criteria
- Existing Listings search, listing pages, planner and weekly schedules behave exactly as before.
- One-off event with one occurrence is published only after admin approval.
- Multi-day event displays each date under one detail page.
- Recurring event stores and displays multiple explicit occurrences correctly.
- Leader can link their own Listing(s) to an event.
- Leader cannot link another organiser's Listing, even by tampering with request IDs.
- Event can be submitted without a linked Listing.
- Public Events section excludes drafts, pending events and cancelled occurrences from upcoming/bookable results.
- Cancelled events remain visible with a clear cancelled label.
- Admin can review and approve/reject submissions.
- Mobile and desktop Directory, filters, date entry and detail pages pass.
- No regression to the existing Listings Directory.
