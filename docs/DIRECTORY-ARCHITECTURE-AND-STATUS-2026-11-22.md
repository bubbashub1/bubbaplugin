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
7. **Leader submissions use the existing Leader account and organiser identity.** Leaders save drafts or submit for review; admins approve, reject or return submissions. Changes to published records are submitted as revisions and do not alter the live record until approved. The schema upgrade described below must be run on staging before this workflow is considered enabled.

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

The workflow enforces a minimum age of 3 for School Clubs in both the Leader submission API and the shared admin manager. Childcare age ranges are stored in months.

### Status lifecycle currently implemented by the new admin manager
- `draft`: private, editable from admin, not public.
- `published`: public.
- `pending`: submitted by a Leader and awaiting admin review; never public.
- `rejected`: retained for the Leader to correct and resubmit; never public.
- `archived`: hidden from public results; Leader archiving is limited to unpublished records.
- Admins can save drafts or publish directly. Leaders can save drafts or submit for review. Changes to published records use a separate pending revision snapshot.

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
- Sector-specific fields displayed only for the selected section, including childcare age ranges and School Club holiday/registration details.
- Server-side fixed table and field allowlists, admin-session authentication, same-origin HTTPS POST checks and a per-session CSRF token.

PR #1104 was merged and its staging deployment succeeded. PR #1106 fixed the Businesses public API connection and search. PR #1107 added the specialist public-card details. PR #1108 added an additive schema upgrade and aligned the existing Leader submission and Admin review APIs. All associated PHP/security and storefront checks passed, and the staging deployment completed for PRs #1106 and #1107. PR #1108 is merged; its deployment and the admin-triggered database upgrade must be confirmed separately. Automated checks are not a substitute for browser and database testing.

## 5. Compatibility fixes and remaining limitations

### A. Leader submission schema upgrade — code merged, staging action still required
PR #1108 adds **Admin → Businesses → Enable Leader submissions & approval**. After the staging deployment completes, an administrator must run this confirmed action once.
It safely:
- Adds organiser ownership and creator columns to Childcare, Places and School Clubs.
- Adds childcare age ranges, School Club holiday provision and registration/safeguarding details.
- Adds submission timestamps and review notes.
- Expands the four directory status enums to include `pending` and `rejected`, preserving all existing row values.
- Creates `bh_directory_record_reviews` and `bh_directory_record_revisions`.

The action is additive and designed to be re-runnable. It does not update existing records, publish drafts, alter Activities, change organiser profiles or modify production. **Until the action reports success on staging, Leader submissions and the Admin review queue must be treated as not yet enabled.**

### B. Leader ownership and review lifecycle — compatibility code merged
The existing Leader wizard now uses the existing Leader login and resolves the organiser on the server. It supports draft and pending submissions, and published-record changes are stored as revision snapshots. The Admin review API handles installed sections and skips the optional Events table when it is not installed. The Leader workspace likewise skips unavailable optional sections.
- Business Leaders reuse their existing organiser-linked storefront; the wizard must not create a duplicate storefront.
- Leaders can manage only records linked to their resolved organiser.
- Only published records appear in public results.
- Run owner-isolation, submission, approval, return, rejection and published-revision tests after the staging schema upgrade.

### C. Businesses public API — fixed in PR #1106
The standalone Businesses page now uses `api/directory-businesses.php`, including the organiser-name fallback, contact details, logo and related published-activity count. Category options come from published storefront records. Keyword search includes address/postcode, and town search supports partial town/postcode matches.
The universal directory search also uses the dedicated storefront API. Both paths continue to return only published storefront records; activities are never emitted as Businesses rows.

### D. Specialist public cards — improved in PR #1107
- Childcare cards show age range, registration body/reference, verification link and last-checked date.
- Places cards show facilities.
- School Club cards show school, age range, days/times, collection arrangements, holiday provision, costs and registration/safeguarding details.
- The dedicated pages continue using the sector client without loading the universal Activities results script.

### E. Admin attribution
The standalone admin session does not necessarily contain a Bubba Hub user ID, so `reviewed_by_user_id` may remain null for admin decisions. If named audit attribution is required, add a proper admin identity/audit model rather than assuming `bh_user_id` exists in the admin session.

### F. Browser and data verification still outstanding
GitHub checks and successful deployment do not prove that every screen works against the live staging database. The manual staging checklist below remains required, especially the one-time schema upgrade and a full Leader-to-admin approval cycle.

## 6. Older documents to reconcile

These documents contain the earlier full Leader-owned directory proposal and should be treated as historical design references, not the source of current staging implementation status:
- `docs/FIVE-NEW-DIRECTORY-SECTORS.md`
- `docs/DIRECTORY-SECTION-OWNERSHIP-MATRIX.md`
- `docs/LEADER-ACCOUNT-DIRECTORY-STANDARD.md`

The current staging source of truth is this document. PR #1108 has now reconciled the missing schema/API fields in code, but the administrator-triggered staging upgrade and end-to-end testing remain outstanding. Do not use older statements such as “no SQL has been executed” or “no schema is installed” as current status without checking the actual staging database.

## 7. Staging verification checklist

### Admin and schema
- [ ] Confirm all four directory tables exist on staging: Businesses, Childcare, Places and School Clubs, plus the Business-to-Activity relationship table.
- [ ] After deployment, run **Enable Leader submissions & approval** once on staging and confirm success. This adds schema only and preserves existing records/statuses.
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
- [ ] Test Leader draft creation and editing in each of the four directories.
- [ ] Test Leader submit → Admin approve/return/reject → public visibility.
- [ ] Test that pending/rejected/draft entries stay private.
- [ ] Test published-entry revision requests and confirm the public version stays unchanged until approval.
- [ ] Test Leader A cannot read, edit, submit or archive Leader B's records; forged organiser IDs must be ignored.
- [ ] Confirm the Business wizard reuses the existing storefront draft and cannot create a duplicate.

### Release control
- [ ] Keep all work on `staging-site` until verified.
- [ ] Record test results and any remaining limitations.
- [ ] Obtain explicit approval before production deployment or live database changes.

## 8. Next implementation order

1. Confirm PR #1108 deployment has completed on staging.
2. In Admin → Businesses, run **Enable Leader submissions & approval** and confirm success.
3. Complete one full Leader-to-admin review cycle for Business, Childcare, Places and School Clubs.
4. Verify Businesses in both the standalone page and universal directory search.
5. Test public sector cards, filters, canonical routes, mobile layout and empty/error states.
6. Reconcile historical documents and record verified staging results.
7. Obtain explicit approval before any production deployment or live database changes.
