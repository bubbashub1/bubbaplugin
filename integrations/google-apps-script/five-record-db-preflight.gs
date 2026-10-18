/** Bubba Hub Phase 2 — read-only staging database preflight for IDs 2–6. */
function bhFiveRecordDatabasePreflight(){
 const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
 if(!sheet||sheet.getLastRow()<6)throw new Error('Missing five source records');
 const count=sheet.getLastColumn(),headers=sheet.getRange(1,1,1,count).getDisplayValues()[0].map(x=>String(x).trim().toLowerCase());
 const get=(row,key)=>{const i=headers.indexOf(key);return i<0?'':String(row[i]||'').trim();};
 const rows=sheet.getRange(2,1,5,count).getDisplayValues();
 const records=rows.map(r=>{
  const title=get(r,'title'),id=get(r,'id');
  const slug=title.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
  return {id,title,slug};
 });
 if(records.map(r=>r.id).join(',')!=='2,3,4,5,6')throw new Error('Unexpected first five IDs');
 const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
 if(!token)throw new Error('BH_SYNC_TOKEN missing');
 const response=UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-five-record-preflight.php',{
  method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},
  payload:JSON.stringify({action:'five_record_preflight',records}),muteHttpExceptions:true,followRedirects:false
 });
 if(response.getResponseCode()!==200)throw new Error('Preflight HTTP '+response.getResponseCode()+': '+response.getContentText().slice(0,300));
 const result=JSON.parse(response.getContentText());
 if(result.ok!==true||result.mode!=='FIVE_RECORD_DB_PREFLIGHT_READ_ONLY'||result.readyForWrites!==false||result.writesPerformed!==false)throw new Error('Unexpected preflight response');
 Logger.log(JSON.stringify(result,null,2));return result;
}
