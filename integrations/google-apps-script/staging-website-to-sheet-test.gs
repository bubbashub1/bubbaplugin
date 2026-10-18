/**
 * Bubba Hub staging-only website -> Google Sheets isolated test.
 * Reads exact activity 999999 from the authenticated, read-only export API.
 * Writes ONLY the "Staging Sync Test" tab; never touches MASTER SHEET.
 * Manual execution only; no triggers and no website writes.
 */
function bhTestWebsiteToSheet999999() {
  const tabName = 'Staging Sync Test';
  const expectedId = '999999';
  const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token) throw new Error('BH_SYNC_TOKEN is missing from Script Properties');

  const response = UrlFetchApp.fetch(
    'https://staging.bubbahub.co.uk/api/sheets-sync-export.php', {
      method: 'post',
      contentType: 'application/json',
      headers: { Authorization: 'Bearer ' + token },
      payload: JSON.stringify({ action: 'export', after_id: 999998, limit: 2 }),
      muteHttpExceptions: true,
      followRedirects: false
    }
  );
  const status = response.getResponseCode();
  if (status !== 200) throw new Error('Staging export HTTP ' + status + ': ' + response.getContentText().slice(0, 300));
  const data = JSON.parse(response.getContentText());
  if (!data.ok || data.mode !== 'read_only_export' || !Array.isArray(data.records)) {
    throw new Error('Unexpected staging export response');
  }
  const matches = data.records.filter(function(r) { return String(r.id) === expectedId; });
  if (matches.length !== 1) throw new Error('Expected exactly one staging activity 999999; found ' + matches.length);
  const record = matches[0];
  if (record.title !== 'BUBBA HUB SYNC TEST - DO NOT PUBLISH' || record.status !== 'draft') {
    throw new Error('Unexpected test listing title or status; no sheet changes made');
  }

  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) throw new Error('Open the script from the target spreadsheet');
  const headers = ['id','title','status','slug','description','source','imported_at'];
  const lock = LockService.getDocumentLock() || LockService.getScriptLock();
  lock.waitLock(15000);
  try {
    let sheet = spreadsheet.getSheetByName(tabName);
    if (!sheet) {
      sheet = spreadsheet.insertSheet(tabName);
      sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
      sheet.setFrozenRows(1);
    } else {
      const current = sheet.getRange(1, 1, 1, headers.length).getValues()[0].map(String);
      if (current.join('|') !== headers.join('|')) {
        throw new Error('Existing Staging Sync Test headers differ; no changes made');
      }
    }
    const lastRow = sheet.getLastRow();
    if (lastRow > 1) {
      const ids = sheet.getRange(2, 1, lastRow - 1, 1).getDisplayValues().map(function(row) { return row[0]; });
      if (ids.indexOf(expectedId) !== -1) {
        Logger.log('Already imported activity 999999 to Staging Sync Test; no duplicate added.');
        return;
      }
    }
    const safe = function(value) {
      const str = String(value == null ? '' : value);
      return /^[=+\-@]/.test(str) ? "'" + str : str;
    };
    sheet.appendRow([
      expectedId,
      safe(record.title),
      safe(record.status),
      safe(record.slug),
      safe(record.description),
      'staging website export',
      new Date()
    ]);
    Logger.log('Success: activity 999999 added once to Staging Sync Test; MASTER SHEET unchanged.');
  } finally {
    lock.releaseLock();
  }
}
