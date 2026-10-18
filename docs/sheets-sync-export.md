# Staging website listing export for Sheets reconciliation

Endpoint: POST /api/sheets-sync-export.php on staging.bubbahub.co.uk only.

Authenticated with the existing staging BH_SYNC_TOKEN bearer token (environment, api/config.php or the literal putenv entry in staging wp-config.php). Do not log, expose or commit tokens.

Example JSON request: {"action":"export","after_id":0,"limit":100}

Response includes a page of activity scalar fields, next_after_id and has_more. Advance after_id until has_more is false. This is keyset pagination by numeric activity ID; a concurrent database edit can change a value during a run, so this is not a transactional snapshot. It is a read-only foundation, not an automatic sync.

Only explicitly allowlisted columns are returned. The endpoint exports all activity statuses (including unpublished); treat output as confidential and do not expose it to public visitors. No child, family, user, or booking data are returned.

## Acceptance checks
- GET => 405, wrong host => 403, no/wrong token => 401.
- POST {"action":"export","after_id":0,"limit":2} => at most two rows and a cursor.
- Iterate all pages; IDs are unique and increasing, and total reconciles with database count at test time.
- No database writes, no sheet writes, no triggers.
- Confirm token configured on staging; leave live endpoint unchanged.
- Obtain the 95-row Schema Audit CSV and actual MASTER SHEET headers before mapping create/update logic.
