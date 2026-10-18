/** PR #221 — Public visibility + source mapping check. No writes or secrets logged. */
function bhId2FinalSafetyAudit(){
 const ss=SpreadsheetApp.getActiveSpreadsheet(),sheet=ss.getSheetByName('MASTER SHEET');
 if(!sheet)throw new Error('MASTER SHEET missing');
 const row=sheet.getRange(2,1,1,sheet.getLastColumn()).getDisplayValues()[0];
 const headers=sheet.getRange(1,1,1,sheet.getLastColumn()).getDisplayValues()[0].map(s=>s.trim().toLowerCase());
 const value=k=>{const i=headers.indexOf(k);return i<0?'':String(row[i]||'').trim();};
 if(value('id')!=='2'||value('title')!=='Cornwall Libraries')throw new Error('ID2 source changed; abort');
 const base='https://staging.bubbahub.co.uk/api/activities.php';
 const tests=[{name:'existing_draft_by_id',url:base+'?id=999999'},{name:'existing_draft_by_slug',url:base+'?slug=bubba-hub-sheet-sync-test-999999'},{name:'proposed_id_2',url:base+'?id=2'},{name:'proposed_slug',url:base+'?slug=cornwall-libraries'}];
 const results=tests.map(test=>{
  const response=UrlFetchApp.fetch(test.url,{method:'get',muteHttpExceptions:true,followRedirects:false});
  const status=response.getResponseCode();let parsed=null;
  try{parsed=JSON.parse(response.getContentText());}catch(e){}
  const entries=parsed&&Array.isArray(parsed.data)?parsed.data:null;
  const count=entries?entries.length:null;
  const safe=status===200&&parsed&&parsed.ok===true&&count===0;
  return {name:test.name,httpStatus:status,returnedActivityCount:count,passed:!!safe};
 });
 const mapping={
  sheetRow:2,id:value('id'),title:value('title'),slug:'cornwall-libraries',
  availableSourceColumns:headers.filter(Boolean),
  nonEmptySourceColumns:headers.filter((h,i)=>h&&String(row[i]||'').trim()!==''),
  missingFields:['organisation_name','venue_name','sessions'].filter(k=>!value(k)),
  approvedMappedFields:['id','title'],
  deferredFields:'All other source columns require manual mapping approval; never silently discard or invent values'
 };
 const passed=results.every(x=>x.passed);
 const output={mode:'ID2_FINAL_SAFETY_READ_ONLY',publicApiTests:results,sourceMapping:mapping,publicApiVisibilityPassed:passed,backupConfirmed:false,sourceMappingApproved:false,readyForWrites:false,writesPerformed:false};
 Logger.log(JSON.stringify(output,null,2));return output;
}
