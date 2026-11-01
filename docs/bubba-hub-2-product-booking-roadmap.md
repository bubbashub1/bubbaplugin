# Bubba Hub 2.0 — differentiation and future native booking architecture

## Product positioning
Bubba Hub is a local family companion for Devon and Cornwall, not a clone of a baby-class booking directory. Core journey: discover -> save -> plan -> book -> attend -> revisit. Cover pregnancy, babies, children, family events, free community groups, libraries and support.

## Non-negotiable experience principles
- Discovery and basic family planning remain useful without paid membership.
- A class leader can claim and verify an existing listing quickly.
- Free and community listings are first-class; do not bury them below paid results.
- Organisers may keep external booking URLs even after Bubba Hub offers native bookings.
- One canonical organiser, activity, venue and session model; no duplicate schedule truth.
- Every public schedule must indicate freshness and whether the organiser confirmed it.
- Never infer capacity, confirmed booking or revenue from an outbound click.
- AI assists with public factual listing text; human approval is mandatory.

## Native booking rollout — separate gated releases
1. Existing external booking links, safely tracked as outbound actions.
2. Free reservations with capacity, duplicate prevention, confirmation email and cancellation.
3. Paid one-off sessions with payment provider, refunds, reconciliation and audit trail.
4. Term blocks, sibling discounts, waiting lists, transfers and organiser payouts.
5. Unified family bookings, planner reminders and attendance history.

## Data ownership and boundaries
- bh_activities: canonical listing; bh_venues: canonical physical location; bh_sessions: canonical recurring schedule.
- Occurrences are computed from recurring sessions plus term links and exceptions; do not overwrite source sessions during expansion.
- Booking products/prices, inventory, reservations, payments, refunds and attendance require separate tables and explicit state machines.
- Reserve places atomically within database transactions; enforce idempotency keys and unique constraints.
- Payment confirmation only from verified provider webhooks; never from browser redirect alone.
- Keep user/child data minimised, role-scoped and retained according to documented policy.
- Family planner records preferences and selections, not automatic bookings.
- Pro analytics distinguish listing views, clicks, saves, planner additions and verified native bookings.

## Success measures
Weekly returning families, verified active listings, timetable freshness, completed family plans, leader claim-to-publish time, qualified outbound booking clicks, native booking conversion, organiser Pro retention and support burden.

## Delivery sequence
Staging Gemini listing assistance -> schedule engine + verification -> planner clashes -> leader onboarding -> free reservations -> paid booking architecture. No live rollout until migration rehearsal, privacy review, security tests and rollback verification.
