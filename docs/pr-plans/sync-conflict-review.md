# Admin sync conflict review

Status: design draft only — no production changes.

## Scope
Build admin dashboard showing new, changed, unchanged, conflicting and failed rows, field-level side-by-side values and source evidence. Approve/reject per field or batch with role checks, CSRF protection, audit trail and rollback. No writes until explicit approval in staging; live gate remains off. Acceptance: admin actions logged and rejected conflicts never update records.

## Dependencies
Existing Bubba Hub master Google Sheet, Apps Script sync, staging preview-only endpoint and audit logging. Verify actual endpoint and schema before implementation.

## Rollout
1. Review existing code and configuration.
2. Implement behind disabled feature flag.
3. Test against staging fixture and non-production rows.
4. Document rollback and obtain approval before live writes.
