# Bubba Hub Unified Directory Platform

## Product direction

Bubba Hub will grow into one family discovery platform with six primary directory sections:

1. **Listings** — existing activities, classes, groups, clubs, regular sessions and pregnancy activities.
2. **Events** — one-off, recurring and multi-day family events, each with explicit dated occurrences.
3. **Businesses** — family-focused businesses, independent providers, shops and services.
4. **Childcare & Schools** — nurseries, childminders, preschools and schools, with specialist childcare and setting information.
5. **Places & Days Out** — family-friendly destinations such as parks, beaches, farms, attractions, museums and soft play.
6. **School Clubs** — breakfast clubs, after-school clubs, wraparound care and holiday clubs for children aged 3+.

Existing venues remain shared location records used by listings and events; Places & Days Out is a curated public destination directory, not a relabelled venue admin table.

Family support, pregnancy support, feeding support, SEND services and similar providers should initially be discoverable through suitable Listings or Business categories, rather than creating more top-level sections prematurely.

## User experience

- One main Directory landing page with six clear section choices: Listings, Events, Businesses, Childcare & Schools, Places & Days Out, and School Clubs.
- A unified search entry point can search across all six sections.
- Each section retains its own filters, result cards, detail pages and specialist fields.
- A type filter or section tab lets families narrow the search to one directory.
- Shared location filters use the established Devon and Cornwall region/town structure.
- Shared UI patterns: search, filters, map where relevant, list/grid views, saved items, report/update link, loading/empty/error states and responsive layouts.
- Cross-links connect related records without copying or duplicating their data.
- Use consistent visual design, accessibility, moderation and SEO conventions.

## Data and architecture principles

- This is one directory platform, not one giant table and not six disconnected websites.
- Keep each directory type's data model appropriate to its purpose. Do not force childcare, school club, event, business and activity fields into one generic record.
- Preserve the existing activity, organiser, venue, session, planner, booking and account data and APIs. Existing bh_activities remains the source of truth for Listings.
- Events remain distinct from activities and weekly activity sessions. Use event records, explicit event occurrences and a relationship table for links to activities.
- Business and Childcare & Schools records should be designed only after inspecting the live/staging schema and existing organisation ownership model. Avoid duplicate provider identities where a business also operates a nursery or school setting.
- Reuse existing organisers/accounts, venues, region/town data, categories, media handling and shared UI where compatible. Do not create parallel tables or taxonomies without schema review.
- A venue is a shared location/place record, not automatically a public directory listing. The future Places & Days Out section needs its own curated content and visitor-facing fields.
- Prefer shared components and service helpers over copied implementations. Keep section-specific APIs and permissions explicit.
- Every owner-managed submission must be authenticated, status-checked, validated, CSRF-protected and ownership-checked server-side.
- Public pages show only approved/published records. Changes to published records may require renewed review according to moderation rules.
- Use parameterised SQL, escaped output, safe image uploads, audit history and rate limits.
- Use UK date/time display and test daylight-saving changes for dated events.
- Do not modify or migrate production data without explicit approval. Test migrations and behaviour on staging first.

## Ownership and permissions

- A class leader can manage their own Listings and submit Events linked only to Listings owned by their organiser profile.
- Business owners manage their own Business profile(s) subject to admin verification and approval.
- Childcare & Schools managers manage their own setting profile(s) subject to appropriate verification and admin approval.
- Admins can review, approve, request changes, reject, suspend, archive and edit all directory types.
- Families can browse, save and plan around public records; they do not gain edit rights through a save or relationship.
- Do not assume every directory type has the same owner role. Model and test permissions per type.

## Search and discovery

Shared filters where meaningful:
- Search terms
- Region, town and postcode/radius where available
- Category
- Age suitability
- Accessibility and family facilities where applicable

Specialist filters:
- Listings: day, time, age, price, session type, booking required, term-time status and accessibility.
- Events: date range, occurrence, free/paid, age guidance, category and location.
- Businesses: service/product category, family services, location, opening hours and contact/website.
- Childcare & Schools: age range, setting type, opening hours, funded places and relevant childcare/inspection details. Display factual inspection information with source and last-checked date; do not imply endorsement.

Do not expose filters that cannot be supported by reliable structured data.

## Delivery roadmap

### Stage 0 — Platform definition
- Confirm this unified directory architecture.
- Audit current navigation, page routes, activity/venue/organiser tables, shared APIs and existing admin tools.
- Map existing data and components; identify what can be reused.
- Keep the existing Events foundation as the first new specialist directory, but align its public experience with the unified platform.
- No production changes.

### Stage 1 — Shared Directory shell
- Build the central Directory landing/navigation experience for all six sections.
- Add section routes and consistent page shell.
- Preserve the current Listings experience and its search/map/planner integration.
- Show unfinished sections as clearly labelled introductions until their searchable data and workflows are ready; do not imply that placeholder sections are live directories.
- Ensure mobile and desktop behaviour.

### Stage 2 — Events
- Validate and apply the additive Events migration on staging.
- Build secure Events API, public Events view/detail page, leader submission and activity linking, and admin approval.
- Retain the cancelled Bubba & Me Expo notice and registration shutdown.
- Integrate Events into central Directory search/navigation without changing existing Listings API responses.

### Stage 3 — Businesses
- Audit existing organiser/provider records before designing tables.
- Build business profiles, categories, location/contact details, ownership verification, approval and search.

### Stage 4 — Childcare & Schools
- Build specialist profiles for nurseries, childminders, preschools and schools.
- Add setting-specific fields, age ranges, opening hours, availability and childcare information.
- Include clear data sources and last-checked dates for inspection/factual claims.
- Add approval and management workflows.

### Stage 5 — Places & Days Out
- Build curated destination profiles, distinct from venue records used for classes/events.
- Include visitor facilities, accessibility, age suitability, opening/seasonal information and location.
- Add source and last-checked dates for changeable visitor details.

### Stage 6 — School Clubs
- Build profiles for breakfast clubs, after-school clubs, wraparound care and holiday clubs for children aged 3+.
- Include age eligibility, school collection arrangements, operating days/times, holiday dates, costs and contact/booking.
- Capture appropriate registration and safeguarding information for provider verification.

### Stage 7 — Unified discovery
- Combine approved records across all six sections in global search.
- Add cross-links, family saves/planner integration and relevant map/list views.
- Measure performance and avoid loading all directory datasets unnecessarily.

## Acceptance criteria

- Existing Listings, weekly sessions, venue associations, planner, bookings and account workflows continue working.
- The Directory clearly exposes the six primary sections.
- Search can be global or section-specific.
- Records are never duplicated merely to appear in more than one relevant section; use explicit relationships where appropriate.
- Each section has appropriate specialist fields, permissions, filters and approval rules.
- Public users cannot see pending, rejected, suspended or archived records as active results.
- Leaders cannot link events to another organiser's listing, including by tampering with request data.
- The cancelled Bubba & Me Expo remains cancelled and cannot accept registrations.
- No staging or production migration is run without an explicit deployment/migration step and verification.
