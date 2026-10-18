/**
 * Bubba Hub Phase 2 — schema-aware read-only import preview.
 * Run bhSchemaAwareImportPreview(). No spreadsheet or website writes.
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
  const ss=SpreadsheetApp.getActiveSpreadsheet();
  if(!ss) throw new Error('Open from Bubba Hub Listings spreadsheet');
  const sheet=ss.getSheetByName(BH_SCHEMA_PREVIEW.masterTab);
  if(!sheet) throw new Error('MASTER SHEET tab missing');
  const lastCol=sheet.getLastColumn();
  const headers=sheet.getRange(1,1,1,lastCol).getDisplayValues()[0].map(v=>String(v).trim());
  const normalized=headers.map(v=>v.toLowerCase());
  const idColumns=normalized.reduce((a,v,i)=>v==='id'?a.concat(i):a,[]);
  if(idColumns.length!==1) throw new Error('Expected exactly one id column');
  const lastRow=sheet.getLastRow();
  const rows=lastRow>1?sheet.getRange(2,1,lastRow-1,lastCol).getDisplayValues():[];
  const sheetById=new Map(),duplicateIds=[],invalidRows=[];
  rows.forEach((row,index)=>{
    const id=String(row[idColumns[0]]||'').trim();
    if(!/^[1-9]\\d*$/.test(id)){invalidRows.push({row:index+2,id:id});return;}
    if(sheetById.has(id)) duplicateIds.push({id:id,firstRow:sheetById.get(id).row,duplicateRow:index+2});
    else sheetById.set(id,{row:index+2,values:row});
  });
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if(!token) throw new Error('BH_SYNC_TOKEN missing from Script Properties');
  const websiteById=new Map(); let after=0,pages=0,complete=false,availableFields=[];
  while(!complete&&pages<BH_SCHEMA_PREVIEW.maxPages){
    const response=UrlFetchApp.fetch(BH_SCHEMA_PREVIEW.exportUrl,{method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},payload:JSON.stringify({action:'export',after_id:after,limit:BH_SCHEMA_PREVIEW.pageSize}),muteHttpExceptions:true,followRedirects:false});
    if(response.getResponseCode()!==200) throw new Error('Staging export HTTP '+response.getResponseCode()+'; no writes performed');
    const data=JSON.parse(response.getContentText());
    if(data.ok!==true||data.mode!=='read_only_export'||!Array.isArray(data.records)||typeof data.has_more!=='boolean'||!Number.isSafeInteger(data.next_after_id)) throw new Error('Invalid export response; no writes performed');
    availableFields=data.fields||availableFields;
    data.records.forEach(record=>{const id=String(record.id||'');if(!/^[1-9]\\d*$/.test(id)||websiteById.has(id))throw new Error('Invalid or duplicate staging ID '+id);websiteById.set(id,record);});
    pages++; if(data.has_more&&data.next_after_id<=after)throw new Error('Pagination did not advance'); after=data.next_after_id; complete=!data.has_more;
  }
  if(!complete)throw new Error('Safety stop: pagination incomplete; no writes performed');
  const sheetOnly=[],matched=[],websiteOnly=[],excludedTests=[];
  sheetById.forEach((source,id)=>{if(websiteById.has(id))matched.push({id:id,sheetRow:source.row,websiteTitle:String(websiteById.get(id).title||'')});else sheetOnly.push({id:id,sheetRow:source.row});});
  websiteById.forEach((record,id)=>{if(BH_SCHEMA_PREVIEW.stagingTestIds.has(id)){excludedTests.push({id:id,title:String(record.title||''),status:String(record.status||'')});return;}if(!sheetById.has(id))websiteOnly.push({id:id,title:String(record.title||''),status:String(record.status||'')});});
  const hs=new Set(normalized);
  const fieldMapping=[
    {sheet:'id',website:'id',status:hs.has('id')&&availableFields.includes('id')?'available':'missing'},
    {sheet:'title',website:'title',status:hs.has('title')&&availableFields.includes('title')?'available':'missing'},
    {sheet:'description',website:'description',status:hs.has('description')&&availableFields.includes('description')?'available':'missing'},
    {sheet:'category',website:'category',status:hs.has('category')&&availableFields.includes('category')?'available':'missing'},
    {sheet:'status',website:'status',status:hs.has('status')&&availableFields.includes('status')?'available':'missing'},
    {sheet:'featured',website:'featured',status:hs.has('featured')&&availableFields.includes('featured')?'available':'missing'},
    {sheet:'organisation_name',website:'organisers relation',status:'requires organiser schema/identity audit'},
    {sheet:'venue_*',website:'venues/activity_venues',status:'requires venue relation audit'},
    {sheet:'sessions',website:'sessions/activity_schedules',status:'requires schedule relation audit'},
    {sheet:'image_1..3',website:'activity_images',status:'requires image relation audit'},
    {sheet:'tags',website:'tags/activity_tags',status:'requires tag relation audit'}
  ];
  const summary={mode:'SCHEMA_AWARE_IMPORT_PREVIEW_READ_ONLY',spreadsheet:ss.getName(),tab:BH_SCHEMA_PREVIEW.masterTab,sheetRows:rows.length,uniqueValidSheetIds:sheetById.size,stagingActivityCount:websiteById.size,matchedIds:matched.length,sheetOnlyCount:sheetOnly.length,nonTestWebsiteOnlyCount:websiteOnly.length,duplicateSheetIds:duplicateIds.length,invalidSheetRows:invalidRows.length,exportPages:pages,websiteExportFields:availableFields,proposedActions:{createWebsiteRecords:sheetOnly.length,updateExistingRecords:0,noOpMatchedRecords:matched.length,conflicts:duplicateIds.length+invalidRows.length,websiteOnlyRecordsForReview:websiteOnly.length,excludedKnownStagingTestRecords:excludedTests.length},fieldMapping:fieldMapping,samples:{sheetOnly:sheetOnly.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),websiteOnly:websiteOnly.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),matched:matched.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),duplicateSheetIds:duplicateIds.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),invalidSheetRows:invalidRows.slice(0,BH_SCHEMA_PREVIEW.sampleLimit),excludedTestRecords:excludedTests},safety:{writesPerformed:false,masterSheetModified:false,liveWebsiteTouched:false,stagingDatabaseModified:false,relatedTablesWritten:false,note:'ID-based planning only; no record is inserted or updated. Related tables require separate schema audits.'}};
  Logger.log(JSON.stringify(summary,null,2)); return summary;
}
