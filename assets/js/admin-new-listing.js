document.addEventListener('DOMContentLoaded',async()=>{
const form=document.getElementById('newListingForm');if(!form)return;
const status=document.getElementById('newListingStatus'),button=form.querySelector('button[type="submit"]'),id=new URLSearchParams(location.search).get('id');let original=null;
const redirect=()=>location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname+location.search));
button.disabled=true;
try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(auth.status===401){redirect();return;}const check=await auth.json();if(!auth.ok||!check.ok)throw Error('Could not verify admin access.');
const presets={'0-6':[0,6,'0–6 months'],'6-12':[6,12,'6–12 months'],'0-12':[0,12,'0–12 months'],'12-48':[12,48,'1–4 years'],'48+':[48,null,'4+ years']};
const preset=form.elements.namedItem('age_preset'),free=form.elements.namedItem('price_free'),price=form.elements.namedItem('price_from'),unit=form.elements.namedItem('pricing_unit');
const applyPreset=()=>{const v=presets[preset.value];if(!v)return;form.elements.namedItem('age_min_months').value=v[0];form.elements.namedItem('age_max_months').value=v[1]??'';form.elements.namedItem('age_range').value=v[2];};
preset.addEventListener('change',applyPreset);
free.addEventListener('change',()=>{price.disabled=free.checked;if(free.checked)price.value='0';});
if(!id){preset.value='0-12';applyPreset();}
if(id){status.textContent='Loading listing…';const r=await fetch('/api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listing.');original=d.data;for(const el of form.elements){if(el.name&&Object.hasOwn(original,el.name))el.value=original[el.name]??'';}const lo=original.age_min_months,hi=original.age_max_months;

const existing=Object.entries(presets).find(([,v])=>lo!==null&&lo!==undefined&&lo!==''&&Number(lo)===v[0]&&((hi===null||hi===undefined||hi==='')?v[1]===null:Number(hi)===v[1]));
preset.value=existing?.[0]||'';
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
// Seven-step admin editor, matching the class leader's new listing workflow.
const steps=[['Basics',['title','organisation_name','description']],['About',['category','age_preset','age_range','price_from','pricing_unit','price_free']],['Extra',['booking_url','email','phone','website','status']],['Schedule',[]],['Venue',['venue_name','town','county','region','postcode','address']],['Photos',['image_path']],['Review',[]]];
const stepNav=document.getElementById('adminListingSteps');
const back=document.getElementById('adminListingBack'),next=document.getElementById('adminListingNext'),save=document.getElementById('adminListingSubmit');
const labels=[...form.querySelectorAll('.admin-form-grid > label')];
let step=0;
const review=document.createElement('div');review.className='bh-review';review.hidden=true;form.querySelector('.admin-form-grid').after(review);
const renderStep=()=>{
 const current=steps[step];
 labels.forEach(label=>{const name=label.querySelector('[name]')?.name;label.hidden=step!==6&&!current[1].includes(name);label.style.display=label.hidden?'none':'';});
 review.hidden=step!==6;
 if(step===6){review.replaceChildren();labels.forEach(label=>{const el=label.querySelector('[name]');if(!el)return;const line=document.createElement('p');const strong=document.createElement('strong');strong.textContent=label.textContent.trim()+': ';line.append(strong,document.createTextNode(el.value||'—'));review.append(line);});}
 back.hidden=step===0;next.hidden=step===6;save.hidden=step!==6;
 [...stepNav.children].forEach((b,i)=>{b.classList.toggle('active',i===step);b.setAttribute('aria-current',i===step?'step':'false');});
};
steps.forEach(([name],i)=>{const b=document.createElement('button');b.type='button';b.className='bh-step';b.innerHTML='<span>'+(i+1)+'</span>'+name;b.addEventListener('click',()=>{step=i;renderStep();});stepNav.append(b);});
back.addEventListener('click',()=>{step=Math.max(0,step-1);renderStep();});
next.addEventListener('click',()=>{const required=labels.filter(l=>!l.hidden).map(l=>l.querySelector('[required]')).filter(Boolean);const invalid=required.find(el=>!el.value.trim());if(invalid){invalid.reportValidity();return;}step=Math.min(6,step+1);renderStep();});
renderStep();
form.addEventListener('submit',async e=>{e.preventDefault();const body={...(original||{}),...Object.fromEntries(new FormData(form).entries())};
body.price_free=free.checked?1:0;body.price_per_family=!free.checked&&unit.value==='per_family'?1:0;body.price_per_session=!free.checked&&unit.value==='per_session'?1:0;body.pricing_unit=unit.value;body.id=original?.id||null;body.sessions=original?.sessions||[];body.venues=(original?.venues||[]).slice(1).map(v=>({...v,latitude:v.latitude??'',longitude:v.longitude??''}));body.accessibility=original?.accessibility||[];
body.latitude=original?.latitude??'';body.longitude=original?.longitude??'';body.sessions=body.sessions.map(s=>({...s,price:s.price??'',start_date:s.start_date??'',end_date:s.end_date??''}));
button.disabled=true;status.textContent='Saving listing…';status.classList.remove('is-error');
try{const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});if(r.status===401){redirect();return;}const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not save listing.');status.textContent='✓ Listing saved successfully.';if(!original){original={id:d.id};location.href='/admin/admin-activities-add-listing.html?id='+encodeURIComponent(d.id);}
}catch(err){status.textContent=err.message;status.classList.add('is-error');}finally{button.disabled=false;}});
});