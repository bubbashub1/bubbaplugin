/**
 * Bubba Hub guarded sync release gate (Google Apps Script).
 * Read-only: the second request asks for a write but the server MUST reject it.
 * Never run this test after enabling BH_SHEETS_955_WRITE_ENABLED.
 */
function bhLive955GuardedSafetyGate() {
  var props = PropertiesService.getScriptProperties();
  var token = props.getProperty('BH_LIVE_SYNC_TOKEN');
  if (!token) throw new Error('BH_LIVE_SYNC_TOKEN is missing');

  var url = 'https://www.bubbahub.co.uk/api/sheets-sync-guarded-repair-955.php';
  var request = {
    action: 'repair_955',
    id: 955,
    expected_price_from: '',
    expected_age_range: '',
    proposed_price_from: '2.00',
    proposed_age_range: '0 - 5 years',
    dry_run: true
  };

  function call(payload, withAuth) {
    var options = {
      method: 'post',
      contentType: 'application/json',
      payload: JSON.stringify(payload),
      muteHttpExceptions: true
    };
    if (withAuth) options.headers = { Authorization: 'Bearer ' + token };
    var response = UrlFetchApp.fetch(url, options);
    var body;
    try {
      body = JSON.parse(response.getContentText());
    } catch (e) {
      throw new Error('Non-JSON HTTP ' + response.getResponseCode());
    }
    return { status: response.getResponseCode(), body: body };
  }

  var unauthorized = call(request, false);
  if (unauthorized.status !== 401 || unauthorized.body.error !== 'unauthorized') {
    throw new Error('FAIL: unauthenticated request was not rejected');
  }

  var preview = call(request, true);
  if (preview.status !== 200 || preview.body.ok !== true ||
      preview.body.mode !== 'dry_run' || preview.body.id !== 955 ||
      preview.body.writes_performed !== false) {
    throw new Error('FAIL: authenticated dry run did not pass');
  }

  // This is only safe while the server-side write flag remains DISABLED.
  // Do not set BH_SHEETS_955_WRITE_ENABLED=1 before or during this test.
  var denied = Object.assign({}, request, { dry_run: false });
  var blocked = call(denied, true);
  if (blocked.status !== 403 || blocked.body.error !== 'writes_disabled') {
    throw new Error('STOP: write-disable gate not confirmed. Inspect server before retrying.');
  }

  Logger.log(JSON.stringify({
    mode: 'LIVE_GUARDED_RELEASE_GATE',
    id: 955,
    unauthorizedRejected: true,
    authenticatedDryRunPassed: true,
    liveRecordStillBlank: preview.body.safe_to_proceed === true,
    writeAttemptBlocked: true,
    writesPerformed: false
  }, null, 2));
}
