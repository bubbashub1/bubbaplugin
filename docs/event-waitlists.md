# Event waiting lists and released-space offers

Build against the canonical event/ticket model in #21–23 and PR #66, not a second reservation store.

## Functionality
- Admin/organiser toggles waitlists per ticket tier and occurrence.
- Families can join once per event/tier with consent and receive position/confirmation.
- When a reservation is cancelled or capacity increases, allocate an offer transactionally to the earliest eligible waitlist entry.
- Offer has a fixed expiry; accepting is idempotent and requires rechecking capacity inside a transaction.
- Expired/declined offers advance to the next entry; never overbook.
- Email offers through existing SMTP with a secure opaque redemption token; no personal details in URLs.
- Admin view of waiting, offered, redeemed, expired and withdrawn entries; audit manual overrides.

## Staging tests
- Concurrent acceptance cannot exceed capacity.
- Duplicate submissions do not create multiple entries.
- Cancelled event sends no new offers.
- Paid tier acceptance uses Stripe webhook confirmation and does not imply payment on browser redirect.
- SMTP failure does not silently lose the place in queue.
- Role/ownership checks, CSRF, privacy and mobile UX validated.
