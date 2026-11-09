# Booking prototype – integration review and current status

Date: 2026-11-09  
Repository: `bubbashub1/bubbaplugin`  
Branch: `prototype/native-family-bookings-v0.1`  
Target: `main`

## Current decision

**NOT READY TO MERGE OR DEPLOY.** The first integration pass now uses the existing reservation model and server-side consent profiles. The work remains on the development branch. No live deployment, live database migration or live data change has been performed.

## Changes made in the integration pass

1. Added `database/booking_integration_v0_2.sql` to extend existing `bh_booking_reservations` fields and backfill organiser ownership. It does not create a second reservation table or recreate `bh_booking_slots`.
2. Added `api/consent-profile.php` for authenticated family accounts to read and save consent profiles against `bh_consent_profiles`.
3. Updated `assets/js/consent.js` to save consent to the authenticated account rather than treating localStorage as the authoritative record.
4. Reworked `api/booking-requests.php` to read and write `bh_booking_reservations`, create server-generated consent snapshots in `bh_booking_consent_snapshots`, and enforce capacity against canonical reservation rows.
5. Updated the activity booking form to load the family's server-side consent profile and send no browser-supplied consent JSON.
6. Updated the dedicated leader booking API and existing leader portal query to read canonical reservations. Venue joins in the existing leader query are now optional.
7. Capacity is synchronised after a booking is made or a leader changes its status. Cancelled reservations release places; attended reservations continue to count.
8. Booking Manager now has a visual session form with date, start/end time, capacity, price and optional venue selection. Booking mode and external URL are saved against the selected activity; external timetable importing is not claimed or enabled.
9. A separate booking-time confirmation checkbox is required and timestamped in the server-generated consent snapshot.

## Canonical booking model

- `bh_booking_slots`: dated sessions and capacity.
- `bh_booking_reservations`: one reservation record per family account and slot. Existing status values are retained: `reserved` (awaiting organiser action), `confirmed`, `cancelled`, `attended`.
- `bh_consent_profiles`: current family-owned consent profile.
- `bh_booking_consent_snapshots`: dated snapshot linked by `reservation_id`.

The old `bh_booking_requests` table is not part of the new design and its prototype migration has been removed. Do not import the old v0.1 SQL file.

## Security and data handling

- Booking endpoints require an active signed-in family or leader account and use the existing session/CSRF pattern.
- Leader reads and updates are scoped to the organiser linked to the signed-in account.
- Child records are checked against the signed-in family account.
- Consent is read from the server-side profile and must match the selected child; browser-supplied consent JSON is ignored.
- The booking snapshot is generated server-side from the saved profile and stored separately from the reservation.
- No payment capture, refunds, waitlists, automated email or recurring-slot generation is included.

## Remaining work before merge

1. Confirm the branch remains synchronised with `main` and review the latest complete diff.
2. Confirm the new visual booking form and venue selector work correctly with real organiser-linked venues.
3. Confirm the family booking page, existing My Hub summary, dedicated leader booking page and existing leader portal all display the same reservation consistently.
4. Review consent snapshot access and retention, and confirm only the minimum necessary information is visible to the correct organiser.
5. Confirm the legacy organiser account link fallback against actual schema and linked accounts.
6. Review slot closure/cancellation and reservation status transitions, including reopening a slot after cancellation.
7. Validate SQL migration on a disposable copy, including repeat-run and partially installed schema.
8. Run PHP lint on all changed PHP files and JavaScript syntax checks.
9. Run role, ownership, consent tampering, capacity/concurrency and full end-to-end tests.
10. Complete staging verification before merge or production deployment.

## Test status

- Code/schema review: **in progress**.
- PHP lint: **NOT RUN**.
- JavaScript syntax/browser checks: **NOT RUN**.
- Disposable database migration test: **NOT RUN**.
- Role and ownership tests: **NOT RUN**.
- Consent validation/tampering tests: **NOT RUN**.
- Capacity/concurrency tests: **NOT RUN**.
- End-to-end browser test: **NOT RUN**.
- Staging verification: **NOT RUN** (staging environment has not yet been confirmed restored).

## Release status

**NOT READY.** Continue on the prototype branch. Do not merge to `main`, import SQL into production, or deploy until the remaining work and tests are complete and the user has approved the release.
