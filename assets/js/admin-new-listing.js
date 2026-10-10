document.addEventListener('DOMContentLoaded',async()=>{
const form=document.getElementById('newListingForm');if(!form)return;
const status=document.getElementById('newListingStatus'),button=form.querySelector('button[type="submit"]'),id=new URLSearchParams(location.search).get('id');let original=null;
const redirect=()=>location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname+location.search));
button.disabled=true;
try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(auth.status===401){redirect();return;}const check=await auth.json();if(!auth.ok||!check.ok)throw Error('Could not verify admin access.');
if(id){status.textContent='Loading listing…';const r=await fetch('/api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listing.');original=d.data;for(const el of form.elements){if(el.name&&Object.hasOwn(original,el.name))el.value=original[el.name]??'';}document.querySelector('h1').textContent='Edit listing';document.title='Edit listing · Admin · Bubba Hub';status.textContent='';}
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
 const addName=name=>{name=String(name||'').trim();if(name&&!names.has(name.toLowerCase()))names.set(name.toLowerCase(),name);};
 selected.forEach(addName);
 try{
  const response=await fetch('/api/admin-activities.php',{credentials:'same-origin',cache:'no-store'});
  const payload=await response.json();
  if(response.ok&&payload.ok) (payload.data||[]).forEach(a=>String(a.category||'').split(',').forEach(addName));
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
 const custom=document.createElement('input');custom.type='text';custom.placeholder='Add another category and press Enter';custom.style.marginTop='10px';
 custom.addEventListener('keydown',event=>{if(event.key!=='Enter')return;event.preventDefault();const name=custom.value.trim();if(!name)return;addName(name);selected.add(names.get(name.toLowerCase()));custom.value='';render();sync();});
 wrapper.appendChild(custom);
 form.addEventListener('submit',event=>{if(!selected.size){event.preventDefault();status.textContent='Choose at least one category.';status.classList.add('is-error');} },true);
}
button.disabled=false;
}catch(e){status.textContent=e.message;status.classList.add('is-error');return;}
form.addEventListener('submit',async e=>{e.preventDefault();const body={...(original||{}),...Object.fromEntries(new FormData(form).entries())};
body.id=original?.id||null;body.sessions=original?.sessions||[];body.venues=(original?.venues||[]).slice(1).map(v=>({...v,latitude:v.latitude??'',longitude:v.longitude??''}));body.accessibility=original?.accessibility||[];
body.latitude=original?.latitude??'';body.longitude=original?.longitude??'';body.sessions=body.sessions.map(s=>({...s,price:s.price??'',start_date:s.start_date??'',end_date:s.end_date??''}));
button.disabled=true;status.textContent='Saving listing…';status.classList.remove('is-error');
try{const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});if(r.status===401){redirect();return;}const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not save listing.');status.textContent='✓ Listing saved successfully.';if(!original)form.reset();
}catch(err){status.textContent=err.message;status.classList.add('is-error');}finally{button.disabled=false;}});
});