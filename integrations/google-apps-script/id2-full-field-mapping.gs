/** Bubba Hub PR #222 — read-only mapping inventory for Cornwall Libraries, MASTER SHEET row 2. */
function bhAuditId2FullFieldMapping(){
 const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
 if(!sheet)throw Error('MASTER SHEET missing');
 const cells=sheet.getRange(1,1,2,sheet.getLastColumn()).getDisplayValues();
 const headers=cells[0].map(v=>String(v).trim().toLowerCase()),values=cells[1];
 const get=k=>{const i=headers.indexOf(k);return i<0?'':String(values[i]||'').trim();};
 if(get('id')!=='2'||get('title')!=='Cornwall Libraries')throw Error('Unexpected ID 2 source row');
 const activity=['id','title','category','featured','description','age_range','age_min_months','age_max_months','price_from','price_per_family','price_per_session','booking_url','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know','image_path','accessibility','tags'];
 const organiser=['organisation_name','facebook_url','instagram_url','email','phone','website'];
 const venue=['venue_name','address','venue_address_line_1','venue_address_line_2','city','region','postcode','latitude','longitude','venue_phone','venue_email','venue_website','venue_notes'];
 const aliases={county:'NO_STAGING_ACTIVITY_COLUMN_REVIEW',price_free:'DERIVE_IS_FREE_WITH_EXPLICIT_APPROVAL',status:'FORCE_DRAFT_IGNORE_SOURCE_PUBLICATION',image_1:'IMAGE_STORAGE_REVIEW',image_2:'IMAGE_STORAGE_REVIEW',image_3:'IMAGE_STORAGE_REVIEW',sessions:'SESSION_JSON_REVIEW_NO_INVENTION'};
 const dbMap={activity:{},organiser:{},venue:{},review:[]};
 const add=(group,k,dest)=>{const v=get(k);if(!v)return;dbMap[group][k]={destination:dest||k,value:v};};
 activity.forEach(k=>add('activity',k,k));organiser.forEach(k=>add('organiser',k,k==='organisation_name'?'bh_organisers.organisation_name':'bh_organisers.'+k));
 venue.forEach(k=>add('venue',k,({venue_name:'bh_venues.name',address:'bh_venues.address',venue_address_line_1:'bh_venues.address_line_1',venue_address_line_2:'bh_venues.address_line_2',city:'bh_venues.town',venue_phone:'bh_venues.phone',venue_email:'bh_venues.email',venue_website:'bh_venues.website',venue_notes:'bh_venues.notes'})[k]||'bh_venues.'+k));
 Object.keys(aliases).forEach(k=>{if(get(k))dbMap.review.push({field:k,value:get(k),reason:aliases[k]});});
 const unknown=headers.filter((k,i)=>k&&String(values[i]||'').trim()&&!activity.includes(k)&&!organiser.includes(k)&&!venue.includes(k)&&!Object.hasOwn(aliases,k));
 const result={mode:'ID2_FULL_MAPPING_READ_ONLY',id:'2',title:get('title'),proposedSlug:'cornwall-libraries',proposedStatus:'draft',activity:dbMap.activity,organiser:dbMap.organiser,venue:dbMap.venue,manualReview:dbMap.review,unknownPopulatedColumns:unknown,organiserLink:'UNVERIFIED_DO_NOT_LINK_OR_CREATE',venueLink:'UNVERIFIED_DO_NOT_LINK_OR_CREATE',sessions:'NONE_SUPPLIED_DO_NOT_INVENT',schemaCompatibility:'NOT_YET_VERIFIED_FOR_ALL_COLUMNS',mappingApproved:false,readyForWrites:false,writesPerformed:false,masterSheetModified:false};
 Logger.log(JSON.stringify(result,null,2));return result;
}
