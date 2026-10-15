# Recurring events and cancellation notifications

Depends on event model #21–23. Reuse timetable exceptions concepts #41–42 and configured school term dates rather than duplicate calendars.

## Scope
- Recurrence by weekday, start/end date, optional term-time-only, plus excluded dates and one-off overrides.
- Store canonical recurrence rule and exception records; generate occurrences predictably with Europe/London DST handling.
- Prevent duplicate occurrence creation during repeated imports or updates.
- Notify registered attendees through existing SMTP when an occurrence is cancelled, moved or materially changed.
- Admin/organiser preview of impacted registrations before confirming changes.
- Unsubscribe and transactional-notification rules handled separately; no bulk email leaks.

## Acceptance
- Recurring event dates across DST and term breaks are correct.
- A single cancellation does not cancel the whole series.
- Email delivery errors are logged safely and can be retried without duplicate notices.
- Existing booked tickets remain associated with the correct occurrence.
- Permissions and audit logs validated on staging.
