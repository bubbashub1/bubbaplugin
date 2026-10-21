# Bubba Hub — consolidated Google Sheets auto-import

Status: design and integration checklist only. **No automatic writes or triggers are enabled.**

## Existing work to integrate
- PR #127: authenticated read-only preview scaffold (closed unmerged).
- PR #128: ID and normalized booking URL matching (closed unmerged; depends on #127).
- PR #129: three-way field comparison and conflict detection (closed unmerged; depends on #128).
- PR #123: planning, Apps Script and audit schema (closed unmerged).
- PR #302: deployed staging-only guarded fixture 999999 endpoint, with server-side write gate off.
- PR #221: staging read-only public visibility/source audit, still open.
- PR #296: separate live-only guarded repair for ID 955; do not merge into auto-import.

## Required end-to-end flow
1. Read the existing MASTER SHEET with Apps Script; validate column mapping and required identifiers.
2. Send bounded, authenticated batches to a **staging-only preview** endpoint; never embed secrets in browser code.
3. Match by immutable activity ID first, then normalized booking URL, with ambiguous or duplicate matches held for review.
4. Return field-level proposed changes, missing baselines and conflicts without database writes.
5. Require an explicit admin approval of an immutable preview/batch digest before any commit.
6. Commit allowed fields only using transactions, row locks, conflict guards and append-only audit entries.
7. Report per-row results and retry safely with idempotency keys; no silent overwrites, deletes or publishing.
8. Restrict volunteer editing by area; use a separate reviewed approval queue rather than granting production DB access.
9. Schedule imports only after manual staging acceptance, backup/rollback drill and separate production approval.

## Acceptance gates
- PHP lint and unit tests; authentication/authorization and payload-size tests.
- Staging fixture dry run and guarded write with audit/rollback; verify write gate returns to disabled.
- Duplicate IDs, ambiguous URLs, stale baselines, conflicting fields and repeated batches fail safely.
- Compare full master-sheet column inventory with canonical bh_activities / venue / organiser schema.
- Verify no changes to live, legacy import/export, or public listings during this draft stage.

## Release policy
Keep all live automatic writes **OFF**. No cron trigger, live deployment or production data modification is authorized by this planning PR.
