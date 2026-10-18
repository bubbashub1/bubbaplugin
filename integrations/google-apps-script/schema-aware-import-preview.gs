/**
 * Bubba Hub Phase 2 — schema-aware read-only audit + import preview.
 * Spreadsheet-bound Apps Script. NO spreadsheet writes, NO website writes.
 *
 * Run bhSchemaAwareImportPreview().
 * It reads MASTER SHEET and the authenticated staging export, then logs a
 * proposed action plan. It does not import records or change sheet tabs.
 *
 * This first pass intentionally compares activity IDs and basic field
 * availability only. Related organiser/venue/session writes are not planned
 * until their schemas and relations are explicitly audited.
 */
const BH_SCHEMA_PREVIEW = {
  masterTab: 'MASTER SHEET',
  exportUrl: 'https://staging.bubbahub.co.uk/api/sheets-sync-export.php',
  pageSize: 100,
  maxPages: 200,
  sampleLimit: 25,
  stagingTestIds: new Set(['900001','900002','900003','900004','900005','900006','900007','900008','900009','999999'])
};

function bhSchemaAwareImportPreview() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (!ss) throw new Error('Open from Bubba Hub Listings spreadsheet');
  const sheet = ss.getSheetByName(BH_SCHEMA_PREVIEW.masterTab);
  if (!sheet) throw new Error('MASTER SHEET tab missing');
  const lastCol = sheet.getLastColumn();
  if (lastCol < 1) throw new Error('MASTER SHEET is empty');
  const headers = sheet.getRange(1,1,1,lastCol).getDisplayValues()[0].map(v=>String(v).trim());
  const normalized = headers.map(v=>v.toLowerCase());
  const idColumns = normalized.reduce((a,v,i)=>v==='id'?a.concat(i):a,[]);
  if (idColumns.length !== 1) throw new Error('Expected exactly one id column');
  const lastRow=sheet.getLastRow();
  const rows=lastRow>1?sheet.getRange(2,1,lastRow-1,lastCol).getDisplayValues():[];
  const sheetById=new Map(), duplicateIds=[], invalidRows=[];
  rows.forEach((row,index)=>{
    const id=String(row[idColumns[0]]||'').trim();
    if (!/^[1-9]\\d*$/.test(id)) {invalidRows.push({row:index+2,id:id});return;}
    if (sheetById.has(id)) duplicateIds.push({id:id,firstRow:sheetById.get(id),duplicateRow:index+2});
    else sheetById.set(id,{row:index+2,values:row});
  });
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token) throw new Error('BH_SYNC_TOKEN missing from Script Properties');
  const websiteById=new Map();
  let after=0,pages=0,complete=false,availableFields=[];
  while (!complete && pages < BH_SCHEMA_PREVIEW.maxPages) {
    const response=UrlFetchApp.fetch(BH_SCHEMA_PREVIEW.exportUrl,{
      method:'post',contentType:'application/json',
      headers:{Authorization:'Bearer '+token},
      payload:JSON.stringify({action:'export',after_id:after,limit:BH_SCHEMA_PREVIEW.pageSize}),
      muteHttpExceptions:true,followRedirects:false
    });
    if(response.getResponseCode()!==200) throw new Error('Staging export HTTP '+response.getResponseCode()+'; no writes performed');
    const data=JSON.parse(response.getContentText());
    if(data.ok!==true||data.mode!=='read_only_export'||!Array.isArray(data.records)||typeof data.has_more!=='boolean'||!Number.isSafeInteger(data.next_after_id))
      throw new Error('Invalid export response; no writes performed');
    availableFields=data.fields||availableFields;
    data.records.forEach(record=>{
      const id=String(record.id||'');
      if(!/^[1-9]\\d*$/.test(id)||websiteById.has(id)) throw new Error('Invalid or duplicate staging ID '+id);
      websiteById.set(id,record);
    });
    pages++;
    if(data.has_more&&data.next_after_id<=after) throw new Error('Pagination did not advance');
    after=data.next_after_id;
    complete=!data.has_more;
  }
  if(!complete) throw new Error('Safety stop: pagination incomplete; no writes performed');

  const onlySheet=[],matched=[],onlyWebsite=[],excludedTestRecords=[];
  sheetById.forEach((source,id)=>{
    if(websiteById.has(id)) matched.push({id:id,sheetRow:source.row,websiteTitle:String(websiteById.get(id).title||'')});
    else onlySheet.push({id:id,sheetRow:source.row});
  });
  websiteById.forEach((record,id)=>{
    if(BH_SCHEMA_PREVIEW.stagingTestIds.has(id)) {
      excludedTestRecords.push({id:id,title:String(record.title||''),status:String(record.status||'')});
      return;
    }
    if(!sheetById.has(id)) onlyWebsite.push({id:id,title:String(record.title||''),status:String(record.status||'')});
  });
  const sheetHeaderSet=new Set(normalized);
  const mapping=[
    {sheet:'id',website:'id',status:sheetHeaderSet.has('id')&&availableFields.includes('id')?'available':'missing'},
    {sheet:'title',website:'title',status:sheetHeaderSet.has('title')&&availableFields.includes('title')?'available':'missing'},
    {sheet:'description',website:'description',status:sheetHeaderSet.has('description')&&availableFields.includes('description')?'available':'missing'},
    {sheet:'category',website:'category',status:sheetHeaderSet.has('category')&&availableFields.includes('category')?'available':'missing'},
    {sheet:'status',website:'status',status:sheetHeaderSet.has('status')&&availableFields.includes('status')?'available':'missing'},
    {sheet:'featured',website:'featured',status:sheetHeaderSet.has('featured')&&availableFields.includes('featured')?'available':'missing'},
    {sheet:'organisation_name',website:'organisers relation',status:'requires organiser schema/identity audit'},
    {sheet:'venue_*',website:'venues and activity_venues',status:'requires venue relation audit'},
    {sheet:'sessions',website:'sessions/activity_schedules',status:'requires schedule relation audit'},
    {sheet:'image_1..3',website:'activity_images',status:'requires image relation audit'},
    {sheet:'tags',website:'tags/activity_tags',status:'requires tag relation audit'}
  ];
  const summary={
    mode:'SCHEMA_AWARE_IMPORT_PREVIEW_READ_ONLY',
    spreadsheet:ss.getName(),tab:BH_SCHEMA_PREVIEW.masterTab,
    sheetRows:rows.length,uniqueValidSheetIds:sheetById.size,
    stagingActivityCount:websiteById.size,matchedIds:matched.length,
    sheetOnlyCount:onlySheet.length,nonTestWebsiteOnlyCount:onlyWebsite.length,
    duplicateSheetIds:duplicateIds.length,invalidSheetRows:invalidRows.length,
    exportPages:pages,websiteExportFields:availableFields,
    proposedActions:{
      createWebsiteRecords:onlySheet.length,
      updateExistingRecords:0,
      noOpMatchedRecords:matched.length,
      conflicts:duplicateIds.length+invalidRows.length,
      websiteOnlyRecordsForReview:onlyWebsite.length,
      excludedKnownStagingTestRecords:excludedTestRecords.length
    },
    fieldMapping: mapping,
    samples:{
      sheetOnly:onlySheet.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),
      websiteOnly:onlyWebsite.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),
      matched:matched.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),
      duplicateSheetIds:duplicateIds.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),
      invalidSheetRows:invalidRows.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),
      excludedTestRecords:excludedTestRecords
    },
    safety:{
      writesPerformed:false,
      masterSheetModified:false,
      liveWebsiteTouched:false,
      stagingDatabaseModified:false,
      relatedTablesWritten:false,
      note:'Action counts are ID-based planning only. No record is inserted or updated. Related tables require separate schema audit before write planning.'
    }
  };
  Logger.log(JSON.stringify(summary,null,2));
  return summary;
}
