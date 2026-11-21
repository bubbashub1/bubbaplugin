# Five new Directory sectors — staging build status

This build treats Events, Businesses, Childcare & Schools, Places & Days Out, and School Clubs as five new sections alongside the existing Listings directory. Listings continue to use the existing activity tables and are not migrated.

## Public section pages
- `events.html` — Events (existing one-off events foundation).
- `businesses.html` — searchable Businesses section.
- `childcare-schools.html` — searchable Childcare & Schools section.
- `places-days-out.html` — searchable Places & Days Out section.
- `school-clubs.html` — searchable School Clubs section.

The four specialist sections share the public search script `assets/js/directory-sector.js` and public API `api/directory-sector.php`. The public API only returns records with `status='published'`. Search supports keyword, town/postcode and category.

## Leader submission foundation
- `leader/directory-new.html?type=business`
- `leader/directory-new.html?type=childcare`
- `leader/directory-new.html?type=place`
- `leader/directory-new.html?type=school_club`
- `api/leader-directory-records.php`

The Leader Hub navigation links to all four new submission tools. They use the existing Leader session, resolve the organiser on the server from the authenticated user, use CSRF and same-origin checks, validate URLs and section-specific rules, and save as draft or pending review. No user-supplied owner ID is accepted. Email reconciliation is only used when exactly one legacy organiser email match exists.

## Sector-specific fields
- Businesses: business type, public profile, location, website and contact details.
- Childcare & Schools: setting type, age range, registration body/reference, official verification URL and last-checked date.
- Places & Days Out: destination type, facilities and accessibility notes. This is a separate curated destination dataset, not the existing venues table.
- School Clubs: club type, minimum age of 3+, school/site, collection arrangements, days/times, holiday provision, fees and verification details.
- Events: one-off/multi-date events, occurrence records, optional links to the Leader's own Listings and admin approval.

## Consolidated SQL
`database/events-directory-schema.sql` now contains the Events tables plus Businesses, Childcare & Schools, Places & Days Out, School Clubs and shared review history. This is one staging installation batch for all five new sectors. It does not alter `bh_activities`, existing venues, bookings or planner records.

**Do not run the SQL until a staging backup is available and the schema has been reviewed against the actual staging database.** No database installation has been performed.

## Still to build before the database batch is considered complete
1. Admin moderation UI/API for the four specialist section types, including return-for-edits/reject/approve and review audit history.
2. Leader "My Directory entries" management list, edit/resubmit and archive/cancel workflows.
3. End-to-end tests after the consolidated schema is installed: draft, pending, admin approval, public search, owner isolation, validation and mobile layout.
4. Confirm actual organiser/user linkage on staging. The new API prefers `bh_organisers.user_id`; unique email matching is legacy fallback only.

## Safety and scope
- All commits are on `staging-site`.
- No SQL has been executed.
- No live data, production schema or production deployment has been changed.
