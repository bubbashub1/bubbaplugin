/**
 * Bubba Hub comparison extension — development only.
 * Paste into a separate Apps Script file alongside the working preview script.
 * Uses existing BH_SYNC, bhGetSyncConfig_ and bhLog_ helpers.
 */
function bhCompareSync() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) throw new Error('Another sync is running');
  try {
    const book = SpreadsheetApp.getActiveSpreadsheet();
    const master = book.getSheetByName('MASTER SHEET');
    if (!master) throw new Error('MASTER SHEET missing');
    const config = bhGetSyncConfig_();
    const compareUrl = 'https://staging.bubbahub.co.uk/api/sheets-sync-compare.php';
    // Deliberately staging-only until the endpoint has been verified.
    if (config.url.indexOf('https://staging.bubbahub.co.uk/') !== 0) {
      throw new Error('Comparison requires staging configuration; live preview settings must remain unchanged.');
    }
    const all = master.getDataRange().getValues();
    const headers = all[0].map(v => String(v).trim());
    if (headers.indexOf('id') < 0) throw new Error('Missing id column');
    const rows = [];
    for (let i = 1; i < all.length; i++) {
      if (all[i].every(v => v === '' || v === null)) continue;
      const values = {};
      headers.forEach((header, j) => {
        if (!header) return;
        const v = all[i][j];
        values[header] = v instanceof Date
          ? Utilities.formatDate(v, Session.getScriptTimeZone(), 'yyyy-MM-dd')
          : v;
      });
      rows.push({sheet_row:i + 1,values:values});
    }
    const findings = [];
    for (let i = 0; i < rows.length; i += 25) {
      const response = UrlFetchApp.fetch(compareUrl, {
        method:'post',
        contentType:'application/json',
        headers:{Authorization:'Bearer ' + config.token},
        payload:JSON.stringify({action:'compare',rows:rows.slice(i,i+25)}),
        muteHttpExceptions:true,
        followRedirects:false
      });
      if (response.getResponseCode() !== 200) {
        throw new Error('Comparison HTTP ' + response.getResponseCode() + ' at batch ' + (Math.floor(i/25)+1));
      }
      const result = JSON.parse(response.getContentText());
      if (!result.ok || !Array.isArray(result.results)) throw new Error('Invalid comparison response');
      result.results.forEach(item => {
        if (item.differences && item.differences.length) {
          item.differences.forEach(diff => findings.push([
            item.sheet_row,item.id,diff.field,
            String(diff.sheet_value),String(diff.website_value),item.status
          ]));
        } else {
          findings.push([item.sheet_row,item.id,'','','',item.status]);
        }
      });
    }
    let review = book.getSheetByName('Sync Review');
    if (!review) review = book.insertSheet('Sync Review');
    review.clearContents();
    const output = [['Sheet Row','Activity ID','Field','Google Sheets','Website','Status']].concat(findings);
    review.getRange(1,1,output.length,6).setValues(output);
    review.setFrozenRows(1);
    bhLog_('compare','ok',findings.length + ' review lines written');
    return {ok:true,reviewLines:findings.length};
  } finally {
    lock.releaseLock();
  }
}
