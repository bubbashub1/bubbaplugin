# Event organiser dashboard and attendance

Depends on the canonical event and ticket models planned in #21–23 and PRs #65–66. Do not add a parallel events store.

## Scope
- Organiser-owned event list: draft, pending, approved, cancelled, completed.
- Edit event details subject to approval rules; protect confirmed attendees from silent material changes.
- Read-only registration totals, capacity, check-ins and no-shows, with private attendee details only where operationally necessary.
- Admin overview and CSV export with role checks and minimal personal data.
- Mobile-friendly event cards and clear actions; admin access to all events, leader access to own events only.
- Audit changes and enforce CSRF on mutations.

## Acceptance
- Leader cannot access another organiser's attendee list or event.
- Empty events show useful state.
- Counts agree with ticket reservations and idempotent check-ins.
- Cancelled events and refunds are reflected correctly.
- Existing leader portal routes and admin approval workflow are reused.
