# Native family bookings v0.1

This is a staging-only prototype. It does not collect payments.

## Install
1. Back up the staging database.
2. Import `database/booking_requests_v0_1.sql`.
3. Deploy the branch to staging only.
4. Test with one test family account and one test leader account.

## Behaviour
- Family bookings require an active family account and a valid session CSRF token.
- A booking is tied to a real `bh_booking_slots` row; the slot must be future-dated, open and in Bubba Hub mode.
- Capacity is checked inside a transaction while the slot row is locked.
- Duplicate active requests by the same family for the same slot are rejected.
- Parent, child and consent details are snapshotted on the private booking record.
- Leaders can view only requests for their own organiser and change pending/confirmed requests to confirmed, cancelled or attended.
- Families can view only their own requests.
- No payment is taken. New requests start as `pending`.

## Deliberate limits
- This v0.1 API is not yet wired into the public activity booking modal or leader dashboard.
- It does not send confirmation emails, offer a waitlist, or process refunds.
- Do not run on live until UI integration, security review and staging tests are complete.
