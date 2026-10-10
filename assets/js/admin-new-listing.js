document.addEventListener('DOMContentLoaded',async()=>{
const form=document.getElementById('newListingForm');if(!form)return;
const status=document.getElementById('newListingStatus'),button=form.querySelector('button[type="submit"]'),id=new URLSearchParams(location.search).get('id');let original=null;
const redirect=()=>location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname+location.search));
button.disabled=true;
try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(auth.status===401){redirect();return;}const check=await auth.json();if(!auth.ok||!check.ok)throw Error('Could not verify admin access.');
if(id){status.textContent='Loading listing…';const r=await fetch('/api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listing.');original=d.data;for(const el of form.elements){if(el.name&&Object.hasOwn(original,el.name))el.value=original[el.name]??'';}document.querySelector('h1').textContent='Edit listing';document.title='Edit listing · Admin · Bubba Hub';status.textContent='';}
button.disabled=false;
}catch(e){status.textContent=e.message;status.classList.add('is-error');return;}
form.addEventListener('submit',async e=>{e.preventDefault();const body={...(original||{}),...Object.fromEntries(new FormData(form).entries())};
body.id=original?.id||null;body.sessions=original?.sessions||[];body.venues=(original?.venues||[]).slice(1).map(v=>({...v,latitude:v.latitude??'',longitude:v.longitude??''}));body.accessibility=original?.accessibility||[];
body.latitude=original?.latitude??'';body.longitude=original?.longitude??'';body.sessions=body.sessions.map(s=>({...s,price:s.price??'',start_date:s.start_date??'',end_date:s.end_date??''}));
button.disabled=true;status.textContent='Saving listing…';status.classList.remove('is-error');
try{const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)});if(r.status===401){redirect();return;}const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not save listing.');status.textContent='✓ Listing saved successfully.';if(!original)form.reset();
}catch(err){status.textContent=err.message;status.classList.add('is-error');}finally{button.disabled=false;}});
});