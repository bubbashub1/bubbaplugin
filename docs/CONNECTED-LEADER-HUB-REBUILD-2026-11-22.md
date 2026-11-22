# Connected Leader Hub rebuild — foundation and delivery plan

**Working branch:** `feature/connected-leader-hub-foundation`  
**Base:** `staging-site`  
**Date:** 22 November 2026  
**Scope:** Leader experience and shared data architecture across all six family-directory sectors.  
**Release rule:** staging only. No live deployment, live database changes, destructive migration, or automatic publication is authorised by this plan.

## Product promise

**Less admin. More time for classes.**

A leader should manage their organisation from one simple hub. They enter information once, reuse it wherever relevant, and Bubba Hub keeps connected pages in sync. Leaders should never need to understand the database or repeat the same details for each directory section.

This is a rebuild of the management experience and its underlying connections, not a visual reskin of disconnected pages.

## Six directory sectors

1. Activities & Classes — existing `bh_activities` system.
2. Businesses — `bh_directory_businesses` storefronts.
3. Childcare & Schools — `bh_directory_childcare`.
4. Places & Days Out — `bh_directory_places`.
5. School Clubs — `bh_directory_school_clubs`.
6. Events — `bh_directory_events` and `bh_directory_event_occurrences`.

The six sectors remain distinct public experiences with their own specialist fields. They must share common organisation, venue, timetable, occurrence, media and relationship services where appropriate. A record can be shown in more than one sector through explicit relationships; do not duplicate the underlying record.

## Leader experience principles

- One Leader Hub navigation and one consistent page shell across every leader page.
- One organisation profile and contact source reused across all owned entries.
- A clear "My Hub" overview showing the leader's six sectors, shared resources and a short list of actions needing attention.
- "Add" actions start a short guided flow and ask only for information not already known.
- "Edit" actions update the canonical record; linked displays refresh from that record.
- "Use an existing…" is the default for venues, schedules, events and organisation details; create a new shared record only when needed.
- Show where a shared resource is used before changing or archiving it.
- Archive/remove a relationship without deleting a shared resource still used elsewhere.
- Mobile-first, accessible forms with autosave/draft recovery, clear save feedback and no unnecessary multi-page navigation.
- Leaders only see and manage records belonging to their resolved organiser identity.
- No claim, submission, moderation or approval feature is in scope until the connected foundations are complete and tested.

## Initial code audit — verified on staging source

| Area | Current implementation observed | Rebuild requirement |
|---|---|---|
| Leader navigation | `leader.html` contains separate Add links and separate My Classes, Schedule, Venues, Bookings and Directory entries destinations | Replace with one consistent hub navigation organised around the six sectors and shared resources |
| Classes | `api/leader-portal.php` loads organiser-owned `bh_activities`; `leader-portal.js` has separate class editing | Keep `bh_activities` as canonical Listings data; make Add/Edit use the same reusable shared-resource pickers |
| Venues | `bh_venues` has `activity_id`; Leader API loads venues per activity. Choosing an existing venue in class creation inserts a new venue row for the new activity | Introduce a reusable venue identity and many-to-many links to activities and other directory records; preserve old rows and map them safely |
| Weekly schedule | Class creation writes `bh_activities.weekly_schedule` JSON. Leader portal reads `bh_sessions` by `venue_id` | Establish one canonical timetable service; migrate/bridge weekly schedule JSON and existing sessions without losing data |
| Booking slots | `bh_booking_slots` stores dated `starts_at`/`ends_at`, `activity_id` and `venue_id`; reservations reference slots | Keep dated bookable occurrences separate from reusable weekly timetable patterns; link each slot to its class, venue and schedule occurrence |
| Events | `bh_directory_events`, `bh_directory_event_occurrences` and `bh_directory_event_listings` exist. Event form saves occurrences and activity links. Event schema includes `venue_id`, but current Leader event insert writes venue details as text and does not populate that shared venue link | Connect events to shared venues and occurrences; allow one event to appear in Events and on related listings through relationship records |
| Businesses | Storefronts live in `bh_directory_businesses`; `bh_directory_business_listings` links storefronts to activities | Retain one storefront and use explicit links to relevant activities and other sector entries |
| Childcare / Places / Clubs | Separate specialist tables with free-text address/town fields | Keep specialist attributes, add optional shared venue and schedule links where appropriate; retain remote/mobile/no-fixed-venue options |
| FAQs | `bh_leader_faqs` belongs to organiser and optionally an activity | Allow an FAQ to be shared across selected owned listings instead of forcing duplicate FAQs |
| Profile | Organiser profile is used by the legacy portal and public organiser page | Make organiser contact, branding and public profile a single reusable source for all six sectors |
| Bookings | Leader API joins reservations to booking slots, activities and venues | Ensure the same canonical class, venue and dated occurrence is visible across schedule, class and booking views |

## Target shared model

This is the target logical model. Exact physical schema and migration details must be validated against the current staging database before any DDL is written.

### Organisation and ownership
- Keep `bh_organisers` as the canonical leader organisation identity, connected to the authenticated `bh_users` account.
- All six sector records and shared resources have an organiser owner or are linked through an ownership-safe relationship.
- Resolve organiser identity on the server; ignore client-supplied owner IDs.

### Venues
- Create a canonical shared venue record independent of any one class.
- Create a relationship table linking a venue to one or more owned records (Activities, Businesses, Childcare, Places, Clubs and Events as applicable).
- Store relationship-specific details only on the relationship when genuinely different (e.g. room, entrance notes, event-specific setup); store the physical address once on the venue.
- Existing `bh_venues` rows must be preserved. Migration must identify likely duplicates, propose candidate matches, and require safe deterministic mapping; no automatic fuzzy merges or deletions.
- A venue used by multiple records remains when one relationship is removed.

### Timetables and occurrences
- Store recurring weekly timetable patterns separately from dated occurrences.
- Link a timetable pattern to the relevant owned listing and shared venue.
- Store actual dated occurrences/bookable slots separately, preserving existing `bh_sessions` and `bh_booking_slots` meanings until schema inspection confirms exact semantics.
- A single occurrence can be surfaced in a class page, leader calendar and event/related listing without creating duplicate occurrence rows.
- Updating a recurring pattern affects future generated occurrences only under an explicit, tested rule; it must not silently rewrite past bookings.

### Cross-sector relationships
- Use explicit relationship tables/typed links rather than copying data between sector tables.
- A business can be linked to its services/classes; an event can be linked to one or more listings; a shared venue and timetable can be linked wherever relevant.
- Public sector APIs resolve relationships at read time and display the relevant connected content without turning linked records into duplicate directory entries.
- Specialist sector attributes remain in their own tables.

## Rebuild phases and acceptance criteria

### Phase 0 — baseline and design (this branch)
- Record the six-sector source of truth, current broken connections, target model and safety rules.
- Inspect every Leader page, API, table definition and all reads/writes for venues, schedules, occurrences and bookings.
- Produce a migration mapping report before changing data.

**Exit:** agreed field/table relationship map; confirmed staging DDL; no unresolved ambiguity about session vs booking-slot semantics.

### Phase 1 — shared foundation
- Add additive, idempotent staging schema for shared venue identities/links and canonical timetable patterns/links.
- Add server-side services for ownership, venue lookup, schedule lookup and relationship management.
- Add compatibility reads/writes so existing screens continue to work during transition.
- Backfill links conservatively; report duplicates and unresolved records; never delete legacy data.

**Exit:** old and new data can be read consistently; regression tests pass; existing public listings and bookings unchanged.

### Phase 2 — unified Leader Hub shell
- One consistent responsive shell, navigation, organisation context and overview.
- Six sector entry points plus shared Venues, Timetable, Events and Bookings.
- Consistent "Add", "Edit", "Link existing" and "View where used" patterns.

**Exit:** every Leader page uses the same shell and correct organiser context on desktop and mobile.

### Phase 3 — connected workflows
- Add/edit a venue once and link it to multiple owned records.
- Add/edit a weekly timetable once and show it wherever linked.
- Create an event once, add multiple occurrences, and link it to relevant directory entries.
- Update shared organiser details once and reflect them across owned listings.
- Connect booking slots to the correct dated occurrence without duplicating schedule data.

**Exit:** end-to-end test proves each action updates all linked displays and does not create duplicate records.

### Phase 4 — six-sector consistency
- Align Add/Edit flows for all six sectors.
- Reuse common contact, location, venue, schedule, image and event controls.
- Keep sector-specific fields simple and conditional.
- Add relationship-aware archive and deletion safeguards.

**Exit:** leaders can manage all six sectors from one hub with no repeated common data entry.

### Phase 5 — verification and release readiness
- Test ownership isolation with two leader accounts.
- Test create/edit/link/unlink/reuse flows for each sector.
- Test public pages, organiser pages, calendars and booking flows.
- Test mobile, accessibility, empty/error states, performance and legacy compatibility.
- Obtain explicit approval before production work.

**Exit:** documented staging test evidence and explicit production approval.

## Non-negotiable safety rules

1. Staging only until explicitly authorised otherwise.
2. No destructive schema changes or row deletion during migration.
3. No automatic fuzzy duplicate merges.
4. No existing published listing or booking is silently rewritten.
5. No shared venue is deleted while linked.
6. No past dated booking/occurrence is changed by editing a recurring schedule.
7. No leader can access another organiser's records by changing IDs or request payloads.
8. No submissions, claims or approval workflows are added as part of this rebuild phase.
9. Deployment success is not the same as browser/database verification; report each separately.

## First implementation milestone

Complete Phase 0, then implement the additive shared venue and timetable foundation. Do not start with a visual-only redesign: the shared data model and APIs must be reliable before the new screens are connected to them.
