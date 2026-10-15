# Event feedback and sponsor management

Reuse event ownership, Expo exhibitor applications (#58, PR #64), and admin approval workflow (#52).

## Scope
- Optional post-event attendee feedback via single-use invitation with simple rating, accessibility feedback and free-text suggestions.
- Restrict feedback to event admin/organiser summaries, with explicit consent for any public testimonial.
- Sponsor pipeline: organisation/contact, event, package, status (interested/negotiating/confirmed/declined), promised benefits and internal notes.
- Do not store payment card details; link approved payment collection to existing Stripe integration.
- Export summary CSV to authorised admins; prevent sponsor contact details appearing in public event pages.

## Staging tests
- Consent, unsubscribe and access controls.
- Sanitise feedback text and avoid publishing identifying details.
- Duplicate feedback invite submissions are idempotent.
- Sponsor and exhibitor records are linked rather than copied into parallel systems.
