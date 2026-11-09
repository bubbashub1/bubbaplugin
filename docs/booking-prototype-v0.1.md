# Native family bookings – prototype implementation notes

## Scope
A first native Bubba Hub booking workflow: organisers create dated bookable slots, families request places, and leaders manage requests. This prototype does not collect payments.

## Current integration design
The booking flow uses the existing shared database model:
- `bh_booking_slots` for dated sessions.
- `bh_booking_reservations` for family reservations.
- `bh_consent_profiles` for the family's current saved consent.
- `bh_booking_consent_snapshots` for the consent state attached to a particular reservation.

There must not be a second writable `bh_booking_requests` table.

## Staging database instructions
1. Back up the staging database.
2. Confirm the existing schema includes `bh_booking_slots`, `bh_booking_reservations`, `bh_consent_profiles` and `bh_booking_consent_snapshots`.
3. Import `database/booking_integration_v0_2.sql` into STAGING only.
4. Confirm the migration completes and can be run a second time without damaging data.
5. Deploy the matching feature branch to staging only after the SQL migration succeeds.
6. Use test family and leader accounts to complete the full journey.

Do not import the removed `booking_requests_v0_1.sql`. Do not run this migration on production without separate approval.

## Status mapping
The existing reservation enum is preserved:
- `reserved`: new family request awaiting leader review (shown as Pending in the UI).
- `confirmed`: leader confirmed the place.
- `cancelled`: reservation cancelled.
- `attended`: family attended; it continues to count against capacity.

## Not included
Payments, refunds, waitlists, automated booking emails, recurring slot generation, and automatic external timetable imports.
