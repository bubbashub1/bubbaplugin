/** Staging-only guarded fixture dry-run. Never sends dry_run:false. */
function bhStagingGuardedFixtureDryRun() {
  const token = PropertiesService.getScriptProperties().getProperty('BH_SHEETS_SYNC_TOKEN');
  if (!token || token.length < 32) throw new Error('BH_SHEETS_SYNC_TOKEN script property missing');
  const endpoint = 'https://staging.bubbahub.co.uk/api/sheets-sync-staging-guarded-fixture.php';
  const payload = {
    action: 'guarded_fixture_update',
    fixture_id: 999999,
    dry_run: true,
    expected_price_from: null,
    expected_age_range: null,
    new_price_from: '2.00',
    new_age_range: '0 - 5 years'
  };
  const response = UrlFetchApp.fetch(endpoint, {
    method: 'post',
    contentType: 'application/json',
    headers: { Authorization: 'Bearer ' + token },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true,
    followRedirects: false
  });
  const status = response.getResponseCode();
  const body = response.getContentText();
  Logger.log('HTTP: ' + status);
  Logger.log(body);
  if (status !== 200) throw new Error('Dry-run failed: HTTP ' + status);
  const data = JSON.parse(body);
  if (data.mode !== 'dry_run' || data.writes_performed !== false || data.fixture_id !== 999999) {
    throw new Error('Unexpected dry-run response');
  }
  Logger.log('SAFE TO PROCEED: ' + data.safe_to_proceed);
  Logger.log('WRITES PERFORMED: ' + data.writes_performed);
}
