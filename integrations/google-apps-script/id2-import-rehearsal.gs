/** Read-only rehearsal for sheet ID 2. Never writes to MASTER SHEET or website. */
function bhRehearseCornwallLibrariesImport(){
 const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
 if(!sheet)throw new Error('MASTER SHEET missing');
 const headers=sheet.getRange(1,1,1,sheet.getLastColumn()).getDisplayValues()[0].map(s=>s.trim().toLowerCase());
 const row=sheet.getRange(2,1,1,sheet.getLastColumn()).getDisplayValues()[0];
 const value=k=>{const i=headers.indexOf(k);if(i<0)throw new Error('Missing column '+k);return String(row[i]||'').trim();};
 const record={id:value('id'),title:value('title'),slug:'cornwall-libraries'};
 if(record.id!=='2'||record.title!=='Cornwall Libraries')throw new Error('Source row has changed; stop');
 const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
 if(!token)throw new Error('BH_SYNC_TOKEN missing');
 const response=UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-id2-import-rehearsal.php',{
  method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},
  payload:JSON.stringify({action:'rehearse_id_2',record}),muteHttpExceptions:true,followRedirects:false
 });
 if(response.getResponseCode()!==200)throw new Error('ID2 rehearsal HTTP '+response.getResponseCode()+': '+response.getContentText().slice(0,300));
 const data=JSON.parse(response.getContentText());
 if(data.ok!==true||data.mode!=='ID2_DRAFT_INSERT_REHEARSAL_READ_ONLY'||data.readyForWrites!==false||data.writesPerformed!==false)throw new Error('Unexpected response');
 Logger.log(JSON.stringify(data,null,2));return data;
}
