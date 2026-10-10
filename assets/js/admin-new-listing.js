document.addEventListener('DOMContentLoaded',async()=>{
const form=document.getElementById('newListingForm');if(!form)return;
const status=document.getElementById('newListingStatus'),button=form.querySelector('button[type="submit"]'),id=new URLSearchParams(location.search).get('id');let original=null;
const redirect=()=>location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname+location.search));
button.disabled=true;
try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(auth.status===401){redirect();return;}const check=await auth.json();if(!auth.ok||!check.ok)throw Error('Could not verify admin access.');
const presets={'0-6':[0,6,'0–6 months'],'6-12':[6,12,'6–12 months'],'0-12':[0,12,'0–12 months'],'12-48':[12,48,'1–4 years'],'48+':[48,null,'4+ years']};
const preset=form.elements.namedItem('age_preset'),free=form.elements.namedItem('price_free'),price=form.elements.namedItem('price_from'),unit=form.elements.namedItem('pricing_unit');
const blockWeeks=form.elements.namedItem('block_length_weeks'),blockField=document.getElementById('blockWeeksField');const syncBlockWeeks=()=>{const show=unit.value==='per_block'&&!free.checked;blockField.hidden=!show;blockWeeks.required=show;blockWeeks.disabled=!show;if(!show)blockWeeks.value='';};unit.addEventListener('change',syncBlockWeeks);free.addEventListener('change',syncBlockWeeks);const applyPreset=()=>{const keys=[...preset.selectedOptions].map(o=>o.value).filter(v=>presets[v]);if(!keys.length)return;const values=keys.map(k=>presets[k]);form.elements.namedItem('age_min_months').value=Math.min(...values.map(v=>v[0]));form.elements.namedItem('age_max_months').value=values.some(v=>v[1]===null)?'':Math.max(...values.map(v=>v[1]));form.elements.namedItem('age_range').value=values.map(v=>v[2]).join(', ');};
preset.addEventListener('change',applyPreset);
free.addEventListener('change',()=>{price.disabled=free.checked;if(free.checked)price.value='0';});
if(!id){syncBlockWeeks();[...preset.options].forEach(o=>o.selected=o.value==='0-12');applyPreset();}
if(id){status.textContent='Loading listing…';const r=await fetch('/api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listing.');original=d.data;for(const el of form.elements){if(el.name&&Object.hasOwn(original,el.name))el.value=original[el.name]??'';}syncBlockWeeks();const lo=original.age_min_months,hi=original.age_max_months;

const existing=Object.entries(presets).find(([,v])=>lo!==null&&lo!==undefined&&lo!==''&&Number(lo)===v[0]&&((hi===null||hi===undefined||hi==='')?v[1]===null:Number(hi)===v[1]));
const savedLabels=String(original.age_range||'').split(',').map(s=>s.trim());const matches=Object.entries(presets).filter(([,v])=>savedLabels.includes(v[2])).map(([k])=>k);[...preset.options].forEach(o=>o.selected=matches.length?matches.includes(o.value):o.value===(existing?.[0]||''));
if(original.price_free!==undefined)free.checked=String(original.price_free)==='1';
if(original.pricing_unit)unit.value=original.pricing_unit;
else if(String(original.price_per_family)==='1')unit.value='per_family';
else if(String(original.price_per_session)==='1')unit.value='per_session';
price.disabled=free.checked;
document.querySelector('h1').textContent='Edit listing';document.title='Edit listing · Admin · Bubba Hub';status.textContent='';}
// Reuse existing category names, allowing multiple selections without duplicate listings.
const categoryInput=form.querySelector('[name="category"]');
if(categoryInput){
 const wrapper=document.createElement('div');
 wrapper.className='bh-category-picker';
 wrapper.innerHTML='<div style="font-weight:600;margin-bottom:8px">Choose categories (multiple allowed)</div><div class="bh-category-options" style="display:flex;flex-wrap:wrap;gap:10px"></div><small>Selected categories are saved against this single activity.</small>';
 categoryInput.parentElement.appendChild(wrapper);
 categoryInput.type='hidden';
 const options=wrapper.querySelector('.bh-category-options');
 const selected=new Set(String(categoryInput.value||'').split(',').map(x=>x.trim()).filter(Boolean));
 const names=new Map();
 const decodeName=value=>{const el=document.createElement('textarea');el.innerHTML=String(value||'');return el.value;};
 const addName=name=>{name=decodeName(name).trim();if(name&&!names.has(name.toLowerCase()))names.set(name.toLowerCase(),name);};
 selected.forEach(addName);
 try{
  const response=await fetch('/api/admin-activities.php?action=categories',{credentials:'same-origin',cache:'no-store'});
  const payload=await response.json();
  if(response.ok&&payload.ok) (payload.data||[]).forEach(addName);
 }catch(error){console.warn('Categories unavailable:',error);}
 const sync=()=>{categoryInput.value=[...selected].join(', ');};
 const render=()=>{
  options.replaceChildren();
  [...names.values()].sort((a,b)=>a.localeCompare(b)).forEach(name=>{
   const label=document.createElement('label');label.style.cssText='display:inline-flex;align-items:center;gap:6px;border:1px solid #ced7c7;border-radius:18px;padding:7px 11px;cursor:pointer';
   const check=document.createElement('input');check.type='checkbox';check.checked=selected.has(name);check.addEventListener('change',()=>{if(check.checked)selected.add(name);else selected.delete(name);sync();});
   label.append(check,document.createTextNode(name));options.appendChild(label);
  });
 };
 render();sync();
 const search=document.createElement('input');search.type='search';search.placeholder='Search categories';search.setAttribute('aria-label','Search categories');wrapper.insertBefore(search,options);search.addEventListener('input',()=>{const term=search.value.trim().toLowerCase();[...options.children].forEach(label=>{label.hidden=!label.textContent.toLowerCase().includes(term);});});
 const custom=document.createElement('input');custom.type='text';custom.placeholder='Add another category and press Enter';custom.style.marginTop='10px';
 custom.addEventListener('keydown',event=>{if(event.key!=='Enter')return;event.preventDefault();const values=custom.value.split(',').map(x=>x.trim()).filter(Boolean);if(!values.length)return;values.forEach(name=>{addName(name);selected.add(names.get(name.toLowerCase()));});custom.value='';render();sync();search.dispatchEvent(new Event('input'));});
 wrapper.appendChild(custom);
 form.addEventListener('submit',event=>{if(!selected.size){event.preventDefault();status.textContent='Choose at least one category.';status.classList.add('is-error');} },true);
}
// Existing venues are searchable using the browser's built-in datalist.
const venueSelect=document.getElementById('existingVenueSelect');
const venueName=form.elements.namedItem('venue_name');
if(venueSelect){
 const venueSearch=document.createElement('input');venueSearch.type='search';venueSearch.placeholder='Filter venues by name, town or postcode';venueSearch.setAttribute('aria-label','Search existing venues');venueSelect.before(venueSearch);
 const venueMessage=document.createElement('small');venueSelect.after(venueMessage);
 let venues=[];
 const renderVenues=()=>{
  const query=venueSearch.value.trim().toLowerCase();const current=venueSelect.value;
  venueSelect.replaceChildren(new Option('Add new venue / enter manually',''));
  venues.filter(v=>[v.name,v.town,v.postcode,v.address].some(s=>String(s||'').toLowerCase().includes(query))).slice(0,200).forEach(v=>venueSelect.add(new Option([v.name,v.town,v.postcode].filter(Boolean).join(' · '),String(v.id))));
  if([...venueSelect.options].some(o=>o.value===current))venueSelect.value=current;
 };
 venueSearch.addEventListener('input',renderVenues);
 venueSelect.addEventListener('change',()=>{
  const v=venues.find(x=>String(x.id)===venueSelect.value);if(!v)return;
  const fields={venue_name:v.name,address:v.address,town:v.town,region:v.region,postcode:v.postcode};
  Object.entries(fields).forEach(([name,value])=>{const field=form.elements.namedItem(name);if(field)field.value=value||'';});
  const county=form.elements.namedItem('county');if(county&&/exeter|devon/i.test(v.region||''))county.value='Devon';
  venueMessage.textContent='✓ Existing venue details copied. You can review them before saving.';
 });
 try{
  const response=await fetch('/api/venues.php',{credentials:'same-origin',cache:'no-store'});
  const payload=await response.json();if(!response.ok||!payload.ok)throw Error(payload.error||'Could not load venues');
  venues=Array.isArray(payload.data)?payload.data:[];renderVenues();
  if(original){const current=venues.find(v=>String(v.name||'').trim().toLowerCase()===String(original.venue_name||'').trim().toLowerCase()&&String(v.postcode||'').trim().toLowerCase()===String(original.postcode||'').trim().toLowerCase());if(current)venueSelect.value=String(current.id);}
  venueMessage.textContent=venues.length+' existing venues available.';
 }catch(error){venueMessage.textContent='Venue lookup unavailable; you can still enter venue details manually.';renderVenues();}
}
button.disabled=false;
}catch(e){status.textContent=e.message;status.classList.add('is-error');return;}
// Schedule editor: preserves existing sessions and supports multiple new sessions.
const scheduleSection=document.getElementById('adminListingSchedule'),scheduleRows=document.getElementById('adminScheduleRows');
const addSession=(s={})=>{
 const row=document.createElement('div');row.className='bh-card';row.style.cssText='padding:14px;margin:12px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px';
 const fields=[['day_of_week','Day','select'],['start_time','Start time','time'],['end_time','End time','time'],['frequency','Frequency','select'],['start_date','First date (optional)','date'],['end_date','Last date (optional)','date'],['price','Session price (£)','number']];
 for(const [name,label,type] of fields){
  const wrap=document.createElement('label');wrap.textContent=label;
  let control;
  if(type==='select'){control=document.createElement('select');const options=name==='day_of_week'?[['','Choose day'],['1','Monday'],['2','Tuesday'],['3','Wednesday'],['4','Thursday'],['5','Friday'],['6','Saturday'],['7','Sunday']]:[['weekly','Weekly'],['fortnightly','Fortnightly'],['monthly','Monthly'],['once','One-off']];options.forEach(([value,text])=>control.add(new Option(text,value)));}
  else{control=document.createElement('input');control.type=type;if(type==='number'){control.min='0';control.step='0.01';}}
  control.dataset.sessionField=name;control.value=s[name]??(name==='frequency'?'weekly':'');wrap.appendChild(control);row.appendChild(wrap);
 }
 const term=document.createElement('label');const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.dataset.sessionField='term_time_only';checkbox.checked=String(s.term_time_only??'0')==='1';term.append(checkbox,document.createTextNode(' Term-time only'));row.appendChild(term);
 const remove=document.createElement('button');remove.type='button';remove.className='button button-soft';remove.textContent='Remove session';remove.onclick=()=>row.remove();row.appendChild(remove);row.dataset.venueIndex=String(s.venue_index??0);scheduleRows.appendChild(row);
};
(original?.sessions||[]).forEach(addSession);
document.getElementById('adminAddSession')?.addEventListener('click',()=>addSession());


const importButton=document.getElementById('adminIcalPreview');
const importMessage=document.getElementById('adminIcalMessage'),importResults=document.getElementById('adminIcalResults');
const previewImportedSessions=(entries,note='')=>{
 importResults.replaceChildren();
 importMessage.textContent=entries.length+' dated events found.'+(note?' '+note:'');
 const checks=[],list=document.createElement('div');
 entries.forEach(event=>{
  const label=document.createElement('label');label.style.cssText='display:flex;gap:10px;align-items:center;padding:8px 0';
  const check=document.createElement('input');check.type='checkbox';check.checked=true;checks.push([check,event]);
  label.append(check,document.createTextNode(event.start_date+' '+event.start_time+' — '+event.title));list.append(label);
 });
 importResults.append(list);
 if(!entries.length)return;
 const add=document.createElement('button');add.type='button';add.className='button button-primary';add.textContent='Add selected sessions';
 add.addEventListener('click',()=>{
  const known=new Set([...scheduleRows.children].map(row=>{const v={};row.querySelectorAll('[data-session-field]').forEach(el=>v[el.dataset.sessionField]=el.value);return [v.start_date,v.start_time,v.day_of_week].join('|');}));
  let count=0;
  checks.forEach(([check,event])=>{const key=[event.start_date,event.start_time,event.day_of_week].join('|');if(check.checked&&!known.has(key)){addSession(event);known.add(key);count++;}});
  importMessage.textContent=count+' sessions added. Review and Save listing to store them.';importResults.replaceChildren();
 });
 importResults.append(add);
};
importButton?.addEventListener('click',async()=>{
 const url=document.getElementById('adminIcalUrl').value.trim();importResults.replaceChildren();importMessage.textContent='Loading calendar…';importButton.disabled=true;
 try{
  const response=await fetch('/api/admin-ical-preview.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});
  const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Calendar could not be loaded.');
  previewImportedSessions(data.data||[],data.skipped_recurring?data.skipped_recurring+' recurrence rules require manual handling.':'');
 }catch(error){importMessage.textContent=error.message;}finally{importButton.disabled=false;}
});
document.getElementById('adminBookwhenPastePreview')?.addEventListener('click',()=>{
 const text=document.getElementById('adminBookwhenText').value,rows=text.split(/\\r?\\n/);
 const months=['january','february','march','april','may','june','july','august','september','october','november','december'];
 let month=-1,year=new Date().getFullYear(),day=null;const events=[];
 for(const raw of rows){
  const line=raw.replace(/[|]/g,' ').replace(/\\s+/g,' ').trim();if(!line)continue;
  const heading=line.match(/^(January|February|March|April|May|June|July|August|September|October|November|December)\\s*,?\\s*(\\d{4})?$/i);
  if(heading){month=months.indexOf(heading[1].toLowerCase());if(heading[2])year=Number(heading[2]);continue;}
  const match=line.match(/^(?:(\\d{1,2})\\s+(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\\s+)?(\\d{1,2})(?::(\\d{2}))?\\s*(am|pm)\\s*(?:BST|GMT)?\\s+(.+)$/i);
  if(!match||month<0)continue;
  if(match[1])day=Number(match[1]);if(!day||day<1||day>31)continue;
  const h=Number(match[2])%12+(match[4].toLowerCase()==='pm'?12:0);
  const date=new Date(Date.UTC(year,month,day));if(date.getUTCMonth()!==month)continue;
  const title=match[5].replace(/^\\[Button:\\s*/,'').replace(/\\]$/,'').trim();if(!title)continue;
  events.push({title,day_of_week:((date.getUTCDay()+6)%7)+1,start_time:String(h).padStart(2,'0')+':'+String(Number(match[3]||0)).padStart(2,'0'),end_time:'',start_date:date.toISOString().slice(0,10),end_date:date.toISOString().slice(0,10),frequency:'once'});
  if(events.length>=150)break;
 }
 previewImportedSessions(events,'Pasted timetable preview; confirm dates and course durations before saving.');
});
document.getElementById('adminScreenshotRead')?.addEventListener('click',async()=>{
 const input=document.getElementById('adminScheduleScreenshot'),message=document.getElementById('adminScreenshotMessage'),preview=document.getElementById('adminScreenshotPreview');
 const file=input?.files?.[0];if(!file){message.textContent='Choose a timetable image first.';return;}
 if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>5*1024*1024){message.textContent='Use a PNG, JPEG or WebP image under 5 MB.';return;}
 const url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;preview.onload=()=>URL.revokeObjectURL(url);
 if(typeof window.TextDetector!=='function'){message.textContent='Automatic screenshot reading is not supported by this browser. You can view the screenshot here and enter the sessions manually, or paste the timetable text above.';return;}
 message.textContent='Reading timetable image…';
 try{const bitmap=await createImageBitmap(file);let blocks;try{blocks=await new TextDetector().detect(bitmap)}finally{bitmap.close?.()}
 const extracted=blocks.map(b=>b.rawValue).filter(Boolean).join('\\n');if(!extracted.trim())throw Error('No readable timetable text found.');
 document.getElementById('adminBookwhenText').value=extracted;
 message.textContent='Text extracted into the timetable box above. Check the dates and times, then select Preview pasted timetable. Screenshot recognition can make mistakes.';
 }catch(error){message.textContent='Could not read this image automatically. Please enter the sessions manually or paste the timetable text.';}
});
const collectSessions=()=>[...scheduleRows.children].map(row=>{const s={venue_index:Number(row.dataset.venueIndex||0)};row.querySelectorAll('[data-session-field]').forEach(el=>s[el.dataset.sessionField]=el.type==='checkbox'?(el.checked?1:0):el.value);return s;}).filter(s=>s.day_of_week||s.start_time);
 // Seven-step admin editor, matching the class leader's new listing workflow.
const steps=[['Basics',['title','organisation_name','description']],['About',['category','age_preset','age_range','price_from','pricing_unit','block_length_weeks','price_free']],['Extra',['booking_url','email','phone','website','status']],['Schedule',[]],['Venue',['venue_name','town','county','region','postcode','address']],['Photos',['image_path']],['Review',[]]];
const stepNav=document.getElementById('adminListingSteps');
const back=document.getElementById('adminListingBack'),next=document.getElementById('adminListingNext'),save=document.getElementById('adminListingSubmit');
const labels=[...form.querySelectorAll('.admin-form-grid > label')];
let step=0;
const review=document.createElement('div');review.className='bh-review';review.hidden=true;form.querySelector('.admin-form-grid').after(review);
const renderStep=()=>{
 const current=steps[step];
 labels.forEach(label=>{const field=label.querySelector('[name]');const name=field?.name;label.hidden=step!==6&&!current[1].includes(name)&&!(step===4&&label.querySelector('#existingVenueSelect'));label.style.display=label.hidden?'none':'';if(field&&field.required){field.disabled=label.hidden;}});
 review.hidden=step!==6;if(scheduleSection)scheduleSection.hidden=step!==3;
 if(step===6){review.replaceChildren();labels.forEach(label=>{const el=label.querySelector('[name]');if(!el)return;const line=document.createElement('p');const strong=document.createElement('strong');strong.textContent=label.textContent.trim()+': ';line.append(strong,document.createTextNode(el.value||'—'));review.append(line);});}
 back.hidden=step===0;next.hidden=step===6;save.hidden=step!==6;
 [...stepNav.children].forEach((b,i)=>{b.classList.toggle('active',i===step);b.setAttribute('aria-current',i===step?'step':'false');});
};
steps.forEach(([name],i)=>{const b=document.createElement('button');b.type='button';b.className='bh-step';b.innerHTML='<span>'+(i+1)+'</span>'+name;b.addEventListener('click',()=>{step=i;renderStep();});stepNav.append(b);});
back.addEventListener('click',()=>{step=Math.max(0,step-1);renderStep();});
next.addEventListener('click',()=>{const required=labels.filter(l=>!l.hidden).map(l=>l.querySelector('[required]')).filter(Boolean);const invalid=required.find(el=>!el.value.trim());if(invalid){invalid.reportValidity();return;}step=Math.min(6,step+1);renderStep();});
renderStep();
form.addEventListener('submit',async e=>{e.preventDefault();status.textContent='Checking listing…';status.classList.remove('is-error');const requiredNames=['title','organisation_name','venue_name','town','category'];const missing=requiredNames.find(name=>!String(form.elements.namedItem(name)?.value||'').trim());if(missing){status.textContent='Please complete '+missing.replaceAll('_',' ')+' before saving.';status.classList.add('is-error');step=['title','organisation_name'].includes(missing)?0:missing==='category'?1:4;renderStep();return;}const body={...(original||{}),...Object.fromEntries(new FormData(form).entries())};delete body.age_preset;
body.price_free=free.checked?1:0;body.price_per_family=!free.checked&&unit.value==='per_family'?1:0;body.price_per_session=!free.checked&&unit.value==='per_session'?1:0;body.pricing_unit=unit.value;body.block_length_weeks=unit.value==='per_block'&&!free.checked?Number(blockWeeks.value):null;body.id=original?.id||null;body.sessions=collectSessions();body.venues=(original?.venues||[]).slice(1).map(v=>({...v,latitude:v.latitude??'',longitude:v.longitude??''}));body.accessibility=original?.accessibility||[];
body.latitude=original?.latitude??'';body.longitude=original?.longitude??'';body.sessions=body.sessions.map(s=>({...s,price:s.price??'',start_date:s.start_date??'',end_date:s.end_date??''}));
if(body.sessions.some(s=>!s.day_of_week||!s.start_time||s.end_time&&s.end_time<=s.start_time)){status.textContent='Each session needs a day and start time, and its end time must be later than its start time.';status.classList.add('is-error');step=3;renderStep();return;}button.disabled=true;status.textContent='Saving listing…';status.classList.remove('is-error');
try{const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});if(r.status===401){redirect();return;}const raw=await r.text();let d;try{d=JSON.parse(raw);}catch{throw Error('Server returned an invalid response (HTTP '+r.status+').');}if(!r.ok||!d.ok)throw Error(d.error||'Could not save listing (HTTP '+r.status+').');status.textContent='✓ Listing saved successfully.';if(!original){original={id:d.id};location.href='/admin/admin-activities-add-listing.html?id='+encodeURIComponent(d.id);}
}catch(err){status.textContent=err.message;status.classList.add('is-error');}finally{button.disabled=false;}});
});