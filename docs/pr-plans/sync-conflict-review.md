# Admin sync conflict review — remaining work

Status: **staging-only implementation backlog**. This PR is documentation only; it must not be merged as an implementation or used to enable live writes.

## What has already landed
- Admin Sheets Sync centre and protected staging preview of field-level changes.
- Saved review queue and authenticated approve/reject actions with audit records.
- Test-fixture guarded apply and atomic rollback, exercised against activity 999999.
- Google Apps Script master-sheet staging bridge with five-row default batch (#379, #380).
- Direct admin dashboard shortcut (#382).

These are separate workflows and **do not yet prove** that a staged master-sheet batch can be reviewed, approved, applied and rolled back end-to-end.

## Remaining acceptance criteria
1. **Batch intake and mapping:** display batches from the current master-sheet bridge, with run ID, source row, matched activity ID, and field allowlist. No silent matches by title; unmatched IDs are skipped with reasons.
2. **Status classification:** count and display new/unmatched, unchanged, proposed changes, conflicts/stale, invalid/failed and applied/rolled-back rows. Never represent unverified new listings as approved.
3. **Field comparison:** show current website value, proposed master-sheet value, source evidence/URL when available, field name, last-check timestamp and reason for conflicts. Escape all user-controlled text.
4. **Review controls:** authorized admin can approve/reject each field and optionally a bounded batch. Require CSRF and explicit confirmation; record reviewer identity, timestamp, source run, before/after values and decision. Rejections never update activities.
5. **Preflight:** immediately before applying, re-read and lock target rows; verify fingerprints, field allowlist, ID, permissions, batch size and idempotency. Stale rows must fail closed and remain reviewable.
6. **Apply and rollback:** reuse and reconcile the existing transactional override/audit/rollback path rather than introducing a second incompatible audit schema from #368. Provide a verified rollback by run ID, preventing rollback when current values have changed.
7. **Staging tests:** start with five eligible rows and activity 999999; test duplicates, absent IDs, malicious markup, stale fingerprints, mixed valid/invalid batches, replay, partial failure, rollback twice, and session expiry. Capture counts and SQL audit evidence.
8. **Live gate:** no live sync or activity writes from this PR. Require separate production migration plan, backup, deployment checklist, manual approval and tested rollback before any live activation.

## Proposed delivery sequence
- PR A: staging read-only prepared-batch list and field-level preview; show counts, errors and source provenance.
- PR B: bridge batch review to existing authenticated decision queue; add conflict/rejection controls and audit tests.
- PR C: connect explicitly approved batches to the existing guarded apply/rollback engine after schema reconciliation and fixture tests.
- PR D: production-readiness checklist only, without enabling live updates.

## Boundaries and dependencies
- #368: **hold** until its proposed preflight/audit table is reconciled with merged apply and rollback code; do not merge both competing implementations.
- #344: scheduled daily reconciliation remains a separate future feature.
- #296: one-listing live repair is not a general-purpose sync mechanism.
- Keep legacy CSV tools outside the preferred master-sheet sync workflow.
