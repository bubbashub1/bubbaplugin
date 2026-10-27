# Temporary one-way website to Google Sheets export

The existing live API `/api/sheets-sync-export.php` is read-only and token authenticated.

## Install
1. Open the existing Google Sheet, Extensions > Apps Script.
2. Add a script file and paste `scripts/website-to-sheets-export.gs` into it.
3. Keep the existing Script Property `BH_SHEETS_SYNC_TOKEN`; never paste it into code.
4. Run `bhWebsiteToSheets` once, approve Google's permissions, and check the new `WEBSITE EXPORT` tab. The `MASTER SHEET` tab is never modified.
5. When the first run is correct, run `bhInstallWebsiteExportEverySixHours` once.
6. In Apps Script > Triggers, disable any scheduled trigger that calls `bhSendLiveSheetSnapshots` or another Sheet-to-website function. Do not delete that function if needed later.

The export includes only ID, title, price_from, age_range, and status. Do not use it as a full backup. Make a website database backup separately before bulk edits. If the export fails, the last successful `WEBSITE EXPORT` tab is retained. No automatic changes are made to website records.

## Verify
Change one non-sensitive listing field through the website's authorised edit form. Run `bhWebsiteToSheets` manually, then confirm the same listing ID and updated field in `WEBSITE EXPORT`. Check Apps Script executions for failures. This verifies export only; website edit permissions are separate.
