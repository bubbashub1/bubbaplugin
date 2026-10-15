# Bubba Hub — feature architecture and draft PR map

This is a **planning-only draft**, not executable functionality. Implement each section in a separate draft PR. Avoid modifying production data while drafting.

## Foundation
- #72 Core data and permissions: inventory actual WordPress/PHP/HTML routes and bh_* tables; document canonical IDs, ownership, permissions, migrations, rollback, security and test fixtures.
- #81 Analytics, SEO, accessibility: shared design system, performance, consent and one tracking implementation.

## Family experience
- #73 My Hub, profiles, favourites, preferences, privacy and account controls.
- #74 Planner / Planner Pro: recurrence, shared calendars, print/export, event sync and conflicts.
- #51 Deterministic opt-in recommendations, connected to family preferences.

## Organisers and venues
- #75 Leader portal and listing wizard; #49 duplicate class; #38 duplicates; #40 listing health.
- #76 Venue data and directory, extending #50 without duplicating bh_venues.
- #41–42 schedule exceptions, bulk updates and school terms; #45 termly confirmations.

## Events and bookings
- #21–24 Events directory, submissions, Expo tickets and leader first access.
- #58–63 Exhibitor applications, organiser dashboard, recurrence, waitlists, feedback/sponsors, My Hub and newsletter integration.
- #77 Booking/payment/refund architecture, reuse Stripe and booking tables.

## Communications, moderation and revenue
- #79 Server-hosted newsletter and notifications, extending #29 and #48.
- #78 Support, guidance, private questions and moderation.
- #80 Admin approval centre, claims and reporting, extending #52 and #18.
- #82 Pro entitlements, donations, sponsorship, advertisements and billing.

## Future app
- #83 Hybrid Android app, extending #30; shared API and auth rather than separate data storage.

## Required PR checklist
1. Problem, user journey and acceptance criteria.
2. Existing implementation inventory and dependencies.
3. Canonical schema/API contract and migration plan (if needed).
4. Family, leader, admin and Free/Pro permission matrix.
5. Mobile, accessibility and security/privacy requirements.
6. Staging test cases including negative, duplicate and concurrency cases.
7. Backward compatibility, rollback and explicit release approval.

## Release ordering
First map canonical data and permissions (#72), then implement independent vertical slices. Do not merge planning-only PRs as if they deliver features. Each working PR must show actual tests and a staging verification route.
