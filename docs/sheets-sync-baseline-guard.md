# Stage 5: trusted baseline guard (staging only)

This PR adds a **pure comparison helper and tests**, not a baseline store or automatic sync.

## Safety requirements before connecting to the endpoint

- Capture a baseline **only after explicit admin approval** of an authoritative website/sheet reconciliation. Never bootstrap a baseline from arbitrary incoming Sheet data.
- Store snapshots in a server-controlled private location, scoped by staging environment, activity ID, field name, version, timestamp and approving admin. Protect access; never place baseline values or credentials in public files.
- Never accept a client-provided `baseline` as trusted. Reject missing, stale or invalid snapshots and require manual review.
- Run three-way checks against an atomic snapshot of website values; re-check website values before any eventual guarded write to avoid races.
- Only allowlisted fields may be compared. Existing input validation remains mandatory.
- Even with a valid baseline, `sheet_changed_review` is **not approval to write**. No updates, inserts, deletes or baseline persistence are enabled here.
- No live deployment or database schema migration in this PR.

The helper intentionally emits `baseline_required` when there is a difference and no trusted snapshot. A field that is equal on both sides can be labelled `same` without a baseline. Conflicts require both sides to have diverged from a trusted baseline.
