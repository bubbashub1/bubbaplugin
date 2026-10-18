/**
 * Bubba Hub Phase 2: five-row import dry-run (READ ONLY).
 * Run bhFiveListingDryRun(). Does not call a write endpoint.
 * This is a source validation preview, NOT an approved SQL import plan.
 */
function bhFiveListingDryRun() {
  const ss=SpreadsheetApp.getActiveSpreadsheet();
  const sheet=ss && ss.getSheetByName('MASTER SHEET');
  if(!sheet) throw new Error('MASTER SHEET not found');
  const cols=sheet.getLastColumn();
  if(sheet.getLastRow()<6) throw new Error('Need at least five data rows');
  const headers=sheet.getRange(1,1,1,cols).getDisplayValues()[0].map(x=>String(x).trim().toLowerCase());
  if(headers.filter(x=>x==='id').length!==1) throw new Error('Expected one ID header');
  const data=sheet.getRange(2,1,5,cols).getDisplayValues();
  const get=(row,key)=>{const i=headers.indexOf(key);return i<0?'':String(row[i]||'').trim();};
  const seen=new Set();
  const report=data.map((row,i)=>{
    const id=get(row,'id'),title=get(row,'title'),slug=get(row,'slug');
    const errors=[],warnings=[];
    if(!/^[1-9][0-9]*$/.test(id)) errors.push('invalid ID');
    if(seen.has(id)) errors.push('duplicate ID within sample');
    seen.add(id);
    if(!title) errors.push('missing title');
    if(!slug) warnings.push('slug must be generated and checked for collision');
    if(!get(row,'organisation_name')) warnings.push('missing organiser name');
    if(!get(row,'venue_name')) warnings.push('missing venue name');
    if(!get(row,'sessions')) warnings.push('no sessions supplied');
    const ageMin=get(row,'age_min_months'),ageMax=get(row,'age_max_months');
    if(ageMin&&!/^[0-9]+$/.test(ageMin)) errors.push('invalid age_min_months');
    if(ageMax&&!/^[0-9]+$/.test(ageMax)) errors.push('invalid age_max_months');
    if(ageMin&&ageMax&&/^[0-9]+$/.test(ageMin)&&/^[0-9]+$/.test(ageMax)&&Number(ageMin)>Number(ageMax)) errors.push('min age greater than max age');
    const lat=get(row,'latitude'),lon=get(row,'longitude');
    if(lat&&(!Number.isFinite(Number(lat))||Math.abs(Number(lat))>90)) errors.push('invalid latitude');
    if(lon&&(!Number.isFinite(Number(lon))||Math.abs(Number(lon))>180)) errors.push('invalid longitude');
    return {sheetRow:i+2,id,title,organisationName:get(row,'organisation_name'),venueName:get(row,'venue_name'),hasSessions:!!get(row,'sessions'),hasImage:!!(get(row,'image_path')||get(row,'image_1')),errors,warnings,action:'DRY_RUN_ONLY'};
  });
  const result={mode:'FIVE_LISTING_SOURCE_VALIDATION_READ_ONLY',source:'MASTER SHEET',rowsInspected:report.length,invalidRows:report.filter(r=>r.errors.length).length,rows:report,blockers:['Staging has no bh_activity_schedules/bh_activity_images/bh_activity_tags tables','No foreign keys declared: relation integrity must be validated explicitly','Organiser and venue deduplication strategy unverified','No SQL write plan or staging insertion approved'],writesPerformed:false};
  Logger.log(JSON.stringify(result,null,2));return result;
}
