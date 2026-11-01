# Bubba Hub 2.0 — Stage 0 repository audit and rollout gates
Date: 2026-11-01
Status: IN PROGRESS — repository review only; no staging database or deployment verified.

## Confirmed from current repository
- database/schema.sql defines bh_organisers, bh_activities, bh_venues and bh_sessions; existing tables must be reused rather than creating parallel activity/venue systems.
- assets/js/admin-new-listing.js already contains a multi-step admin editor and booking import review workflow.
- api/admin-ical-preview.php is an existing booking URL extraction endpoint. Do not create a competing booking scraper.
- api/claim-invitations-cron.php is a CLI worker; new scheduled jobs should follow the same fail-closed operational approach.
- api/admin-session-guard.php supports administrator-only API authentication.
- api/admin-ai-listing-suggest.php is an initial staging-only AI suggestion endpoint; it has NOT been wired to the wizard or tested with Gemini.
- Existing live database schema can differ from schema.sql (the organiser user_id mismatch was observed). Never assume CREATE TABLE IF NOT EXISTS migrates existing tables.

## Required discovery before schema changes
1. Inventory actual staging tables, columns, indexes, foreign keys, row counts and current migrations.
2. Identify canonical ownership links for organisers and authorised volunteers.
3. Identify current session recurrence/date fields, timetable storage, booking URL ownership, planner data and school-term structures.
4. Audit existing user roles, Pro entitlements, payments, analytics events, emails and privacy settings.
5. Confirm backup/restore access and staging-to-live isolation.
6. Review staging deployment workflow; do not merge to main or run live migrations.

## Stage 1 — AI-assisted listings
- One server-side Gemini adapter for public, non-booking listing text, returning field-level suggestions only.
- User selects each suggested change; never silently overwrite a saved value.
- Avoid hallucinated URLs, contacts, ages, prices and categories; validate against allowed values.
- Before enablement: enforce server-side per-user quotas, request size and rate limits, response caching, timeout, spending guard, audit metadata, CSRF, and role-specific permissions.
- Never send private booking or family data to AI.
- Staging-only key in server configuration, not source control.
- Free: initial proposed 3 assisted actions/month; Pro: generous configurable fair-use quota, NOT unmetered unlimited.
- Confirm whether a user is Free/Pro from canonical entitlement source, not client parameters.

## Stage 2 — Native schedules
- Reuse bh_sessions where possible; create migrations only for missing recurrence/exception/verification fields.
- Support weekly patterns, start/end date, term calendars, holiday overrides, cancellation, rescheduling, and per-venue sessions.
- Explicit verification status and last-confirmed timestamp; don't conflate scheduled sessions with booking availability.
- Add conflict detection for family planner using actual start/end instants and timezone Europe/London.
- Booking imports become optional suggestions for schedule changes; canonical schedules remain under authorised owner control.

## Stage 3 — Analytics and monetisation
- Define allowed events and consent/retention before tracking.
- Verify organiser ownership on every Pro Insights query.
- Report clicks as intent, not confirmed bookings or sales.
- Free basic totals; Pro trends, per-listing breakdowns and monthly summaries.
- Payment provider webhook verification, idempotency, cancellation and entitlement reconciliation required before billing.

## Stage 4 — Automation
- Queue/retry with idempotency keys; audit source and previous values; no unattended overwrites.
- Deduplicate email notifications and provide preferences.
- Stage in small batches, confirm results and have rollback procedures.

## Release gates
[ ] Staging database inventory reviewed
[ ] Backups and restore tested
[ ] Permission and cross-account access tests
[ ] AI usage/rate/cost limits tested
[ ] Timetable recurrence and DST tests
[ ] Booking import compatibility tests
[ ] Planner collision tests
[ ] Privacy and retention review
[ ] Payment sandbox tests
[ ] Staging smoke tests
[ ] Explicit approval for any live deployment

Do not merge this development branch to main until gates relevant to the release are met.
