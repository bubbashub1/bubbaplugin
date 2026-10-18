/**
 * Bubba Hub — read-only staging website / MASTER SHEET ID reconciliation.
 * No database or MASTER SHEET writes. Existing live preview settings unchanged.
 */
function bhPreviewWebsiteIdReconciliation() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) throw new Error('Another reconciliation is running');
  try {
    const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
    if (!token) throw new Error('BH_SYNC_TOKEN is missing');
    const book = SpreadsheetApp.getActiveSpreadsheet();
    const master = book.getSheetByName('MASTER SHEET');
    if (!master) throw new Error('MASTER SHEET is missing');
    const lastColumn = master.getLastColumn();
    if (!lastColumn) throw new Error('MASTER SHEET is empty');
    const headers = master.getRange(1, 1, 1, lastColumn).getDisplayValues()[0].map(v => v.trim().toLowerCase());
    const idColumns = headers.map((h, i) => h === 'id' ? i : -1).filter(i => i >= 0);
    if (idColumns.length !== 1) throw new Error('MASTER SHEET must contain exactly one id column');
    const sheetIds = new Map(), duplicates = [], invalid = [];
    const lastRow = master.getLastRow();
    if (lastRow > 1) {
      const values = master.getRange(2, idColumns[0] + 1, lastRow - 1, 1).getDisplayValues();
      values.forEach((row, i) => {
        const id = String(row[0]).trim();
        if (!id) return; // New sheet row: intentionally unassigned ID
        if (!/^[1-9][0-9]*$/.test(id) || id.length > 18) {
          invalid.push([i + 2, id, 'Invalid sheet ID']);
        } else if (sheetIds.has(id)) {
          duplicates.push([i + 2, id, 'Duplicate of sheet row ' + sheetIds.get(id)]);
        } else {
          sheetIds.set(id, i + 2);
        }
      });
    }
    const websiteIds = new Set(), missingOnSheet = [], seenOnSite = new Set();
    let afterId = 0, pageCount = 0, websiteCount = 0, hasMore = true;
    while (hasMore) {
      if (++pageCount > 10000) throw new Error('Too many export pages; stopped');
      const response = UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-export.php', {
        method: 'post',
        contentType: 'application/json',
        headers: {Authorization: 'Bearer ' + token},
        payload: JSON.stringify({action: 'export', after_id: afterId, limit: 100}),
        muteHttpExceptions: true,
        followRedirects: false
      });
      if (response.getResponseCode() !== 200) throw new Error('Staging export HTTP ' + response.getResponseCode() + ' on page ' + pageCount);
      const data = JSON.parse(response.getContentText());
      if (!data.ok || data.mode !== 'read_only_export' || !Array.isArray(data.records) || typeof data.has_more !== 'boolean') throw new Error('Unexpected export response');
      data.records.forEach(record => {
        const id = String(record.id);
        if (!/^[1-9][0-9]*$/.test(id) || seenOnSite.has(id) || Number(id) <= afterId) throw new Error('Invalid or repeated website ID');
        seenOnSite.add(id);
        websiteIds.add(id);
        websiteCount++;
        if (!sheetIds.has(id)) missingOnSheet.push(['Website only', id, '', String(record.title || ''), 'Review before import']);
      });
      const nextId = Number(data.next_after_id);
      if (data.has_more && (!data.records.length || !Number.isSafeInteger(nextId) || nextId <= afterId)) throw new Error('Invalid export cursor');
      afterId = nextId;
      hasMore = data.has_more;
    }
    const results = [['Status', 'Activity ID', 'Sheet Row', 'Website Title', 'Action']];
    missingOnSheet.forEach(row => results.push(row));
    sheetIds.forEach((rowNumber, id) => {
      if (!websiteIds.has(id)) results.push(['Sheet only', id, rowNumber, '', 'Review before creating website listing']);
    });
    duplicates.forEach(row => results.push(['Duplicate sheet ID', row[1], row[0], '', row[2]]));
    invalid.forEach(row => results.push(['Invalid sheet ID', row[1], row[0], '', row[2]]));
    const review = book.getSheetByName('Website ID Review') || book.insertSheet('Website ID Review');
    review.clearContents();
    review.getRange(1, 1, results.length, 5).setValues(results);
    review.setFrozenRows(1);
    const summary = {
      websiteListings: websiteCount,
      uniqueSheetIds: sheetIds.size,
      websiteOnly: missingOnSheet.length,
      sheetOnly: results.filter(r => r[0] === 'Sheet only').length,
      duplicateSheetIds: duplicates.length,
      invalidSheetIds: invalid.length,
      reviewRows: results.length - 1
    };
    Logger.log('ID reconciliation complete: ' + JSON.stringify(summary));
    Logger.log('Review results in the Website ID Review tab. No listings were changed.');
    return summary;
  } finally {
    lock.releaseLock();
  }
}
