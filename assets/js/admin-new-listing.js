document.addEventListener('DOMContentLoaded',async()=>{
const form=document.getElementById('newListingForm');if(!form)return;
const status=document.getElementById('newListingStatus'),button=form.querySelector('button[type="submit"]'),id=new URLSearchParams(location.search).get('id');let original=null;
const preset=form.elements.namedItem('age_preset'),free=form.elements.namedItem('price_free'),price=form.elements.namedItem('price_from'),unit=form.elements.namedItem('pricing_unit');
const redirect=()=>location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname+location.search));
button.disabled=true;
try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(auth.status===401){redirect();return;}const check=await auth.json();if(!auth.ok||!check.ok)throw Error('Could not verify admin access.');
const presets={'0-6':[0,6,'0–6 months'],'6-12':[6,12,'6–12 months'],'0-12':[0,12,'0–12 months'],'12-48':[12,48,'1–4 years'],'48+':[48,null,'4+ years']};

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
 // Website suggestions must update the picker state, not only its hidden input.
 categoryInput.addEventListener('bh:apply-categories',event=>{
  const incoming=Array.isArray(event.detail)?event.detail:[];
  incoming.forEach(value=>{const name=String(value||'').trim();if(!name)return;addName(name);selected.add(names.get(name.toLowerCase()));});
  render();sync();
 });
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
// Suggest county and region only for recognised towns; never guess unknown locations.
const townField=form.elements.namedItem('town'),countyField=form.elements.namedItem('county'),regionField=form.elements.namedItem('region');
const areaByTown={
 'torquay':['Torbay','Torbay'],'paignton':['Torbay','Torbay'],'brixham':['Torbay','Torbay'],
 'newton abbot':['Devon','Teignbridge'],'teignmouth':['Devon','Teignbridge'],'dawlish':['Devon','Teignbridge'],'bovey tracey':['Devon','Teignbridge'],'kingsteignton':['Devon','Teignbridge'],'ashburton':['Devon','Teignbridge'],
 'exeter':['Devon','Exeter'],'whimple':['Devon','East Devon'],'exmouth':['Devon','East Devon'],'sidmouth':['Devon','East Devon'],
 'totnes':['Devon','South Hams'],'kingsbridge':['Devon','South Hams'],'dartmouth':['Devon','South Hams'],'ivybridge':['Devon','South Hams'],
 'plymouth':['Plymouth','Plymouth'],'truro':['Cornwall','Cornwall'],'falmouth':['Cornwall','Cornwall'],'newquay':['Cornwall','Cornwall']
};
function suggestArea(){
 if(!townField||!countyField||!regionField)return;
 const raw=townField.value.trim().toLowerCase();
 const key=raw.replace(/\\s*,.*$/,'').replace(/\\s+[a-z]{1,2}\\d.*$/,'').trim();
 const match=areaByTown[key];
 if(match){countyField.value=match[0];regionField.value=match[1];}
 else if(!raw){countyField.value='';regionField.value='';}
 else{countyField.value='';regionField.value='';}
}
townField?.addEventListener('change',suggestArea);
townField?.addEventListener('blur',suggestArea);
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
  suggestArea();
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
 const fields=[['class_name','Class name (optional)','text'],['day_of_week','Day','select'],['start_time','Start time','time'],['end_time','End time','time'],['frequency','Frequency','select'],['start_date','First date (optional)','date'],['end_date','Last date (optional)','date'],['price','Session price (£)','number']];
 for(const [name,label,type] of fields){
  const wrap=document.createElement('label');wrap.textContent=label;
  let control;
  if(type==='select'){control=document.createElement('select');const options=name==='day_of_week'?[['','Choose day'],['1','Monday'],['2','Tuesday'],['3','Wednesday'],['4','Thursday'],['5','Friday'],['6','Saturday'],['7','Sunday']]:[['weekly','Weekly'],['seven_weeks','7-week block'],['fortnightly','Fortnightly'],['monthly','Monthly'],['once','One-off']];options.forEach(([value,text])=>control.add(new Option(text,value)));}
  else{control=document.createElement('input');control.type=type;if(name==='class_name'){control.maxLength=160;control.placeholder='e.g. Baby Explorers';}if(type==='number'){control.min='0';control.step='0.01';}}
  control.dataset.sessionField=name;control.value=s[name]??(name==='frequency'?'weekly':'');control.required=false;wrap.appendChild(control);row.appendChild(wrap);
 }
 const term=document.createElement('label');const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.dataset.sessionField='term_time_only';checkbox.checked=String(s.term_time_only??'0')==='1';term.append(checkbox,document.createTextNode(' Term-time only (school term dates)'));row.appendChild(term);
 const frequency=row.querySelector('[data-session-field="frequency"]');const first=row.querySelector('[data-session-field="start_date"]');const last=row.querySelector('[data-session-field="end_date"]');
 const updateBlockEnd=()=>{if(frequency.value!=='seven_weeks'||!first.value)return;const date=new Date(first.value+'T12:00:00');if(Number.isNaN(date.getTime()))return;date.setDate(date.getDate()+48);const value=[date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');if(!last.value||last.dataset.autoBlockEnd==='1'){last.value=value;last.dataset.autoBlockEnd='1';}};
 last.addEventListener('input',()=>{last.dataset.autoBlockEnd='0';});first.addEventListener('change',updateBlockEnd);frequency.addEventListener('change',updateBlockEnd);
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
 const url=document.getElementById('adminIcalUrl').value.trim();importResults.replaceChildren();importMessage.textContent='Checking booking page for classes and session times…';importButton.disabled=true;
 try{
  const response=await fetch('/api/admin-ical-preview.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});
  const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Calendar could not be loaded.');
  previewImportedSessions(data.data||[],data.skipped_recurring?data.skipped_recurring+' recurrence rules require manual handling.':'');
 }catch(error){importMessage.textContent=error.message;}finally{importButton.disabled=false;}
});
document.getElementById('adminBookwhenPastePreview')?.addEventListener('click',()=>{
 const field=document.getElementById('adminBookwhenText'),rows=field.value.split(/\r?\n/);
 const button=document.getElementById('adminBookwhenPastePreview');
 let feedback=document.getElementById('adminPastePreviewMessage');
 if(!feedback){feedback=document.createElement('p');feedback.id='adminPastePreviewMessage';feedback.setAttribute('role','status');button.after(feedback);}
 const months=['january','february','march','april','may','june','july','august','september','october','november','december'];
 let month=-1,year=new Date().getFullYear(),day=null,assumedMonth=false;const events=[],seen=new Set();
 const clock=(h,m,ampm)=>String(Number(h)%12+(ampm.toLowerCase()==='pm'?12:0)).padStart(2,'0')+':'+String(Number(m||0)).padStart(2,'0');
 for(const raw of rows){
  const line=raw.replace(/[|]/g,' ').replace(/\s+/g,' ').trim();if(!line)continue;
  const heading=line.match(/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s*,?\s*(20\d{2})?\b/i);
  if(heading){const nextMonth=months.indexOf(heading[1].toLowerCase());if(month===11&&nextMonth===0&&!heading[2])year++;month=nextMonth;if(heading[2])year=Number(heading[2]);day=null;continue;}
  // Bookwhen calendar rows: "12 Mon 10am BST", followed by "11:30am BST" and "1pm BST".
  const dated=line.match(/^(\d{1,2})\s+(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\w*\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b\s*(?:BST|GMT)?\s*(.*)$/i);
  const continuation=line.match(/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b\s*(?:BST|GMT)?\s*(.*)$/i);
  if(!dated&&!continuation)continue;
  if(dated){day=Number(dated[1]);if(month<0){month=new Date().getMonth();assumedMonth=true;}}
  if(month<0||!day||day<1||day>31)continue;
  const match=dated||continuation,h=dated?3:1,m=h+1,ap=h+2,tail=h+3;
  const date=new Date(Date.UTC(year,month,day));
  if(date.getUTCMonth()!==month||date.getUTCDate()!==day)continue;
  if(dated){const weekday=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][date.getUTCDay()];if(weekday.toLowerCase()!==dated[2].slice(0,3).toLowerCase())continue;}
  const title=(match[tail]||'').replace(/^\[Button:\s*/,'').replace(/\]$/,'').trim()||'Class name to confirm';
  const start_time=clock(match[h],match[m],match[ap]),start_date=date.toISOString().slice(0,10),key=start_date+'|'+start_time;
  if(seen.has(key))continue;seen.add(key);
  events.push({title,day_of_week:((date.getUTCDay()+6)%7)+1,start_time,end_time:'',start_date,end_date:start_date,frequency:'once'});
  if(events.length>=150)break;
 }
 feedback.textContent=events.length?events.length+' possible session(s) found. '+(assumedMonth?'Month was missing from the first rows, so the current month was assumed; verify every date. ':'')+'Class names and end times must be confirmed before saving.':'No sessions recognised. Use a screenshot showing the month and rows such as 12 Mon 10am BST, or enter sessions manually.';
 previewImportedSessions(events,'Please confirm all dates, class names and finishing times. No sessions have been saved.'+(assumedMonth?' Initial month was assumed.':''));
 if(events.length)document.getElementById('adminIcalResults')?.scrollIntoView({behavior:'smooth',block:'nearest'});
});
// Optional on-device poster OCR. No image is uploaded to a third-party OCR service.
document.getElementById('adminPosterRead')?.addEventListener('click',async()=>{
 const file=document.getElementById('adminPosterImage').files?.[0],message=document.getElementById('adminPosterMessage'),preview=document.getElementById('adminPosterPreview');
 if(!file){message.textContent='Choose a poster first.';return;}
 if(!['image/png','image/jpeg','image/webp'].includes(file.type)||file.size>5*1024*1024){message.textContent='Choose PNG, JPEG or WebP under 5 MB.';return;}
 const url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;preview.onload=()=>URL.revokeObjectURL(url);
 if(typeof window.TextDetector!=='function'){message.textContent='This browser does not support on-device text recognition. You can paste poster text below instead.';return;}
 message.textContent='Reading poster…';
 try{const bitmap=await createImageBitmap(file);let blocks;try{blocks=await new TextDetector().detect(bitmap);}finally{bitmap.close?.();}
 document.getElementById('adminPosterText').value=blocks.map(x=>x.rawValue).filter(Boolean).join('\n');
 message.textContent='Poster text recognised. Review it and choose Apply recognised details.';
 }catch(error){message.textContent='Could not recognise text from this image. You can paste the text manually.';}
});
document.getElementById('adminPosterApply')?.addEventListener('click',()=>{
 const message=document.getElementById('adminPosterMessage'),text=document.getElementById('adminPosterText').value.trim();
 if(!text){message.textContent='No poster text to apply.';return;}
 const lines=text.split(/\n/).map(x=>x.trim()).filter(Boolean);
 const setEmpty=(name,value)=>{const field=form.elements.namedItem(name);if(field&&value&&!field.value.trim()){field.value=value;field.dispatchEvent(new Event('input',{bubbles:true}));}};
 const title=lines.find(x=>/stay\s*(?:&|and)\s*play|baby\s*class|toddler\s*group|sensory|playgroup|music\s*class/i.test(x)&&x.length<85);
 setEmpty('title',title);
 const age=text.match(/(?:suitable\s*for\s*)?(\d+)\s*[-–]\s*(\d+)\s*(years?|months?)/i);
 if(age)setEmpty('age_range',age[1]+'–'+age[2]+' '+age[3]);
 const phone=text.match(/(?:\+44\s?\d|0\d)[\d\s]{8,15}/);if(phone)setEmpty('phone',phone[0].trim());
 const postcode=text.match(/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i);if(postcode)setEmpty('postcode',postcode[0].toUpperCase());
 const website=text.match(/(?:https?:\/\/)?(?:www\.)?[a-z0-9-]+\.(?:co\.uk|org\.uk|com|uk)(?:\/[^\s]*)?/i);if(website)setEmpty('website',website[0].startsWith('http')?website[0]:'https://'+website[0]);
 const price=text.match(/£\s*(\d+(?:\.\d{1,2})?)/);if(price)setEmpty('price_from',price[1]);
 if(/per\s+family/i.test(text)){const unit=form.elements.namedItem('pricing_unit');if(unit)unit.value='per_family';}
 const dayNames=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
 const day=dayNames.find(d=>new RegExp('\\b'+d+'s?\\b','i').test(text));
 const times=text.match(/(\d{1,2}(?:[:.]\d{2})?\s*(?:am|pm))\s*[-–to]+\s*(\d{1,2}(?:[:.]\d{2})?\s*(?:am|pm))/i);
 const parseTime=value=>{const m=value.match(/(\d{1,2})(?:[:.](\d{2}))?\s*(am|pm)/i);if(!m)return '';let h=Number(m[1])%12;if(m[3].toLowerCase()==='pm')h+=12;return String(h).padStart(2,'0')+':'+(m[2]||'00');};
 if(day&&times){addSession({class_name:title||'',day_of_week:String(dayNames.indexOf(day)+1),start_time:parseTime(times[1]),end_time:parseTime(times[2]),frequency:'weekly'});}
 const description=lines.filter(x=>x.length>38&&!/https?:|www\.|£|postcode|registered charity|telephone/i.test(x)).slice(0,5).join('\n\n');
 setEmpty('description',description);
 message.textContent='Recognised details applied where fields were empty'+(day&&times?'; one session added':'')+'. Review every field before saving.';
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
form.addEventListener('submit',async e=>{e.preventDefault();status.textContent='Checking listing…';try{status.classList.remove('is-error');const requiredNames=['title','organisation_name','venue_name','town','category'];const missing=requiredNames.find(name=>!String(form.elements.namedItem(name)?.value||'').trim());if(missing){status.textContent='Please complete '+missing.replaceAll('_',' ')+' before saving.';status.classList.add('is-error');step=['title','organisation_name'].includes(missing)?0:missing==='category'?1:4;renderStep();return;}const body={...(original||{}),...Object.fromEntries(new FormData(form).entries())};delete body.age_preset;
body.price_free=free.checked?1:0;body.price_per_family=!free.checked&&unit.value==='per_family'?1:0;body.price_per_session=!free.checked&&unit.value==='per_session'?1:0;body.pricing_unit=unit.value;body.block_length_weeks=unit.value==='per_block'&&!free.checked?Number(blockWeeks.value):null;body.id=original?.id||null;body.sessions=collectSessions();body.venues=(original?.venues||[]).slice(1).map(v=>({...v,latitude:v.latitude??'',longitude:v.longitude??''}));body.accessibility=original?.accessibility||[];
body.latitude=original?.latitude??'';body.longitude=original?.longitude??'';body.sessions=body.sessions.map(s=>({...s,price:s.price??'',start_date:s.start_date??'',end_date:s.end_date??''}));
if(body.sessions.some(s=>!s.day_of_week||!s.start_time||s.end_time&&s.end_time<=s.start_time)){status.textContent='Each session needs a day and start time, and its end time must be later than its start time.';status.classList.add('is-error');step=3;renderStep();return;}button.disabled=true;status.textContent='Saving listing…';status.classList.remove('is-error');
try{const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});if(r.status===401){redirect();return;}const raw=await r.text();let d;try{d=JSON.parse(raw);}catch{throw Error('Server returned an invalid response (HTTP '+r.status+').');}if(!r.ok||!d.ok)throw Error(d.error||'Could not save listing (HTTP '+r.status+').');status.textContent='✓ Listing saved successfully.';if(!original){original={id:d.id};location.href='/admin/admin-activities-add-listing.html?id='+encodeURIComponent(d.id);}
}catch(err){status.textContent=err.message;status.classList.add('is-error');}finally{button.disabled=false;}}catch(err){console.error('Listing preparation failed',err);status.textContent='Could not prepare listing: '+(err?.message||'Unknown error')+'. Please review the form and try again.';status.classList.add('is-error');button.disabled=false;}});
});