# Events directory and submission wizard

Existing `events.html` currently renders upcoming activity sessions from `bhActivities()`; this is **not** a dedicated events inventory. Preserve existing sessions while adding a distinct event feed. Do not replace the activity directory or assume an `api/events.php` exists on main.

## Proposed draft implementation
- Add events model with organiser ownership, title, slug, description, start/end timestamp, venue reference, age guidance, category, cover image, ticketing mode, status and visibility.
- Build mobile-first URL import/manual submission wizard with preview and explicit confirmation. Do not automatically publish scraped third-party descriptions.
- Reuse existing admin approval workflow; prevent public submission from granting publish permissions.
- Display both existing activity sessions and approved standalone events, with separate labels and deduplication.
- Filters: date, town, age and category; links to activity/event detail pages.
- Ensure accessible errors, server validation, CSRF, ownership checks and rate limiting.

## Staging acceptance
- Existing `events.html` activity sessions remain visible.
- Draft event submission never appears publicly until approved.
- Editing another organiser's event is rejected.
- Empty and invalid URLs fall back to manual entry.
- Mobile layout and date/time timezone behaviour pass tests.
- Database migrations are reviewed and backed up before deployment.
