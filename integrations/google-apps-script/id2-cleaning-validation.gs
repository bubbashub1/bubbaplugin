/** PR #223 — local read-only validation of Cornwall Libraries ID 2. */
function bhValidateId2Cleaning(){
 const sheet=SpreadsheetApp.getActiveSpreadsheet().getSheetByName('MASTER SHEET');
 if(!sheet)throw Error('MASTER SHEET missing');
 const rows=sheet.getRange(1,1,2,sheet.getLastColumn()).getDisplayValues();
 const headers=rows[0].map(x=>String(x).trim().toLowerCase()),values=rows[1];
 const get=k=>{const i=headers.indexOf(k);return i<0?'':String(values[i]||'').trim();};
 if(get('id')!=='2'||get('title')!=='Cornwall Libraries')throw Error('Unexpected source row: stop');
 const issues=[],warnings=[],proposed={};
 const html=get('description');
 const suspicious=/<(?:script|iframe|object|embed|form|style)\b|\bon\w+\s*=/i.test(html);
 if(suspicious)issues.push('description_contains_disallowed_html');
 const attrCount=(html.match(/\bdata-path-to-node\s*=|\bdata-index-in-node\s*=|\bclass\s*=|\bid\s*=/gi)||[]).length;
 proposed.description={present:!!html,characterCount:html.length,containsHtml:/<[^>]+>/.test(html),extraneousAttributeCount:attrCount,containsTable:/<table\b/i.test(html),containsTimetableText:/timetable|rhymetime|storytime/i.test(html),requiresServerSideHtmlSanitisation:true};
 if(attrCount)warnings.push('description_contains_generated_markup_attributes');
 const unwrap=s=>{const m=s.match(/^\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)$/i);return m?m[2]:s;};
 const imageFields=['image_path','image_1','image_2','image_3'];
 proposed.images=imageFields.filter(k=>get(k)).map(k=>{const raw=get(k),url=unwrap(raw);let parsed=null;try{parsed=new URL(url);}catch(e){}const valid=!!parsed&&['https:','http:'].includes(parsed.protocol)&&!!parsed.hostname; if(!valid)issues.push(k+'_invalid_url');return {field:k,markdownWrapped:raw!==url,validUrl:valid,url:valid?url:null,remoteAvailabilityNotChecked:true};});
 const booleans=['featured','price_per_family','price_per_session','price_free','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome'];
 proposed.booleans={};
 booleans.forEach(k=>{const raw=get(k);if(raw==='')return;const s=raw.toLowerCase(),valid=['true','false','1','0','yes','no'].includes(s);if(!valid)issues.push(k+'_invalid_boolean');proposed.booleans[k]={raw,normalized:valid?['true','1','yes'].includes(s):null};});
 if(proposed.booleans.drop_in_welcome&&proposed.booleans.drop_in_welcome.normalized===false&&/drop.in/i.test(html))warnings.push('drop_in_description_conflicts_with_flag');
 if(proposed.booleans.price_free&&proposed.booleans.price_free.normalized===true&&get('price_from')!==''&&Number(get('price_from'))!==0)issues.push('free_price_conflict');
 const min=Number(get('age_min_months')),max=Number(get('age_max_months'));
 if(get('age_min_months')!==''&&get('age_max_months')!==''&&(!Number.isFinite(min)||!Number.isFinite(max)||min<0||max<min))issues.push('invalid_age_month_range');
 const lat=get('latitude'),lng=get('longitude');
 if((lat&&!lng)||(!lat&&lng))issues.push('incomplete_coordinates');
 if(lat&&lng&&(!Number.isFinite(Number(lat))||!Number.isFinite(Number(lng))||Math.abs(Number(lat))>90||Math.abs(Number(lng))>180))issues.push('invalid_coordinates');
 proposed.relationships={organiserName:get('organisation_name'),venueName:get('venue_name'),organiserMatch:'NOT_VERIFIED',venueMatch:'NOT_VERIFIED',autoLinkAllowed:false};
 if(get('sessions')==='')warnings.push('sessions_blank_description_contains_possible_schedule');
 proposed.source={id:get('id'),title:get('title'),statusInSheet:get('status'),forcedImportStatus:'draft',category:get('category'),county:get('county'),city:get('city'),region:get('region'),sessionColumnEmpty:get('sessions')===''};
 const result={mode:'ID2_CLEANING_VALIDATION_READ_ONLY',source:proposed.source,description:proposed.description,images:proposed.images,booleans:proposed.booleans,relationships:proposed.relationships,issues,warnings,validationPassed:issues.length===0,identityApproved:false,fullMappingApproved:false,readyForWrites:false,writesPerformed:false,masterSheetModified:false};
 Logger.log(JSON.stringify(result,null,2));return result;
}
