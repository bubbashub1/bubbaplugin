# Class website source verification

Status: design draft only — no production changes.

## Scope
Add opt-in Google Custom Search API discovery for original class leader websites and booking pages; compare title, venue, timetable, age and price against master sheet. Store source URL, retrieved timestamp, confidence and evidence; do not treat search snippets as authoritative. Rate limits, quotas, robots/privacy compliance, no automatic overwrites. Acceptance: missing/ambiguous sources flagged for review.

## Dependencies
Existing Bubba Hub master Google Sheet, Apps Script sync, staging preview-only endpoint and audit logging. Verify actual endpoint and schema before implementation.

## Rollout
1. Review existing code and configuration.
2. Implement behind disabled feature flag.
3. Test against staging fixture and non-production rows.
4. Document rollback and obtain approval before live writes.
