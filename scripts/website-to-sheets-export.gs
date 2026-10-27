/**
 * Bubba Hub temporary one-way export: WEBSITE -> GOOGLE SHEETS.
 * Requires existing Script Property BH_SHEETS_SYNC_TOKEN.
 * Never changes MASTER SHEET or writes to the website.
 */
const BH_EXPORT_URL = 'https://bubbahub.co.uk/api/sheets-sync-export.php';
const BH_EXPORT_TAB = 'WEBSITE EXPORT';

function bhWebsiteToSheets() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) throw new Error('Another export is already running');
  try {
    const token = PropertiesService.getScriptProperties().getProperty('BH_SHEETS_SYNC_TOKEN');
    if (!token || token.length < 32) throw new Error('Missing BH_SHEETS_SYNC_TOKEN Script Property');
    const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
    if (!spreadsheet) throw new Error('Run from the bound Master Sheet Apps Script project');
    const columns = ['id', 'title', 'price_from', 'age_range', 'status'];
    const values = [columns];
    const seen = new Set();
    let after = 0;
    for (let page = 0; page < 100; page++) {
      const response = UrlFetchApp.fetch(BH_EXPORT_URL, {
        method: 'post',
        contentType: 'application/json',
        headers: { Authorization: 'Bearer ' + token },
        payload: JSON.stringify({ action: 'export', after_id: after, limit: 100 }),
        muteHttpExceptions: true
      });
      if (response.getResponseCode() !== 200) throw new Error('Export HTTP ' + response.getResponseCode());
      const body = JSON.parse(response.getContentText());
      if (body.ok !== true || !Array.isArray(body.records) || body.mode !== 'read_only_export') {
        throw new Error('Unexpected export response');
      }
      for (const record of body.records) {
        const id = Number(record.id);
        if (!Number.isSafeInteger(id) || id <= after || seen.has(id)) throw new Error('Invalid or duplicate ID');
        seen.add(id);
        values.push(columns.map(key => record[key] == null ? '' : String(record[key])));
      }
      if (!body.has_more) {
        // Do not replace the previous export with an unexpectedly empty result.
        if (values.length <= 1) throw new Error('Empty website export; previous data preserved');
        const tempName = '_BH_EXPORT_TEMP';
        const oldTemp = spreadsheet.getSheetByName(tempName);
        if (oldTemp) spreadsheet.deleteSheet(oldTemp);
        const temp = spreadsheet.insertSheet(tempName);
        temp.getRange(1, 1, values.length, columns.length).setNumberFormat('@');
        temp.getRange(1, 1, values.length, columns.length).setValues(values);
        temp.setFrozenRows(1);
        const previous = spreadsheet.getSheetByName(BH_EXPORT_TAB);
        if (previous) {
          // Preserve previous export until replacement is fully fetched and written.
          const backup = '_BH_EXPORT_PREVIOUS';
          const existingBackup = spreadsheet.getSheetByName(backup);
          if (existingBackup) spreadsheet.deleteSheet(existingBackup);
          previous.setName(backup);
        }
        temp.setName(BH_EXPORT_TAB);
        console.log('Website export complete: ' + seen.size + ' records');
        return;
      }
      const next = Number(body.next_after_id);
      if (!Number.isSafeInteger(next) || next <= after || body.records.length === 0) throw new Error('Invalid pagination');
      after = next;
    }
    throw new Error('Export exceeded page limit; previous data preserved');
  } finally {
    lock.releaseLock();
  }
}

function bhInstallWebsiteExportEverySixHours() {
  ScriptApp.getProjectTriggers()
    .filter(trigger => trigger.getHandlerFunction() === 'bhWebsiteToSheets')
    .forEach(trigger => ScriptApp.deleteTrigger(trigger));
  ScriptApp.newTrigger('bhWebsiteToSheets').timeBased().everyHours(6).create();
  console.log('Six-hour website export trigger installed');
}
