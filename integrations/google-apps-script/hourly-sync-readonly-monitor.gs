/**
 * Bubba Hub hourly sync readiness monitor.
 * Read-only by design: no database writes and no spreadsheet edits.
 * Run bhInstallHourlySyncMonitor() once from the bound spreadsheet.
 * Configure Script Property BH_SYNC_TOKEN first.
 */
const BH_HOURLY_MONITOR = Object.freeze({
  sheet: 'MASTER SHEET',
  endpoint: 'https://staging.bubbahub.co.uk/api/sheets-sync-export.php',
  pageSize: 100,
  maxPages: 100
});

function bhHourlySyncReadOnlyCheck() {
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:false,reason:'already_running'};
  try {
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(BH_HOURLY_MONITOR.sheet);
    if (!sheet) throw new Error('MASTER SHEET missing');
    const data = sheet.getDataRange().getDisplayValues();
    if (data.length < 2) throw new Error('Master Sheet is empty');
    const headers = data[0].map(v => String(v).trim().toLowerCase());
    if (headers.filter(v => v === 'id').length !== 1) throw new Error('Expected exactly one id column');
    const idIndex = headers.indexOf('id');
    const ids = new Set();
    for (let i = 1; i < data.length; i++) {
      const id = String(data[i][idIndex]).trim();
      if (!/^[1-9]\d*$/.test(id) || ids.has(id)) throw new Error('Invalid or duplicate ID on row ' + (i + 1));
      ids.add(id);
    }
    const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
    if (!token || token.length < 32) throw new Error('BH_SYNC_TOKEN not configured');
    let after = 0, pages = 0, complete = false;
    const websiteIds = new Set();
    while (!complete && pages < BH_HOURLY_MONITOR.maxPages) {
      const response = UrlFetchApp.fetch(BH_HOURLY_MONITOR.endpoint, {
        method:'post', contentType:'application/json',
        headers:{Authorization:'Bearer ' + token},
        payload:JSON.stringify({action:'export',after_id:after,limit:BH_HOURLY_MONITOR.pageSize}),
        followRedirects:false, muteHttpExceptions:true
      });
      if (response.getResponseCode() !== 200) throw new Error('Live export HTTP ' + response.getResponseCode());
      const result = JSON.parse(response.getContentText());
      if (result.ok !== true || result.mode !== 'read_only_export' ||
          !Array.isArray(result.records) || typeof result.has_more !== 'boolean' ||
          !Number.isSafeInteger(result.next_after_id)) throw new Error('Invalid export response');
      result.records.forEach(record => {
        const id = String(record.id);
        if (!/^[1-9]\d*$/.test(id) || websiteIds.has(id)) throw new Error('Duplicate/invalid website ID');
        websiteIds.add(id);
      });
      pages++;
      if (result.has_more && result.next_after_id <= after) throw new Error('Export pagination stalled');
      after = result.next_after_id;
      complete = !result.has_more;
    }
    if (!complete) throw new Error('Export exceeded safety page limit');
    const missingFromWebsite = [...ids].filter(id => !websiteIds.has(id));
    const websiteOnly = [...websiteIds].filter(id => !ids.has(id));
    const summary = {ok:true,mode:'read_only',sheetCount:ids.size,websiteCount:websiteIds.size,
      missingFromWebsite:missingFromWebsite.length,websiteOnly:websiteOnly.length,
      writesPerformed:false,checkedAt:new Date().toISOString()};
    console.log(JSON.stringify(summary));
    return summary;
  } catch (error) {
    console.error('Bubba Hub hourly sync check failed: ' + error.message);
    throw error;
  } finally {
    lock.releaseLock();
  }
}

function bhInstallHourlySyncMonitor() {
  ScriptApp.getProjectTriggers()
    .filter(trigger => trigger.getHandlerFunction() === 'bhHourlySyncReadOnlyCheck')
    .forEach(trigger => ScriptApp.deleteTrigger(trigger));
  ScriptApp.newTrigger('bhHourlySyncReadOnlyCheck').timeBased().everyHours(1).create();
}
