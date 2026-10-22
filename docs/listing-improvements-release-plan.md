# Listings & organisers — implementation and release gates

This branch is **schema preparation only**, not deployed. No migration has been executed.

## #38 Duplicate detection
- Existing draft PR #43 provides admin-only, read-only candidate matching.
- Check for duplicate activities, venues and recurring session overlaps; never auto-merge.
- Gate: correct schema, no false-positive blocking, admin auth, and wizard JSON repair.

## #39 Claim an existing listing
- Use `bh_listing_claims` for evidence and review history.
- Only authenticated active leaders can submit claims. Derive requester from the server-side session, never the request body.
- Admin must verify ownership and approve explicitly; reject concurrent pending claims for the same listing or require conflict resolution.
- Approval must transactionally update the **existing** organiser association, preserve listing IDs, bookings and URLs, and log the old owner.
- Notify existing owner, requester and admin; never reveal evidence publicly.

## #40 Listing health
- Extend existing Listing Check / Needs Attention rather than duplicate its UI.
- Store current findings in `bh_listing_health_findings`, keyed by activity and rule.
- First rules: missing venue, missing booking URL where booking required, incomplete age range, missing photo, outdated schedule.
- Check external URLs only through existing SSRF-safe URL validation and bounded rate limits; no unbounded fetches.

## #41 Temporary changes
- `bh_session_exceptions` references existing `bh_activity_schedules.id` with a date-scoped override.
- Cancellation, changed time, changed venue or replacement must **not** modify weekly schedule.
- Directory, calendar, planner and bookings must all consume the same effective-session resolver.
- Gate: timezone/DST, overnight sessions, venue mapping, and exception precedence tests.

## #42 Bulk timetable updates
- `bh_timetable_change_batches` stores actor, request, pre-change snapshot and outcome.
- Preview affected sessions and conflicts before commit; validate organiser ownership for **every** selected session.
- Apply in one database transaction; report errors and provide a safe undo path.
- Reuse existing school-term and timetable editor logic; no second schedule engine.

## Safe rollout
1. Fix live wizard JSON/appearance; backup database; capture baseline listing/session counts.
2. Test duplicate warning on staging; then release alone.
3. Build claim API + admin/leader UI, test permission boundaries and release alone.
4. Integrate health rules with existing admin/leader dashboards; release alone.
5. Build shared effective-session resolver and exception UI; verify all consumer pages before release.
6. Build bulk update preview/commit/undo; release last.
7. Every release: staging test, PHP lint, access controls, CSRF for writes, JSON error responses, rollback procedure, smoke test on live.

**Important:** These schema statements are not sufficient by themselves for production: review current DB structure, backup and migrate through a controlled deployment. Do not deploy this branch automatically.
