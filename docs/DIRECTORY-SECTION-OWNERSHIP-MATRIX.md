# Bubba Hub Directory: Leader ownership and section matrix
> **Historical design reference — not current staging status.** This document records an earlier Directory design. For the installed tables, current admin tools, schema-upgrade step, public API wiring and verified status, use [`DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md`](DIRECTORY-ARCHITECTURE-AND-STATUS-2026-11-22.md). Do not assume every workflow or schema described here is already enabled on staging.

This is the shared implementation contract for all six Directory sections. It complements `LEADER-ACCOUNT-DIRECTORY-STANDARD.md`. It does not create tables or change live data.

## One identity across the Directory
- Sign in once with the existing Leader account (`bh_users.role='leader'`).
- Resolve the Leader's organisation from `bh_organisers.user_id = bh_users.id` on the server.
- Keep one organisation profile and one owner identity even when the organisation publishes in several Directory sections.
- Do not accept browser-supplied owner IDs as authority.
- Legacy email matching is a temporary reconciliation path only. It must be unique and fail closed when ambiguous.
- A section capability controls which create/manage actions appear; it does not create a new account type.

## Section matrix

| Section | Record type | Leader ownership | Core information | Special checks |
|---|---|---|---|---|
| Listings | Existing activity/class/group | Existing `bh_activities.organiser_id` | Name, description, category, age, price, schedule, venue, booking | Preserve existing wizard, planner, bookings, venues and approval flow |
| Events | One-off or multi-date event | `bh_directory_events.organiser_id` | Event details, occurrences, location, age, booking | Link only to own Listings; each date is an occurrence; admin approval |
| Businesses | Family-facing business/service | Dedicated business record linked to organiser | Business name, service type, description, contact, website, locations | Separate opening hours/service areas; no duplicate Leader account |
| Childcare & Schools | Nursery, childminder, preschool, school | Dedicated childcare record linked to organiser | Setting type, age range, address, contact, sessions, fees | Registration/inspection source and last-checked date; no implied endorsement |
| Places & Days Out | Public family destination | Dedicated destination record linked to organiser where Leader-managed | Place type, facilities, access, location, seasonal details | Curated destination records must not simply expose or rename the legacy venues table |
| School Clubs | Breakfast, after-school, wraparound, holiday club | Dedicated club record linked to organiser | School/site, age eligibility (3+), days, times, collection, holiday cover, cost | Verify eligibility, collection arrangements and relevant registration/safeguarding details |

## Shared record lifecycle
1. Leader creates a draft under the server-resolved organisation.
2. Leader submits the record for review.
3. Admin reviews, returns for edits, approves or rejects; all decisions are auditable.
4. Only approved/published records appear in public Directory searches.
5. Material changes to a published record return it to review.
6. Owner can manage only their own records; admins can moderate across organisations.

## Cross-section relationships
- Events can link to one or more Listings owned by the same organisation; never copy the Listing to create an event.
- Shared location infrastructure should be reused where it is reliable, but section-specific location facts remain on the relevant record.
- A business or childcare provider may also publish classes or events under the same Leader organisation.
- A school may operate multiple School Clubs; keep each club's schedule and eligibility distinct.
- Family support, pregnancy support, feeding support and SEND services should be categories/tags within appropriate sections initially, not extra top-level sections.

## Required security tests for every section
- Unauthenticated request is rejected.
- Active Leader can create a draft owned by their linked organisation.
- A forged organiser ID in the request is ignored.
- Leader A cannot read, edit, delete, submit, link or cancel Leader B's record.
- A Leader with multiple section capabilities still has one account and one organisation identity.
- Pending/rejected/archived records are not public.
- Family accounts cannot use Leader management endpoints.
- Admin moderation requires admin authentication and CSRF protection.

## Implementation order
1. Complete Events schema review and staging validation.
2. Audit and document the existing Listings owner/session linkage before changing it.
3. Build Businesses on the same Leader ownership foundation.
4. Build Childcare & Schools with factual verification metadata.
5. Build Places & Days Out without coupling it to legacy activity venues.
6. Build School Clubs with the 3+ eligibility and safeguarding fields.
7. Add unified Directory search, filters and section capabilities once the specialist records are stable.

No schema is installed and no production database is changed by this document.
