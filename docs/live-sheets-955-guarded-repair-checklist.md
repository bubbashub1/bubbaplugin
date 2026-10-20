# Listing 955 guarded repair — deployment checklist

**PR is a draft; no automatic syncing is enabled.**

1. Review endpoint against live `api/db.php` (confirmed: `bh_mysql(): PDO`). The currently working `api/sheets-sync-export.php` is not present on `main` in GitHub; verify it is deployed and backed up on the server separately.
2. Back up the live `bh_activities` table and record the current row 955 values.
3. Run the separate `migrations/20261020_bh_sheets_sync_audit.sql` using an authorized database admin. Confirm the table exists and is InnoDB.
4. Deploy endpoint with `BH_SHEETS_955_WRITE_ENABLED` **unset**. GET should return 405 `post_required`; unauthenticated POST should return 401.
5. Authenticated dry-run POST payload:

```json
{"action":"repair_955","id":955,"dry_run":true,"expected_price_from":"","expected_age_range":"","proposed_price_from":"2.00","proposed_age_range":"0 - 5 years"}
```

Expect `ok:true`, `safe_to_proceed:true`, `writes_performed:false` if values remain blank.
6. With writes disabled, the identical authenticated payload with `dry_run:false` must return HTTP 403 `writes_disabled`.
7. **Before enabling any write**, test the same code on staging with a staging-specific token, test record, audit table, and staging-only hostname adjustment. Verify successful update, audit insertion, rollback on audit failure, conflict response, and repeat request refusal.
8. Once approved, enable `BH_SHEETS_955_WRITE_ENABLED=1` temporarily via server-private configuration, send the exact authenticated `dry_run:false` payload once, confirm a single row updated and a single audit row inserted, then **immediately disable the flag**.
9. Re-run live read-only export and compare listing 955 to the sheet; review any remaining differences. No mass updates.

**Important:** This is a one-record guarded repair, not bidirectional sync. Do not schedule it. Never paste a Bearer token into GitHub, logs, or chat.
