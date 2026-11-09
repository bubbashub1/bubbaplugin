# Booking prototype v0.1 – integration review

Date: 2026-11-09  
Repository: `bubbashub1/bubbaplugin`  
Review branch: `prototype/native-family-bookings-v0.1`  
Target: `main`

## Decision

**Do not merge or deploy this prototype to production yet.** The branch is based on current `main` and is mergeable at GitHub level, but that is not evidence of application correctness. No PHP runtime, database integration, concurrency, or browser end-to-end test results were available during this review.

## Changes made during this review

1. `account/bookings.html` no longer renders hard-coded demonstration bookings. It loads `assets/js/booking-requests.js` so the family page reads the signed-in family's saved bookings.
2. `activity.html` now routes the general “Reserve a place” action to the first available dated slot instead of opening a booking modal with no slot ID.
3. `api/booking-requests.php` now counts `attended` requests when checking duplicate family bookings and slot capacity. Marking a request attended must not silently free capacity or permit a second request for the same session.

## Blocking integration findings

### 1. Two booking record models are now competing

The prototype writes to `bh_booking_requests`. The existing application already has `bh_booking_reservations`, and the current My Hub API and leader portal read from `bh_booking_reservations`. As a result, bookings created by this prototype will not automatically appear in the existing My Hub booking summary or existing leader portal booking summary.

**Required:** choose one canonical reservation record. Prefer extending and reusing `bh_booking_reservations` (with a safe additive migration for parent/child snapshots, public reference, message and request metadata) and use `bh_booking_consent_snapshots` for the consent record. Update the My Hub and leader portal queries to expose the same canonical records. Do not leave two independently writable reservation systems.

### 2. Consent is not verified server-side

The activity page sends a consent object from browser local storage. The API currently checks only that a few keys exist and stores the client-supplied object. A browser can alter this payload, so those checks do not prove that the family has a current consent profile or that the selected child matches it.

**Required:** load the authoritative consent profile from `bh_consent_profiles` for the authenticated family and selected child, verify the required consent flags and timestamp on the server, and create a minimal server-generated snapshot containing only the information the family has consented to share. Never accept the browser's consent JSON as authoritative.

### 3. Existing family and leader booking surfaces need one source of truth

The new dedicated pages use `booking-requests.js`, while existing My Hub and leader portal summaries query `bh_booking_reservations`. The dedicated leader page can display prototype requests, but that does not make the existing portal data consistent.

**Required:** after selecting the canonical reservation model, update both existing summaries and the dedicated booking pages to use the same API/data model. Confirm organiser ownership is enforced on every leader read and write.

### 4. External booking settings are only local browser preferences

`booking-manager.js` saves the selected mode and URL in `localStorage`. It does not persist the URL or mode to the server, and “Refresh now” does not refresh an external timetable. Do not present these controls as a working cross-device organiser setting or live sync.

**Required:** either remove/label this as a local prototype control, or persist the mode and one booking URL against the activity/organiser and implement a separately reviewed, safe refresh workflow.

### 5. Booking manager needs stronger input and workflow validation

The current manager uses sequential browser prompts and does not provide venue selection. The API validates activity ownership and optional venue ownership, but the manager does not pass a venue ID. Capacity is counted from request rows rather than maintaining the existing reservation model.

**Required:** replace prompts with a validated form, include venue selection, validate price/capacity/date/time strictly on the server, and keep capacity consistent with the canonical reservation rows.

## Additional review notes

- The existing `bh_booking_slots` table is already present in `database/schema.sql`; the prototype SQL repeats it with `CREATE TABLE IF NOT EXISTS`. This is non-destructive but should be removed from the new migration to avoid implying a new table is needed.
- `br_organiser_id()` uses `user_id` whenever that column exists and does not fall back to email when the column exists but the leader row has a NULL/unlinked `user_id`. Check legacy organiser linkage before relying on it.
- Slot locking with InnoDB and a transactional capacity check is a good starting point, provided the canonical reservation writes all use the same slot lock and table.
- The API should keep generic client errors and private server logs; do not expose SQL or exception details.
- Confirm session cookie settings and CSRF token lifecycle against the existing auth session on the actual hosting configuration.
- Review retention and access to child/consent data before any real family data is collected.
- Do not enable payment capture in this prototype. Payments, refunds, waitlists, email notifications and recurring slot generation remain out of scope.

## Required test plan before merge

1. PHP lint for every changed PHP file.
2. Database migration test against a disposable copy of the current schema, including repeat-run and partially-installed-schema cases.
3. Family access: anonymous, inactive, family, leader and admin roles.
4. Ownership: family can only read own bookings/children; leader can only read/update bookings for their own organiser; another leader receives no access.
5. Consent: missing, revoked, stale, wrong-child and valid consent profiles; tampered client payload must not bypass server checks.
6. Capacity: final place, over-capacity request, simultaneous requests, duplicate family request, cancellation and attended status.
7. Slot lifecycle: past, closed, cancelled, full, unpublished activity, venue mismatch and session closure with active requests.
8. End-to-end browser journey: activity page → dated slot → family request → family My Hub → leader booking list → leader confirms/cancels/marks attended.
9. Regression: existing recurring calendar, Add to Planner, enquiries, login modal, show-password, account registration, leader portal and admin tools.
10. Staging verification before any production merge/deployment.

## Existing work already on main

The sign-in modal/security work (PR #816) and compact calendar/enquiry work (PRs #814 and #815) are already merged into `main`. Do not re-copy these features from the prototype branch.

## Release status

**Review fixes committed to the prototype branch only. PR remains draft. No merge to `main`, production deployment, database migration or live data change was performed.**
