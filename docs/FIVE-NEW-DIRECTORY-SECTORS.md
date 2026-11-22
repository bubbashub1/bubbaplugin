# Five new Directory sectors — staging build status
> **Historical design reference — not current staging status.** This document records an earlier Directory design. For the installed tables, current admin tools, schema-upgrade step, public API wiring and verified status, use [`DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md`](DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md). Do not assume every workflow or schema described here is already enabled on staging.

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

## Leader Hub management and wizard work
- `leader/directory.html` provides one My Directory entries workspace for Events, Businesses, Childcare & Schools, Places & Days Out and School Clubs. Existing Listings remain in My Classes.
- `api/leader-directory-manage.php` returns only records owned by the authenticated Leader organisation and supports safe archiving of unpublished records. Published records cannot be archived from the Leader interface.
- `leader/directory-new.html` is now a four-step wizard (Basics, Location, Details, Review) for all four specialist sectors. It saves device-local progress, supports draft creation, and can reopen and resubmit drafts or rejected/returned entries.
- `api/leader-directory-records.php` supports owner-checked retrieval and updates of drafts/rejected entries. Published and pending entries cannot be overwritten by this route. Submissions receive a `submitted_at` timestamp.
- Leader Hub navigation and overview now link to all six Directory sectors and the shared management page. The existing Events wizard remains a separate multi-step flow and can link to the Leader's own Listings.

## Still required before database installation is considered complete
1. Review the consolidated SQL against the actual staging database and take a staging backup before installation.
2. End-to-end tests after the SQL is installed: create draft, edit draft, submit, admin approval/return/rejection, public search, owner isolation, archive rules, Events-to-Listing ownership, validation and mobile layout.
3. Confirm actual organiser/user linkage on staging. The APIs prefer `bh_organisers.user_id`; unique email matching is a controlled legacy fallback only.

## Moderation foundation added
- `admin/admin-directory-review.html` provides a protected review queue for the four specialist sections. Only submitted pending records appear; saved drafts stay in the Leader's workspace.
- `api/admin-directory-review.php` supports approve, reject and return-for-edits decisions, uses admin authentication and CSRF, and writes to the shared review history table.
- The Admin Approval Centre now links to the new Directory section review queue.

## Safety and scope
- All commits are on `staging-site`.
- No SQL has been executed.
- No live data, production schema or production deployment has been changed.


## Published-entry revision workflow
- The consolidated SQL adds `bh_directory_record_revisions`, storing a submitted snapshot separately from the published record. Its `section_key` supports Events as well as the four specialist sectors.
- A Leader can open a published specialist entry using **Request changes**. The wizard submits a revision; it does not overwrite the published row.
- Only one pending revision per entry is allowed. Published entries cannot be saved as drafts.
- Admin approval applies the whitelisted payload; returning or rejecting leaves the live record unchanged.
- The public directory continues reading only published records, so pending revisions are not exposed.
- This workflow covers all five new sections, including Events. Published event revisions store the proposed event details, all scheduled dates and linked Listing IDs together. The live event, dates and links remain unchanged until an admin approves the complete revision. The shared review queue displays current versus proposed event details, dates and Listing links.
- No SQL has been executed and no live deployment has been made.


## Published Events revision workflow
- Leaders can use **My Directory entries → Request changes** on a published Event.
- The Event wizard loads the published event, including its scheduled dates and links to the Leader's own Listings.
- Submitting creates a pending revision snapshot; it does not edit the published event.
- The shared Admin Directory review queue shows current and proposed event details, dates and linked Listing IDs.
- Approval updates the event and replaces its scheduled occurrences and Listing links in one database transaction. Rejection or return-for-edits leaves the live event unchanged.
- Only one pending revision per published event is allowed.
