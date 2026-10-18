/**
 * Staging-only: refresh the existing activity 999999 row from the website.
 * Only touches the "Staging Sync Test" tab. No triggers or website writes.
 * Rejects changes to the test row made independently in Sheets.
 */
function bhRefreshStagingTestRow999999() {
  const tabName = 'Staging Sync Test';
  const id = '999999';
  const baselineDescription = 'Staging-only Google Sheets sync test. Not a real activity.';
  const updatedDescription = baselineDescription + ' Update test 1.';
  const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token) throw new Error('BH_SYNC_TOKEN is missing from Script Properties');
  const response = UrlFetchApp.fetch(
    'https://staging.bubbahub.co.uk/api/sheets-sync-export.php', {
      method: 'post',
      contentType: 'application/json',
      headers: {Authorization: 'Bearer ' + token},
      payload: JSON.stringify({action: 'export', after_id: 999998, limit: 2}),
      muteHttpExceptions: true,
      followRedirects: false
    }
  );
  if (response.getResponseCode() !== 200) throw new Error('Export failed HTTP ' + response.getResponseCode());
  const data = JSON.parse(response.getContentText());
  if (data.ok !== true || data.mode !== 'read_only_export' || !Array.isArray(data.records)) throw new Error('Invalid export response');
  const matches = data.records.filter(function(r) {return String(r.id) === id;});
  if (matches.length !== 1) throw new Error('Expected exactly one activity 999999');
  const record = matches[0];
  if (record.title !== 'BUBBA HUB SYNC TEST - DO NOT PUBLISH' || record.status !== 'draft') throw new Error('Unexpected website test listing');
  if (record.description !== updatedDescription) throw new Error('Website description differs from approved test update; refusing to sync');

  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (!ss) throw new Error('Open the script from the target spreadsheet');
  const lock = LockService.getDocumentLock() || LockService.getScriptLock();
  lock.waitLock(15000);
  try {
    const sheet = ss.getSheetByName(tabName);
    if (!sheet) throw new Error('Staging Sync Test tab is missing; run the initial import first');
    const headers = ['id','title','status','slug','description','source','imported_at'];
    const actualHeaders = sheet.getRange(1,1,1,headers.length).getDisplayValues()[0];
    if (headers.join('|') !== actualHeaders.join('|')) throw new Error('Test tab headers changed');
    if (sheet.getLastRow() < 2) throw new Error('Test row missing');
    const ids = sheet.getRange(2,1,sheet.getLastRow()-1,1).getDisplayValues().map(function(row){return row[0];});
    const matchingIndices = ids.map(function(value,i){return value === id ? i+2 : -1;}).filter(function(i){return i > 0;});
    if (matchingIndices.length !== 1) throw new Error('Expected exactly one test row; found ' + matchingIndices.length);
    const rowIndex = matchingIndices[0];
    const existing = sheet.getRange(rowIndex,1,1,7).getDisplayValues()[0];
    if (existing[1] !== record.title || existing[2] !== 'draft' || existing[3] !== record.slug || existing[5] !== 'staging website export') {
      throw new Error('Sheet row was edited independently; conflict, no changes made');
    }
    if (existing[4] === updatedDescription) {
      Logger.log('Already up to date: activity 999999; no duplicate and no changes.');
      return;
    }
    if (existing[4] !== baselineDescription) throw new Error('Sheet description conflict: manual edit detected; no changes made');
    sheet.getRange(rowIndex,5).setValue(updatedDescription);
    Logger.log('Success: refreshed existing activity 999999 description in Staging Sync Test; no row added; MASTER SHEET unchanged.');
  } finally {
    lock.releaseLock();
  }
}
