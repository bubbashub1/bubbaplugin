/* Staging-only, zero-AI smart import. Existing listing wizard owns persistence. */
document.addEventListener('DOMContentLoaded',()=>{
 const form=document.getElementById('newListingForm');if(!form||location.hostname!=='staging.bubbahub.co.uk')return;
 const old=document.getElementById('bhAutoFillRun'),section=old?.closest('section');
 if(!section)return;
 section.querySelector('h2').textContent='Smart Import Wizard · No AI';
 const intro=section.querySelector('p');if(intro)intro.textContent='Step 1: paste a public class URL. Step 2: review extracted fields and image. Step 3: continue through the existing listing steps to review venues, timetable and save.';
 old.hidden=true;document.getElementById('bhAutoFillCategories')?.setAttribute('hidden','');
 const url=document.getElementById('bhAutoFillUrl'),status=document.getElementById('bhAutoFillStatus');
 const scan=document.createElement('button');scan.type='button';scan.className='button button-primary';scan.textContent='1. Scan website (free)';old.after(scan);
 const review=document.createElement('div');review.id='bhSmartImportReview';review.style.cssText='margin-top:14px';status.after(review);
 const safeUrl=value=>{try{const u=new URL(value);return u.protocol==='https:'||u.protocol==='http:'?u.href:'';}catch{return '';}};
 const items=[
  ['title','Activity title'],['description','Description'],['category','Category'],['tags','Tags'],
  ['age_range','Age range'],['price_from','Price'],['organisation_name','Organiser'],
  ['venue_name','Venue'],['address','Address'],['town','Town'],['postcode','Postcode'],
  ['booking_url','Booking URL'],['website','Website'],['email','Email'],['phone','Phone'],
  ['image_path','Listing image URL']
 ];
 const field=name=>form.elements.namedItem(name);
 const emit=el=>{el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));};
 const values={};let result=null;
 scan.addEventListener('click',async()=>{
  const source=safeUrl(url.value.trim());if(!source){status.textContent='Enter a valid public website URL.';return;}
  scan.disabled=true;review.replaceChildren();status.textContent='Scanning public page without AI…';
  try{
   const response=await fetch('/api/listing-website-preview.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({url:source})});
   result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'Website could not be scanned.');
   Object.assign(values,{title:result.title||'',description:result.description||'',category:(result.categories||[])[0]||'',tags:(result.tags||[]).join(', '),website:source,image_path:result.image_url||''});
   for(const key of ['age_range','price_from','organisation_name','venue_name','address','town','postcode','booking_url','email','phone'])values[key]=result[key]||'';
   const heading=document.createElement('h3');heading.textContent='2. Review extracted fields';review.append(heading);
   const note=document.createElement('p');note.textContent='Tick only the fields you want to apply. Empty or unsupported fields remain for manual entry. Existing values are never overwritten without confirmation.';review.append(note);
   const list=document.createElement('div');list.style.cssText='display:grid;gap:8px';review.append(list);
   let found=0;
   items.forEach(([name,label])=>{
    const row=document.createElement('label');row.style.cssText='display:flex;align-items:flex-start;gap:10px;padding:8px;border:1px solid #d7dfd8;border-radius:8px';
    const check=document.createElement('input');check.type='checkbox';check.dataset.importField=name;check.disabled=!String(values[name]).trim();check.checked=!check.disabled;
    const body=document.createElement('span');body.style.cssText='min-width:0;overflow-wrap:anywhere';
    const title=document.createElement('strong');title.textContent=label+' — '+(check.disabled?'Not found':'Found · review');
    const preview=document.createElement('small');preview.style.cssText='display:block;white-space:pre-wrap;max-height:90px;overflow:auto';
    preview.textContent=String(values[name]||'Complete manually').slice(0,700);body.append(title,preview);row.append(check,body);list.append(row);
    if(!check.disabled)found++;
   });
   if(values.image_path){
    const img=document.createElement('img');img.src=values.image_path;img.alt='Website image candidate — check before applying';img.referrerPolicy='no-referrer';img.style.cssText='display:block;max-width:260px;max-height:180px;object-fit:contain;margin:12px 0;border-radius:8px';review.append(img);
   }
   const apply=document.createElement('button');apply.type='button';apply.className='button button-primary';apply.textContent='Apply selected fields and continue reviewing';review.append(apply);
   apply.addEventListener('click',()=>{
    const selected=[...review.querySelectorAll('[data-import-field]:checked')];
    const overwrites=selected.filter(x=>{const el=field(x.dataset.importField);return el&&String(el.value||'').trim()&&String(el.value).trim()!==String(values[x.dataset.importField]).trim();});
    if(overwrites.length&&!confirm('Replace '+overwrites.length+' existing field(s) with reviewed website values?'))return;
    let count=0;
    selected.forEach(x=>{const name=x.dataset.importField,el=field(name);if(!el)return;el.value=values[name];emit(el);count++;});
    status.textContent=count+' field(s) applied. Continue through the existing wizard to review organiser, venues, sessions and save.';
    review.querySelectorAll('input').forEach(x=>x.disabled=true);apply.disabled=true;
    document.getElementById('adminListingNext')?.focus();
   });
   status.textContent=found+' fields found. Review them below before applying.';
  }catch(error){status.textContent=error.message||'Unable to read this website. Continue manually.';}
  finally{scan.disabled=false;}
 });
});
