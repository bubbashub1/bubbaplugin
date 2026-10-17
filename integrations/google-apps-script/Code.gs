/**
 * Bubba Hub Master Spreadsheet — safe Apps Script sync scaffold.
 * Paste into Extensions > Apps Script in your master spreadsheet.
 * Initial mode: READ-ONLY PREVIEW. No automatic edits or deletions.
 * Set Script Properties: BH_SYNC_URL (staging HTTPS endpoint), BH_SYNC_TOKEN.
 */
const BH = Object.freeze({ master: 'Activities', audit: 'Sync Log', conflicts: 'Conflicts',
  columns: ['id','title','category','description','age_range','price_from','booking_url','status','organisation_name','venue_name','city','region','postcode','sessions'] });
function bhSetupMaster() {
  const book = SpreadsheetApp.getActiveSpreadsheet();
  [BH.master,BH.audit,BH.conflicts].forEach(name => {if (!book.getSheetByName(name)) book.insertSheet(name);});
  const sheet=book.getSheetByName(BH.master);
  if (sheet.getLastRow()===0) sheet.appendRow(BH.columns);
  sheet.setFrozenRows(1);
  const log=book.getSheetByName(BH.audit);
  if (log.getLastRow()===0) log.appendRow(['Timestamp','Action','Status','Details']);
  const conflicts=book.getSheetByName(BH.conflicts);
  if (conflicts.getLastRow()===0) conflicts.appendRow(['Timestamp','Activity ID','Field','Sheet value','Website value','Status']);
  bhLog_('setup','ok','Master tabs prepared; existing rows untouched');
}
function bhPreviewSync() {
  const book=SpreadsheetApp.getActiveSpreadsheet();
  const sheet=book.getSheetByName(BH.master);
  if (!sheet) throw new Error('Run bhSetupMaster first');
  const values=sheet.getDataRange().getValues();
  const headers=(values.shift()||[]).map(v=>String(v).trim());
  if (!headers.includes('id')) throw new Error('Missing id column');
  const ids=new Set(), duplicates=[], rows=[];
  values.forEach((cells,index)=>{
    if(cells.every(v=>v===''||v===null))return;
    const row={};headers.forEach((name,i)=>{if(name)row[name]=cells[i] instanceof Date ? Utilities.formatDate(cells[i],Session.getScriptTimeZone(),'yyyy-MM-dd') : cells[i];});
    const id=String(row.id||'').trim();
    if(id && ids.has(id))duplicates.push({row:index+2,id});
    if(id)ids.add(id);
    rows.push({sheet_row:index+2,values:row});
  });
  if(duplicates.length) {bhLog_('preview','blocked','Duplicate activity IDs: '+JSON.stringify(duplicates));throw new Error('Duplicate IDs found: '+JSON.stringify(duplicates));}
  const props=PropertiesService.getScriptProperties();
  const url=props.getProperty('BH_SYNC_URL'), token=props.getProperty('BH_SYNC_TOKEN');
  if(!url||!token)throw new Error('Set BH_SYNC_URL and BH_SYNC_TOKEN in Script Properties first');
  if(!/^https:\/\//.test(url))throw new Error('Only HTTPS sync endpoints are permitted');
  const lock=LockService.getScriptLock();
  if(!lock.tryLock(1000))throw new Error('Another sync preview is running');
  try {
    const response=UrlFetchApp.fetch(url,{
      method:'post',contentType:'application/json',
      headers:{'Authorization':'Bearer '+token},
      payload:JSON.stringify({action:'preview',rows}),
      muteHttpExceptions:true,followRedirects:false
    });
    const status=response.getResponseCode();
    if(status!==200)throw new Error('Sync preview API returned HTTP '+status);
    const result=JSON.parse(response.getContentText());
    if(!result.ok)throw new Error(result.error||'Preview rejected');
    bhLog_('preview','ok',JSON.stringify(result.summary||{}));
    return result;
  } finally {lock.releaseLock();}
}
function bhLog_(action,status,details){
  const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName(BH.audit);
  if(sheet)sheet.appendRow([new Date(),action,status,String(details).slice(0,2000)]);
}
/**
 * Install a six-hour preview trigger. Safe to rerun: removes only triggers
 * for this script's bhPreviewSync function to prevent duplicate executions.
 * Does not enable automatic database or spreadsheet writes.
 */
function bhInstallSixHourTrigger() {
  ScriptApp.getProjectTriggers().filter(t=>t.getHandlerFunction()==='bhPreviewSync')
    .forEach(t=>ScriptApp.deleteTrigger(t));
  ScriptApp.newTrigger('bhPreviewSync').timeBased().everyHours(6).create();
  bhLog_('trigger','ok','Read-only preview scheduled every 6 hours');
}
function onOpen(){
  SpreadsheetApp.getUi().createMenu('Bubba Hub')
    .addItem('Set up master tabs','bhSetupMaster')
    .addItem('Preview sync (no changes)','bhPreviewSync')
    .addItem('Schedule preview every 6 hours','bhInstallSixHourTrigger')
    .addToUi();
}
