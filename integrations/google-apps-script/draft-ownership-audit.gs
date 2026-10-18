/** Run manually. Audits staging only; does not edit any sheet or database row. */
function bhAuditDraftOwnership(){
 const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
 if(!token)throw new Error('BH_SYNC_TOKEN Script Property is missing');
 const response=UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-draft-ownership-audit.php',{
  method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},
  payload:JSON.stringify({action:'draft_ownership_audit'}),muteHttpExceptions:true,followRedirects:false
 });
 if(response.getResponseCode()!==200)throw new Error('Draft ownership audit HTTP '+response.getResponseCode()+': '+response.getContentText().slice(0,300));
 const data=JSON.parse(response.getContentText());
 if(data.ok!==true||data.mode!=='DRAFT_OWNERSHIP_AUDIT_READ_ONLY'||data.readyForWrites!==false||data.writesPerformed!==false)throw new Error('Unexpected response; stopping');
 Logger.log(JSON.stringify(data,null,2));
 return data;
}
