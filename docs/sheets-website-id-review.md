# Read-only website ID reconciliation

Copy integrations/google-apps-script/website-id-review.gs into a **new** Apps Script file in the existing spreadsheet project, alongside the existing preview. Run bhPreviewWebsiteIdReconciliation manually.

Uses existing BH_SYNC_TOKEN but explicitly calls the staging-only authenticated export endpoint. Does not change BH_SYNC_URL, MASTER SHEET, database, or any trigger.

Creates/replaces only the Website ID Review tab with website-only, sheet-only, duplicate, and invalid IDs. Existing sheet IDs are read using getDisplayValues() to avoid numeric coercion. Blank IDs are intentionally excluded from ID comparison and require a separate new-row workflow.

Validation: run after staging deployment; verify summary counts, inspect sampled IDs and titles, and check that the MASTER SHEET has not changed. This is a reconciliation snapshot, not automatic bidirectional sync. No inserts, updates, deletes, or automatic triggers. Compare fields only after the 95-row Schema Audit CSV and exact MASTER SHEET headers have been reviewed.
