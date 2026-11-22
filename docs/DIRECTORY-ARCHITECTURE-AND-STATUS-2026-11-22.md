# Directory architecture and implementation review — 22 November 2026

**Status:** staging review / source-of-truth clarification  
**Applies to:** Listings, Businesses, Childcare & Schools, Places & Days Out, School Clubs  
**Target branch:** `staging-site`  
**Production:** no live deployment or live database changes are authorised by this document.

This document records the current agreed direction and the implementation actually present on staging. Where older planning documents describe a different workflow, this document identifies the conflict; it does not silently treat older plans as implemented.

## 1. Core architecture decisions

1. **Listings / Activities remain their own directory.** They continue to use the existing activity, organiser, venue, session, booking and planner systems. They must not appear as rows in another directory.
2. **Businesses is a storefront directory.** Its public source of truth is published rows in `bh_directory_businesses`. Do not create virtual business cards from leader accounts or activity listings. An organiser link may provide the organisation name and contact/profile details, but the business row is the storefront record.
3. **Childcare & Schools, Places & Days Out and School Clubs are separate directories.** Each has its own table and public sector page. They are not aliases for Activities or the legacy venues table.
4. **Only published records are public.** Draft and archived records must not appear in public search.
5. **Staging first.** Database setup is performed through the authenticated admin setup controls on staging. Do not run destructive SQL or change production data as part of this work.
6. **Keep the admin experience simple.** One shared admin manager for Childcare, Places and Clubs, with a sector selector and only the relevant sector fields visible. Businesses retains its own storefront manager.
7. **Do not assume the older leader submission/review workflow is compatible.** It needs a deliberate schema and lifecycle reconciliation before being re-enabled for these tables.

## 2. Public pages and routes

| Directory | Page / canonical route | Public data source |
|---|---|---|
| Listings | `/directory.html` | Existing Activities/listings system |
| Businesses | `/businesses.html` | Published `bh_directory_businesses` storefronts |
| Childcare & Schools | `/childcare.html` (backed by `childcare-schools.html`) | Published `bh_directory_childcare` |
| Places & Days Out | `/daysout` (backed by `places-days-out.html`) | Published `bh_directory_places` |
| School Clubs | `/clubs` (backed by `school-clubs.html`) | Published `bh_directory_school_clubs` |

The three legacy specialist page URLs redirect to the canonical routes:
- `/childcare-schools.html` → `/childcare.html`
- `/places-days-out.html` → `/daysout`
- `/school-clubs.html` → `/clubs`

Each specialist page uses `assets/js/directory-sector.js` and its fixed `data-sector` value. It must not load the universal Activities results script. The four sector pages should remain visually consistent and responsive.

## 3. Current staging schema

### Businesses
- `bh_directory_businesses`: storefront record, with optional `organiser_id`, title, category, summary/description, location, contact details, status and review metadata.
- `bh_directory_business_listings`: optional relationship between a storefront and existing activity IDs.
- The storefront row is the directory record. Linked Activities may be shown as related offerings but must never become Businesses directory rows.
- Leader signup helper `bh_ensure_business_storefront()` creates a draft only when the Businesses table exists and no storefront already exists for that organiser.
- Admin bulk backfill creates missing draft storefronts for active leaders; it must never auto-publish.

### Childcare & Schools
Table: `bh_directory_childcare`

Common fields: `id, title, category, summary, description, town, region, address, postcode, website_url, contact_email, contact_phone, status, reviewed_at, reviewed_by_user_id, created_at, updated_at`.

Additional fields currently installed: `registration_body, registration_reference, verification_url, verified_on`.

### Places & Days Out
Table: `bh_directory_places`

Common fields as above, plus `facilities`.

This is a curated destination dataset. It is not the existing `bh_venues` table and must not be populated by simply exposing legacy activity venues.

### School Clubs
Table: `bh_directory_school_clubs`

Common fields as above, plus `school_name, age_min, age_max, days_times, collection_arrangements, price_details, verification_url, verified_on`.

The current installed schema permits age values 0–18. If the product rule is to enforce a minimum age of 3 for public School Club records, that validation and its wording still need to be agreed and implemented; it is not enforced by the current admin API.

### Status lifecycle currently implemented by the new admin manager
- `draft`: private, editable from admin, not public.
- `published`: public.
- `archived`: available in schema, but the new shared admin manager does not yet expose an archive action.
- The new admin manager saves either draft or published. It does not create a `pending` state or send records to the older review queue.

## 4. Admin management

### Businesses manager
`admin/admin-directory-businesses.html` with `api/admin-directory-businesses.php`:
- Add, edit, search and publish storefronts.
- Link an existing organiser profile.
- Link existing Activities through the relationship table.
- Install the Businesses tables using the authenticated setup action.
- Create missing leader storefront drafts without publishing them.

### Other family directory sections
PR #1104 adds:
- `admin/admin-directory-sectors.html`
- `api/admin-directory-sectors.php`
- Admin dashboard navigation under **Family directory sectors**.

The shared manager provides:
- One selector for Childcare & Schools, Places & Days Out and School Clubs.
- Search, add, edit, save draft and save & publish.
- Core fields shared across the three directories.
- Sector-specific fields displayed only for the selected section.
- Server-side fixed table and field allowlists, admin-session authentication, same-origin HTTPS POST checks and a per-session CSRF token.

PR #1104 must be merged only after its checks pass and the code review is complete. Automated PHP lint/security checks are not a substitute for browser and database testing.

## 5. Important compatibility findings — not yet resolved

### A. Legacy Leader submission APIs do not match the currently installed tables
The existing `api/leader-directory-records.php`, `leader/directory-new.html`, `api/leader-directory-manage.php` and older planning documents describe a richer Leader submission lifecycle. The current three tables installed by the admin setup do not contain all columns expected by that older code. Examples include:
- `organiser_id` ownership columns on the childcare, places and clubs tables;
- age-range, holiday provision, registration and other richer sector fields;
- a `pending` status and review lifecycle.

The current new admin manager deliberately uses a simpler draft/published workflow and does not depend on those Leader APIs. **Do not present Leader submission, ownership isolation or approval workflows for these three tables as working until the schema and APIs have been reconciled and tested together.**

### B. Legacy Admin Directory Review is not compatible with the simplified setup
`api/admin-directory-review.php` expects the consolidated older schema, including Events and shared revision/review structures, and queries `pending` records. The current admin manager saves `draft` or `published` records directly. Do not route these new admin-managed records into the older review queue until a single agreed moderation lifecycle is implemented.

### C. Businesses public API wiring needs correction
The current `businesses.html` uses the shared `directory-sector.js` client and `api/directory-sector.php?type=business`. That generic API reads the storefront table but returns the raw business title. The dedicated `api/directory-businesses.php` contains the intended organiser-name fallback and linked-listing/storefront logic, but the current Businesses page is not using that dedicated endpoint/client.
- Reconnect the public Businesses page to the intended storefront API, or deliberately bring the required storefront logic into the shared endpoint.
- Preserve the rule that only published storefront rows are returned.
- Verify that the visible title is the storefront/organisation name (e.g. “The Boulder Bunker”), not a generic account label.
- Verify website/contact fallback and linked activity counts without turning activities into business rows.

### D. Public sector cards are currently basic
The shared sector client currently displays title, summary/description, category, town, region and a website link. It does not yet display all useful sector-specific fields such as childcare registration source/last-checked date, place facilities/accessibility, or club age/days/collection/price details. Decide and implement the appropriate public card/detail presentation for each sector before calling the public directories complete.

### E. Review metadata
The new admin manager uses the admin session flag. The standalone admin login session does not necessarily contain a Bubba Hub user ID, so `reviewed_by_user_id` may remain null. If named audit attribution is required, add a proper admin identity/audit model rather than assuming `bh_user_id` exists in the admin session.

## 6. Older documents to reconcile

These documents pre-date the simplified staging implementation and contain assumptions that now conflict with the installed schema or workflow:
- `docs/FIVE-NEW-DIRECTORY-SECTORS.md`
- `docs/DIRECTORY-SECTION-OWNERSHIP-MATRIX.md`
- `docs/LEADER-ACCOUNT-DIRECTORY-STANDARD.md)

In particular, older statements about one consolidated SQL install, Leader-owned specialist entries, pending review, revision snapshots and a shared review queue must be labelled as legacy/planned until rebuilt against the current schema. This document is the current staging architecture clarification, not proof that those older features work.

## 7. Staging verification checklist

### Admin and schema
- [ ] Confirm all four directory tables exist on staging: Businesses, Childcare, Places and School Clubs, plus the Business-to-Activity relationship table.
- [ ] Confirm setup actions are idempotent and do not change existing Activities, venues, organisers or Businesses records.
- [ ] Confirm admin login, refresh, navigation and logout work on the new manager.
- [ ] Create one draft in each of the three new sections.
- [ ] Edit and resave each draft.
- [ ] Publish one test record in each section and verify it appears publicly.
- [ ] Verify draft and archived records do not appear publicly.
- [ ] Test invalid URLs, invalid email, missing title/category/town, overlong fields and age bounds.
- [ ] Verify the form only displays fields for the selected section and is usable on mobile.

### Public pages
- [ ] Verify canonical routes and redirects.
- [ ] Verify Childcare, Places and Clubs never show Activities above or mixed into their results.
- [ ] Verify keyword, town/postcode and category filters.
- [ ] Verify empty, loading and API-error states.
- [ ] Verify Businesses uses the intended storefront endpoint and correct organisation/storefront title.
- [ ] Verify related Activities are presented only inside a storefront context, never as Businesses rows.
- [ ] Check browser console and server logs.

### Leader and moderation workflows
- [ ] Keep Leader submission for these three tables marked **not ready** until schema/API compatibility is resolved.
- [ ] Test owner isolation and forged organiser IDs before enabling any Leader write route.
- [ ] Decide whether the future lifecycle is direct admin draft/publish or Leader draft → pending review → admin approval.
- [ ] Do not run the older review queue against these tables until its required schema and status model exist.

### Release control
- [ ] Keep all work on `staging-site` until verified.
- [ ] Record test results and any remaining limitations.
- [ ] Obtain explicit approval before production deployment or live database changes.

## 8. Next implementation order

1. Complete code review and merge the shared admin manager (PR #1104) after checks pass.
2. Connect Businesses to the intended storefront public API and verify the organisation-name behaviour.
3. Improve public cards/details so each sector displays its meaningful sector-specific information.
4. Reconcile and update the older directory planning documents so they no longer describe incompatible schemas as current.
5. Decide the future Leader ownership/submission/moderation model, then implement schema and APIs together.
6. Complete the staging verification checklist before any production release.
