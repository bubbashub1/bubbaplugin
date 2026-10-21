# Google Sheets daily reconciliation

Status: design draft only — no production changes.

## Scope
Implement scheduled Apps Script trigger calling existing preview-only comparison endpoint. Use activity ID matching, batch pagination, idempotency keys, run logs, last-sync cursor, retry/backoff, and dry-run as default. No live writes. Acceptance: unchanged rows skipped; changed rows flagged; failed batches retry safely; staging fixture 999999 remains protected.

## Dependencies
Existing Bubba Hub master Google Sheet, Apps Script sync, staging preview-only endpoint and audit logging. Verify actual endpoint and schema before implementation.

## Rollout
1. Review existing code and configuration.
2. Implement behind disabled feature flag.
3. Test against staging fixture and non-production rows.
4. Document rollback and obtain approval before live writes.
