/**
 * Bubba Hub master-sheet batch bridge — staging only.
 * Reads the existing MASTER SHEET tab and submits allowlisted fields in batches.
 * This prepares batches for the admin dashboard; it does NOT update activities.
 */
const BH_MASTER_BATCH = {
  tab: 'MASTER SHEET',
  endpoint: 'https://staging.bubbahub.co.uk/api/sheets-sync-admin-override-stage.php',
  batchSize: 100,
  allowed: ['title','description','age_range','price_from','booking_url']
};
function bhPrepareMasterSheetBatchesForAdmin() { return bhPrepareMasterSheetBatchRun_(5); }\n\nfunction bhPrepareAllMasterSheetBatchesForAdmin() { return bhPrepareMasterSheetBatchRun_(null); }\n\nfunction bhPrepareMasterSheetBatchRun_(maxRows) {\n  const ss=SpreadsheetApp.getActiveSpreadsheet();
  if(!ss) throw new Error('Open this function from the Bubba Hub Listings spreadsheet.');
  const sheet=ss.getSheetByName(BH_MASTER_BATCH.tab);
  if(!sheet) throw new Error('MASTER SHEET tab not found.');
  const lastRow=sheet.getLastRow(),lastCol=sheet.getLastColumn();
  if(lastRow<2||lastCol<1) throw new Error('MASTER SHEET has no activity rows.');
  const headers=sheet.getRange(1,1,1,lastCol).getDisplayValues()[0].map(v=>String(v).trim().toLowerCase().replace(/[\s-]+/g,'_'));
  const idIndexes=headers.map((h,i)=>h==='id'||h==='activity_id'?i:-1).filter(i=>i>=0);
  if(idIndexes.length!==1) throw new Error('Expected exactly one id or activity_id column.');
  const col={};['title','description','age_range','price_from','booking_url'].forEach(name=>{const i=headers.indexOf(name);if(i>=0)col[name]=i;});
  if(col.title===undefined)throw new Error('A title column is required.');
  const data=sheet.getRange(2,1,lastRow-1,lastCol).getDisplayValues();
  const seen=new Set(),rows=[],problems=[];
  data.forEach((values,i)=>{
    const rawId=String(values[idIndexes[0]]||'').trim();
    if(rawId==='')return;
    if(!/^[1-9]\d*$/.test(rawId)){problems.push('Invalid activity ID at sheet row '+(i+2));return;}
    if(seen.has(rawId)){problems.push('Duplicate activity ID '+rawId+' at sheet row '+(i+2));return;}
    seen.add(rawId);
    const row={activity_id:rawId};
    BH_MASTER_BATCH.allowed.forEach(field=>{if(col[field]!==undefined)row[field]=String(values[col[field]]??'').trim();});
    rows.push(row);
  });
  if(problems.length)throw new Error('No batches sent because the sheet has issues: '+problems.slice(0,20).join('; '));
  if(!rows.length)throw new Error('No rows with activity IDs were found.');
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN')||PropertiesService.getScriptProperties().getProperty('BH_SHEETS_SYNC_TOKEN');
  if(!token||token.length<32)throw new Error('Set BH_SYNC_TOKEN in Script Properties.');
  const rowsToSend=maxRows===null?rows:rows.slice(0,maxRows);\n  if(!rowsToSend.length)throw new Error('No eligible rows to stage.');\n  const results=[];\n  for(let start=0;start<rowsToSend.length;start+=BH_MASTER_BATCH.batchSize){\n    const batch=rowsToSend.slice(start,start+BH_MASTER_BATCH.batchSize);
    const response=UrlFetchApp.fetch(BH_MASTER_BATCH.endpoint,{method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},payload:JSON.stringify({spreadsheet:ss.getName(),tab:sheet.getName(),rows:batch}),muteHttpExceptions:true,followRedirects:false});
    const body=response.getContentText();let parsed;try{parsed=JSON.parse(body);}catch(e){throw new Error('Invalid response for batch '+(results.length+1)+'; no activity writes performed.');}
    if(response.getResponseCode()!==200||parsed.ok!==true||parsed.mode!=='prepared_for_admin_review'){throw new Error('Batch '+(results.length+1)+' failed (HTTP '+response.getResponseCode()+'): '+(parsed.error||'unexpected response')+'. Previous batches, if any, are staged for admin review only; no activities were updated.');}
    results.push({batch_id:parsed.batch_id,rows_received:parsed.rows_received});
  }
  const summary={ok:true,mode:'prepared_for_admin_review',spreadsheet:ss.getName(),tab:sheet.getName(),total_rows:rowsToSend.length,batches:results,activity_writes_performed:0};
  Logger.log(JSON.stringify(summary,null,2));return summary;
}
