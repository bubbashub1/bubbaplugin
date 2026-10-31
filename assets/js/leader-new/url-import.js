document.addEventListener('DOMContentLoaded',()=>{
const $=id=>document.getElementById(id);
async function preview(mode,url){
 const r=await fetch('/api/leader-listing-import.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode,url})});
 const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Import unavailable');return j;
}
$('leaderListingSourcePreview')?.addEventListener('click',async()=>{
 const button=$('leaderListingSourcePreview'),message=$('leaderListingSourceMessage'),url=$('leaderListingSourceUrl').value.trim();
 if(!url){message.textContent='Paste a public website link first.';return;}
 button.disabled=true;message.textContent='Reading public page details…';
 try{const j=await preview('website',url);
  const fields=[['title','title'],['description','description'],['booking_url','bookingUrl']].filter(([key])=>j.data?.[key]);
  let review=$('leaderWebsiteReview');
  if(!review){review=document.createElement('section');review.id='leaderWebsiteReview';review.style.cssText='margin:12px 0;padding:12px;border:1px solid #b7d5c5;border-radius:10px';button.after(review);}
  review.replaceChildren();
  const heading=document.createElement('h3');heading.textContent='Review website suggestions';review.append(heading);
  const checks=[];
  for(const [key,id] of fields){
   const label=document.createElement('label');label.style.cssText='display:block;margin:10px 0';
   const check=document.createElement('input');check.type='checkbox';check.checked=false;
   const existing=key==='description'?$('descriptionEditor')?.textContent:$(id)?.value;
   label.append(check,document.createTextNode(' '+key.replaceAll('_',' ')+': '+String(j.data[key]).slice(0,240)+(existing?' (existing value will be replaced)':'')));
   review.append(label);checks.push({check,key,id});
  }
  const apply=document.createElement('button');apply.type='button';apply.className='button button-primary';apply.textContent='Apply selected fields';
  apply.onclick=()=>{let count=0;for(const {check,key,id} of checks){if(!check.checked)continue;const value=j.data[key];if(key==='description'){$('descriptionEditor').textContent=value;$('description').value=value;$('descriptionEditor').dispatchEvent(new Event('input',{bubbles:true}));}else $(id).value=value;count++;}
   message.textContent=count+' reviewed field(s) applied. Check before saving.';};
  review.append(apply);message.textContent=fields.length+' website suggestions ready. Select only what you want to import.';
  if(!$('leaderScheduleUrl').value.trim())$('leaderScheduleUrl').value=url;
 }catch(e){message.textContent=e.message;}finally{button.disabled=false;}
});
$('leaderSchedulePreview')?.addEventListener('click',async()=>{
 const button=$('leaderSchedulePreview'),message=$('leaderScheduleMessage'),url=$('leaderScheduleUrl').value.trim(),results=$('leaderScheduleResults');
 if(!url){message.textContent='Paste a public calendar feed URL first.';return;}
 button.disabled=true;results.replaceChildren();message.textContent='Reading public calendar…';
 try{const j=await preview('schedule',url);const events=j.sessions||[];
  message.textContent=events.length+' dated events found. CAUTION: this wizard stores weekly repeating sessions, not one-off dates. Converting will repeat each selected event every week. '+(j.note||'');
  const selections=[];
  for(const event of events){const label=document.createElement('label');label.style.cssText='display:flex;gap:10px;align-items:center;padding:8px 0';
   const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=false;selections.push([checkbox,event]);
   label.append(checkbox,document.createTextNode(event.date+' '+event.start+' '+event.day));results.append(label);}
  if(events.length){const caution=document.createElement('p');caution.textContent='Select only events that genuinely repeat every week. One-off events must not be converted to weekly classes.';results.prepend(caution);const add=document.createElement('button');add.type='button';add.className='button button-primary';add.textContent='Convert selected dates to weekly sessions';
   add.onclick=()=>{let count=0;const existing=new Set(window.BubbaNew.schedule().map(x=>x.day+'|'+x.start+'|'+x.end));
    for(const [checkbox,e] of selections){const key=e.day+'|'+e.start+'|'+e.end;if(checkbox.checked&&e.end&&e.end>e.start&&!existing.has(key)){window.BubbaNew.state.addRow(e);existing.add(key);count++;}}
    message.textContent=count+' weekly schedule rows added from dated events. These repeat weekly; the original event dates are not retained. Confirm this is appropriate before saving.';results.replaceChildren();};results.append(add);}
 }catch(e){message.textContent=e.message;}finally{button.disabled=false;}
});
});