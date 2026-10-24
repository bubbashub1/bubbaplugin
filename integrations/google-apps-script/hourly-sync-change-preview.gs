/**
 * Read-only, field-level change proposal for the Bubba Hub Master Sheet.
 * Requires the authenticated read-only export endpoint.
 * Does not write to the sheet or website.
 */
function bhSyncComparableValue(field, value) {
  const text = String(value ?? '').trim();
  if (field !== 'price_from' || text === '') return text;
  // Treat plain numeric price formatting as equivalent: 2, 2.0 and 2.00.
  // Currency symbols, commas and ranges are NOT normalized; they need review.
  if (!/^(?:0|[1-9]\\d*)(?:\\.\\d+)?$/.test(text)) return text;
  const parts = text.split('.');
  const whole = parts[0];
  const fraction = (parts[1] || '').replace(/0+$/, '');
  return fraction ? whole + '.' + fraction : whole;
}

function bhPreviewHourlySyncChanges() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
  if (!sheet) throw new Error('MASTER SHEET missing');
  const values = sheet.getDataRange().getDisplayValues();
  const headers = values.shift().map(x => String(x).trim().toLowerCase());
  if (headers.filter(x => x === 'id').length !== 1) throw new Error('Expected exactly one id column');
  const allowed = ['title','price_from','age_range','status'];
  const mapped = allowed.filter(x => headers.includes(x));
  const byId = new Map();
  for (let i=0; i<values.length; i++) {
    const row = values[i], id = String(row[headers.indexOf('id')]).trim();
    if (!/^[1-9]\d*$/.test(id) || byId.has(id)) throw new Error('Duplicate or invalid ID at row '+(i+2));
    const fields = {};
    mapped.forEach(name => fields[name] = String(row[headers.indexOf(name)] ?? '').trim());
    byId.set(id,{row:i+2,fields});
  }
  const token = PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token || token.length < 32) throw new Error('BH_SYNC_TOKEN missing');
  let after = 0, complete = false, pages = 0;
  const changes = [], websiteOnly = [], seen = new Set();
  while (!complete && pages++ < 100) {
    const response = UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-export.php',{
      method:'post',contentType:'application/json',
      headers:{Authorization:'Bearer '+token},
      payload:JSON.stringify({action:'export',after_id:after,limit:100}),
      muteHttpExceptions:true,followRedirects:false
    });
    if (response.getResponseCode() !== 200) throw new Error('Export HTTP '+response.getResponseCode());
    const data = JSON.parse(response.getContentText());
    if (data.ok !== true || data.mode !== 'read_only_export' || !Array.isArray(data.records) ||
        typeof data.has_more !== 'boolean' || !Number.isSafeInteger(data.next_after_id)) throw new Error('Unexpected export response');
    for (const item of data.records) {
      const id = String(item.id);
      if (!/^[1-9]\d*$/.test(id) || seen.has(id)) throw new Error('Duplicate/invalid live ID');
      seen.add(id);
      if (!byId.has(id)) {websiteOnly.push(id);continue;}
      const source = byId.get(id);
      for (const field of mapped) {
        // Never propose blank-value overwrites or automatic status changes.
        if (field === 'status') continue;
        const desired = source.fields[field];
        const actual = String(item[field] ?? '').trim();
        if (desired !== '' && bhSyncComparableValue(field, desired) !== bhSyncComparableValue(field, actual)) changes.push({id,sourceRow:source.row,field,current:actual,proposed:desired});
      }
    }
    if (data.has_more && data.next_after_id <= after) throw new Error('Export pagination stalled');
    after = data.next_after_id;
    complete = !data.has_more;
  }
  if (!complete) throw new Error('Safety stop: export incomplete');
  const sheetOnly = [...byId.keys()].filter(id => !seen.has(id));
  const preview = {mode:'READ_ONLY_CHANGE_PROPOSAL',target:'staging',matched:seen.size-websiteOnly.length,
    proposedFieldChanges:changes.length,changes:changes.slice(0,25),
    sheetOnly,websiteOnly,writesPerformed:false};
  console.log(JSON.stringify(preview));
  return preview;
}
