/** Bubba Hub PR #216 — read-only matching. Run bhPreviewOrganiserVenueMatches(). */
function bhPreviewOrganiserVenueMatches(){
 const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
 if(!sheet||sheet.getLastRow()<6)throw new Error('Five source rows required');
 const n=sheet.getLastColumn(),headers=sheet.getRange(1,1,1,n).getDisplayValues()[0].map(x=>x.trim().toLowerCase());
 const rows=sheet.getRange(2,1,5,n).getDisplayValues();
 const value=(r,key)=>{const i=headers.indexOf(key);return i<0?'':String(r[i]||'').trim();};
 const records=rows.map(r=>({id:value(r,'id'),organisation_name:value(r,'organisation_name'),venue_name:value(r,'venue_name')}));
 if(records.some(r=>!/^[1-9][0-9]*$/.test(r.id)))throw new Error('Invalid sheet ID');
 const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
 if(!token)throw new Error('Missing BH_SYNC_TOKEN');
 const res=UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-match-preview.php',{
  method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},
  payload:JSON.stringify({action:'match_preview',records}),muteHttpExceptions:true,followRedirects:false
 });
 if(res.getResponseCode()!==200)throw new Error('Match preview HTTP '+res.getResponseCode()+': '+res.getContentText().slice(0,200));
 const out=JSON.parse(res.getContentText());
 if(out.ok!==true||out.mode!=='ORGANISER_VENUE_MATCH_PREVIEW_READ_ONLY'||out.autoLinkAllowed!==false||out.writesPerformed!==false)throw new Error('Unexpected response');
 Logger.log(JSON.stringify(out,null,2));return out;
}
