# Admin event editing — implementation contract

Status: Draft, not enabled in production.

## Existing entry point
`admin/admin-events.html` currently displays an inactive Edit event card. Replace only after the authenticated edit API and storage model have been reviewed.

## Editable fields
- Details: title, summary, description, organiser contact, slug (slug changes require redirect).
- Schedule: start/end date-time in Europe/London, timezone-aware DST validation.
- Venue: name, address, postcode, accessibility information.
- Media: existing asset selection, uploaded image, validated HTTPS image URL, alt text.
- Applications: exhibitor form open/closed, registration open/closed, optional sponsorship visibility.
- Capacity: maximum visitors (currently intended default 300, adjustable); never reduce below confirmed attendee count.
- Publication: draft/published, public preview, last updated.

## Safety and compatibility
1. Discover authoritative event storage and event-rendering API before schema changes; avoid parallel sources of truth.
2. Require existing admin session validation on **every** read and write; never rely on page-level JS checks alone.
3. POST only for mutations, CSRF token and same-origin checks, input validation, prepared SQL, output escaping.
4. Concurrency control using event version or updated_at; return 409 if stale.
5. Record audit history (admin ID, changed fields, timestamp) without logging sensitive registration data.
6. Preserve existing event IDs, confirmed visitor bookings, QR references, exhibitor applications, sponsorship checkout sessions, payment records and attendee counts.
7. Changing registration status must not invalidate existing confirmed tickets.
8. Uploaded images: size/type limits, MIME sniffing, random filenames, deny executable uploads; never fetch arbitrary server-side URLs without SSRF protections.
9. Stage migrations with backup and rollback. No production writes during testing.
10. Event page, expo page and admin editor must read the same persisted values.

## UI acceptance
- Five grouped sections: Details, Date & Venue, Imagery, Applications & Capacity, Publish.
- Save draft / Publish changes, cancel, unsaved-change warning, inline validation, clear error messages.
- Accessible responsive forms, keyboard navigation and image preview.
- Test existing Expo event, edit description/date/venue/image/settings, reload, verify persistence and public output.
- Verify non-admin GET/POST blocked, CSRF blocked, invalid dates rejected, concurrent edits rejected.
- Confirm existing QR check-in and exhibitor/sponsor workflows still pass.

## Rollout
Implement in a follow-up code commit after inspecting the actual event persistence, auth and API contracts. Do not enable the Edit event link before those safeguards pass.
