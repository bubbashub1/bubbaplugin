# Bubba Hub Pro Insights — staging implementation specification

## Goal
Show class leaders measurable value from Bubba Hub without claiming that outbound clicks are confirmed bookings.

## Free
- Aggregate listing views and outbound booking-link clicks for the last 30 days.
- Plain-language definition of each metric.

## Pro
- Views, unique visitors (privacy-preserving approximate counts), booking-link clicks, website clicks, contact actions, saves and planner additions.
- Month-on-month trends and per-listing breakdowns.
- Monthly value report comparing activity to the previous period.
- Practical listing-improvement suggestions (AI optional).
- Never calculate actual sales or ROI unless verified conversion/revenue data is explicitly integrated.

## Event model
Record events server-side with fields: event_id, activity_id, organiser_id, event_type, occurred_at, source_context, session_dedupe_key.
Allowed event types: listing_view, booking_click, website_click, email_click, phone_click, save, planner_add.
Use short-lived privacy-safe deduplication keys; do not store raw IP addresses or visitor email addresses for analytics. Define retention, consent and privacy policy before activating collection.
Validate activity IDs and allowed event types server-side. Deduplicate repeated views and filter obvious bots.
Protect dashboards with verified organiser ownership; an organiser may only see their own activities. Admin access separately audited.

## Rollout
1. Audit existing analytics events and current organiser ownership mapping.
2. Build staging-only event collection and aggregated read API.
3. Add Pro dashboard with empty states and sample-mode labels only for demos.
4. Test permissions, duplicate views, privacy controls and performance.
5. Verify actual staging data before release; never fabricate historical numbers.

## Commercial model
Free: basic 30-day overview.
Pro: deeper trends, multi-listing comparisons, monthly value email and suggestions.
Do not market 'unlimited AI' without fair-use, quotas and cost controls.
