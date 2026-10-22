# Bubba Hub deployment list — Team and Data Sync

Status: planned, not deployed. Deploy to staging first, then live only after approval.

| Order | Work item | PR | Deployment gate |
| --- | --- | --- | --- |
| 1 | Daily Google Sheets reconciliation | #344 | Dry run and idempotency tests pass |
| 2 | Class website verification | #345 | Source evidence and API quota tests pass |
| 3 | Admin sync conflict review | #346 | Approvals, audit and rollback tested |
| 4 | Volunteer team dashboard | #349 | Regional access and moderation tests pass |
| 5 | Admin Team & Permissions | #350 | Events-only role and API authorization tests pass |
| 6 | Regional volunteer coordinator workflow | Existing draft PR | Region scopes and ownership tested |

## Before production
- Check existing auto-import and admin implementation for compatibility.
- Keep production automatic writes disabled until reviewed.
- Test on staging using safe fixtures and non-production accounts.
- Verify owner retains full access and volunteers cannot access payments or private user data.
- Record rollback instructions and obtain explicit merge/deploy approval.
