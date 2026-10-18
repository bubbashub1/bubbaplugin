/**
 * Bubba Hub — five-record staging import PLAN (read-only).
 * Run bhFiveRecordImportPlan(). No writes, no triggers.
 * Uses staging export for ID and slug collision checks. Organiser/venue
 * matching is deliberately UNVERIFIED until relation exports exist.
 */
function bhFiveRecordImportPlan() {
  const ss=SpreadsheetApp.getActiveSpreadsheet();
  const sheet=ss&&ss.getSheetByName('MASTER SHEET');
  if(!sheet||sheet.getLastRow()<6) throw new Error('MASTER SHEET requires at least five records');
  const cols=sheet.getLastColumn();
  const headers=sheet.getRange(1,1,1,cols).getDisplayValues()[0].map(x=>String(x).trim().toLowerCase());
  if(headers.filter(x=>x==='id').length!==1) throw new Error('Expected exactly one ID column');
  const rows=sheet.getRange(2,1,5,cols).getDisplayValues();
  const val=(r,key)=>{const n=headers.indexOf(key);return n<0?'':String(r[n]||'').trim();};
  const token=PropertiesService.getScriptProperties().getProperty('BH_SYNC_TOKEN');
  if(!token) throw new Error('BH_SYNC_TOKEN missing from Script Properties');
  const websiteById=new Map(),usedSlugs=new Map();
  let after=0,pages=0,more=true;
  while(more&&pages<200){
    const response=UrlFetchApp.fetch('https://staging.bubbahub.co.uk/api/sheets-sync-export.php',{
      method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+token},
      payload:JSON.stringify({action:'export',after_id:after,limit:100}),
      muteHttpExceptions:true,followRedirects:false
    });
    if(response.getResponseCode()!==200)throw new Error('Staging export HTTP '+response.getResponseCode());
    const body=JSON.parse(response.getContentText());
    if(body.ok!==true||body.mode!=='read_only_export'||!Array.isArray(body.records)||typeof body.has_more!=='boolean'||!Number.isSafeInteger(body.next_after_id))
      throw new Error('Unexpected staging export response');
    body.records.forEach(r=>{
      const id=String(r.id==null?'':r.id).trim();
      if(!/^[1-9][0-9]*$/.test(id)||websiteById.has(id))throw new Error('Invalid/duplicate staging ID '+id);
      websiteById.set(id,r);
      const slug=String(r.slug||'').trim().toLowerCase();
      if(slug){if(!usedSlugs.has(slug))usedSlugs.set(slug,[]);usedSlugs.get(slug).push(id);}
    });
    pages++;
    if(body.has_more&&body.next_after_id<=after)throw new Error('Staging export pagination stalled');
    after=body.next_after_id;more=body.has_more;
  }
  if(more)throw new Error('Staging export incomplete; abort');
  const candidateSlugs=new Map(),seenIds=new Set();
  const plans=rows.map((r,i)=>{
    const id=val(r,'id'),title=val(r,'title'),organisation=val(r,'organisation_name'),venue=val(r,'venue_name'),sessions=val(r,'sessions');
    const issues=[],warnings=[];
    if(!/^[1-9][0-9]*$/.test(id))issues.push('invalid_id');
    if(seenIds.has(id))issues.push('duplicate_sample_id');seenIds.add(id);
    if(!title)issues.push('missing_title');
    if(websiteById.has(id))issues.push('staging_id_collision');
    const slug=title.toLowerCase().normalize('NFKD').replace(/[\\u0300-\\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    if(!slug)issues.push('empty_generated_slug');
    if(usedSlugs.has(slug))issues.push('staging_slug_collision');
    if(candidateSlugs.has(slug))issues.push('sample_slug_collision_with_row_'+candidateSlugs.get(slug));
    else candidateSlugs.set(slug,i+2);
    if(!organisation)warnings.push('organiser_missing_manual_review');
    if(/unconfirmed/i.test(organisation))warnings.push('organiser_unconfirmed_do_not_autolink');
    if(!venue)warnings.push('venue_missing_manual_review');
    if(!sessions)warnings.push('sessions_missing_do_not_invent');
    if(!val(r,'image_path')&&!val(r,'image_1'))warnings.push('image_missing');
    return {sheetRow:i+2,id,title,proposedSlug:slug,proposedActivityAction:issues.length?'BLOCK':'CREATE_CANDIDATE_ONLY',organiserMatching:'NOT_VERIFIED',venueMatching:'NOT_VERIFIED',sessionsPresent:!!sessions,issues,warnings};
  });
  const summary={
    mode:'FIVE_RECORD_IMPORT_PLAN_READ_ONLY',stagingActivityCount:websiteById.size,
    candidates:plans.filter(x=>x.proposedActivityAction==='CREATE_CANDIDATE_ONLY').length,
    blocked:plans.filter(x=>x.issues.length).length,records:plans,
    mandatoryBeforeWrites:[
      'Confirm actual organiser/venue matching using staging relation data',
      'Confirm slug-generation matches website rules and is collision-safe',
      'Resolve or explicitly defer missing organiser/venue/session data',
      'Define transaction and rollback for all affected tables',
      'Explicitly approve bounded staging-only write test'
    ],
    writesPerformed:false,masterSheetModified:false,liveWebsiteTouched:false
  };
  Logger.log(JSON.stringify(summary,null,2));return summary;
}
