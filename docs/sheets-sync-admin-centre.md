# Google Sheets Sync Approval Centre — initial staging shell

URL: `/admin/sheets-sync.html`

This is **visual scaffolding only**. It does not load private records, expose the Google Sheets bearer token, approve changes, read/write baselines, or trigger imports.

Next steps before enabling any approval action:
1. Apply and verify the separately reviewed staging baseline migration, with backup.
2. Add a server-side, admin-session-protected review-list endpoint; avoid leaking values to unauthenticated users.
3. Add a CSRF-protected approval workflow with per-field decisions, server-derived identity, audit IDs, and website fingerprint checks.
4. Keep all writes disabled until review and staging-only testing complete.
5. Link the page from the main admin dashboard after its navigation structure is checked.
