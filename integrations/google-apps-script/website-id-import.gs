/**
 * Development-only website-to-sheet ID reconciliation.
 * No automatic triggers. Call with authenticated website export rows from
 * a verified staging API; this function does not fetch secrets or write website data.
 */
function bhAppendMissingWebsiteListings_(records) {
  if (!Array.isArray(records) || records.length > 1) throw new Error('One-listing test only');
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
  if (!sheet) throw new Error('MASTER SHEET missing');
  const headers = sheet.getRange(1,1,1,sheet.getLastColumn()).getValues()[0].map(String);
  const idCol = headers.indexOf('id');
  if (idCol < 0) throw new Error('Missing id header');
  const existing = new Set(sheet.getRange(2,idCol+1,Math.max(1,sheet.getLastRow()-1),1).getValues().flat().map(String));
  records.forEach(record => {
    const id = String(record.id || '');
    if (!/^\\d+$/.test(id)) throw new Error('Invalid activity ID');
    if (existing.has(id)) return;
    const row = headers.map(h => Object.prototype.hasOwnProperty.call(record,h) ? record[h] : '');
    sheet.appendRow(row);
    existing.add(id);
  });
}
