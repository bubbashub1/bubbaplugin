/** Manually run this dry-run first; no changes to MASTER SHEET. */
function bhTestSingleStagingInsertDryRun() {
  const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token) throw new Error('Missing BH_SYNC_TOKEN');
  const response = UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-test-insert.php', {
    method: 'post',
    contentType: 'application/json',
    headers: {Authorization:'Bearer ' + token},
    payload: JSON.stringify({
      action:'create_test_one',
      dry_run:true,
      values:{id:'999999',title:'BUBBA HUB SYNC TEST - DO NOT PUBLISH'}
    }),
    muteHttpExceptions:true,
    followRedirects:false
  });
  Logger.log('HTTP: ' + response.getResponseCode());
  Logger.log('Response: ' + response.getContentText());
}
