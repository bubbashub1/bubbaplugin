/**
 * Bubba Hub Phase 2: read-only spreadsheet-to-staging reconciliation.
 * Paste this file into the existing spreadsheet-bound Apps Script project.
 * Run bhPhase2Preview(); NO SHEET WRITES, NO WEBSITE WRITES.
 * Reads MASTER SHEET ID column and paginated staging activity export.
 * All results go to the Apps Script execution log, not spreadsheet tabs.
 */
const BH_PHASE2 = Object.freeze({
  masterTab: 'MASTER SHEET',
  exportUrl: 'https://staging.bubbahub.co.uk/api/sheets-sync-export.php',
  pageSize: 100,
  maxPages: 200,
  sampleLimit: 20
});

function bhPhase2Preview() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (!ss) throw new Error('Open this script from the Bubba Hub spreadsheet.');
  const sheet = ss.getSheetByName(BH_PHASE2.masterTab);
  if (!sheet) throw new Error('MASTER SHEET tab not found');
  const lastCol = sheet.getLastColumn();
  const headers = sheet.getRange(1,1,1,lastCol).getDisplayValues()[0].map(v=>String(v).trim().toLowerCase());
  const idCols = headers.reduce((a,v,i)=>v==='id' ? a.concat(i+1):a,[]);
  if (idCols.length!==1) throw new Error('Expected exactly one id column in MASTER SHEET');
  const lastRow = sheet.getLastRow();
  const values = lastRow>1 ? sheet.getRange(2,idCols[0],lastRow-1,1).getDisplayValues() : [];
  const sheetIds = new Map(), duplicates = [], invalid = [];
  values.forEach((v,i)=>{
    const id=String(v[0]).trim(), row=i+2;
    if (!/^[1-9]\d*$/.test(id)) {invalid.push({row,id});return;}
    if (sheetIds.has(id)) duplicates.push({id,firstRow:sheetIds.get(id),row});
    else sheetIds.set(id,row);
  });
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if (!token) throw new Error('BH_SYNC_TOKEN missing from Script Properties');
  const siteIds=new Map();
  let after=0, pages=0, done=false;
  while (!done && pages<BH_PHASE2.maxPages) {
    const res=UrlFetchApp.fetch(BH_PHASE2.exportUrl,{
      method:'post',contentType:'application/json',
      headers:{Authorization:'Bearer '+token},
      payload:JSON.stringify({action:'export',after_id:after,limit:BH_PHASE2.pageSize}),
      muteHttpExceptions:true,followRedirects:false
    });
    if (res.getResponseCode()!==200) throw new Error('Staging export HTTP '+res.getResponseCode()+' after '+after);
    const data=JSON.parse(res.getContentText());
    if (data.ok!==true || data.mode!=='read_only_export' || !Array.isArray(data.records) ||
        typeof data.has_more!=='boolean' || !Number.isSafeInteger(data.next_after_id))
      throw new Error('Invalid staging export pagination response');
    data.records.forEach(record=>{
      const id=String(record.id);
      if (!/^[1-9]\d*$/.test(id) || siteIds.has(id)) throw new Error('Invalid or duplicate staging ID '+id);
      siteIds.set(id,record);
    });
    pages++;
    if (data.has_more && data.next_after_id<=after) throw new Error('Staging pagination did not advance');
    after=data.next_after_id;
    done=!data.has_more;
  }
  if (!done) throw new Error('Safety stop: max export pages reached; results incomplete');
  const onlySheet=[],onlySite=[],matched=[];
  sheetIds.forEach((row,id)=>{
    if (siteIds.has(id)) matched.push(id);
    else onlySheet.push({id,row});
  });
  siteIds.forEach((record,id)=>{if(!sheetIds.has(id)) onlySite.push({id,title:String(record.title||''),status:String(record.status||'')});});
  const summary={
    mode:'READ_ONLY_PHASE2',spreadsheet:ss.getName(),sheetTab:BH_PHASE2.masterTab,
    sheetDataRows:values.length,validUniqueSheetIds:sheetIds.size,
    stagingListings:siteIds.size,matchedIds:matched.length,
    onlyInSheet:onlySheet.length,onlyOnStaging:onlySite.length,
    duplicateSheetIds:duplicates.length,invalidOrBlankSheetIds:invalid.length,
    exportPages:pages,
    sampleOnlyInSheet:onlySheet.slice(0,BH_PHASE2.sampleLimit),
    sampleOnlyOnStaging:onlySite.slice(0,BH_PHASE2.sampleLimit),
    sampleDuplicates:duplicates.slice(0,BH_PHASE2.sampleLimit),
    sampleInvalid:invalid.slice(0,BH_PHASE2.sampleLimit),
    warning:'ID presence only: does not compare field values, organisers, venues, sessions, or establish sync baselines.',
    writesPerformed:false
  };
  Logger.log(JSON.stringify(summary,null,2));
  return summary;
}
