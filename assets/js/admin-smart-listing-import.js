/* Reviewed website import: no fields are changed until explicitly selected. */
(()=>{
 const run=document.getElementById('bhAutoFillRun'),form=document.getElementById('newListingForm');
 if(!run||!form)return;
 const panel=document.createElement('section');panel.id='bhSmartImportReview';panel.hidden=true;panel.style.cssText='margin:14px 0;padding:14px;border:1px solid #b7d5c5;border-radius:12px;background:#f5faf7';
 run.insertAdjacentElement('afterend',panel);
 const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 run.addEventListener('click',async event=>{
  event.preventDefault();event.stopImmediatePropagation();
  const status=document.getElementById('bhAutoFillStatus'),url=document.getElementById('bhAutoFillUrl').value.trim();
  if(!/^https?:\/\//i.test(url)){status.textContent='Enter a public website URL.';return;}
  run.disabled=true;panel.hidden=true;status.textContent='Reading website and preparing review…';
  try{
   const response=await fetch('/api/listing-website-preview.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({url})});
   const data=await response.json();if(!response.ok||!data.ok)throw Error(data.message||'Website could not be read');
   const values={title:data.title,description:data.description,website:url,tags:(data.tags||[]).join(', '),email:data.contact?.email,phone:data.contact?.phone,facebook_url:data.contact?.facebook_url,instagram_url:data.contact?.instagram_url,booking_url:data.contact?.booking_url,image_path:data.image_url,category:(data.categories||[]).join(', ')};
   const entries=Object.entries(values).filter(([name,value])=>value&&form.elements.namedItem(name));
   panel.innerHTML='<h3>Review website suggestions</h3><p>Select individual fields to import. Existing values are never replaced unless you tick them.</p>'+entries.map(([name,value],i)=>{
    const current=form.elements.namedItem(name)?.value||'';
    return '<label style="display:block;margin:10px 0;padding:8px;border-bottom:1px solid #dce8df"><input type="checkbox" data-smart-index="'+i+'"> <strong>'+escape(name.replaceAll('_',' '))+'</strong><small style="display:block">Website: '+escape(String(value).slice(0,400))+'</small>'+(current?'<small style="display:block">Existing: '+escape(current.slice(0,180))+'</small>':'')+'</label>';
   }).join('')+'<button type="button" class="button button-primary" id="bhSmartApply">Apply selected fields</button> <button type="button" class="button button-soft" id="bhSmartSchedule">Find venues &amp; schedules for review</button><p style="margin-top:8px">Sessions and venues are previewed separately, with individual checkboxes. Nothing is saved automatically.</p>';
   panel.hidden=false;status.textContent=entries.length+' suggested fields ready for review.';panel.querySelector('#bhSmartSchedule').addEventListener('click',()=>{const booking=document.getElementById('adminIcalUrl'),preview=document.getElementById('adminIcalPreview'),section=document.getElementById('adminListingSchedule');if(!booking||!preview){status.textContent='Schedule importer is unavailable on this page.';return;}const link=data.contact?.booking_url||url;booking.value=link;section.hidden=false;section.scrollIntoView({behavior:'smooth',block:'start'});preview.click();status.textContent='Checking '+link+' for sessions and venues. Review each class before adding.';});
   panel.querySelector('#bhSmartApply').addEventListener('click',()=>{
    let applied=0;
    panel.querySelectorAll('[data-smart-index]:checked').forEach(box=>{
     const [name,value]=entries[Number(box.dataset.smartIndex)];const field=form.elements.namedItem(name);
     if(!field)return;
     if(name==='category'){field.dispatchEvent(new CustomEvent('bh:apply-categories',{detail:data.categories||[]}));}else{field.value=value;}field.dispatchEvent(new Event('input',{bubbles:true}));field.dispatchEvent(new Event('change',{bubbles:true}));applied++;
     if(name==='description'){const editor=document.getElementById('adminDescriptionEditor');if(editor)editor.textContent=value;}
    });
    status.textContent=applied+' fields applied. Review the listing before saving.';
   });
  }catch(error){status.textContent=error.message||'Could not extract website information.';}
  finally{run.disabled=false;}
 },true);
})();
