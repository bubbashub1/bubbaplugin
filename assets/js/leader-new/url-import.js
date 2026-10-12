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
 try{const j=await preview('website',url);let count=0;
  if(j.data.title&&!$('title').value.trim()){$('title').value=j.data.title;count++;}
  if(j.data.description&&!$('descriptionEditor').textContent.trim()){$('descriptionEditor').textContent=j.data.description;$('description').value=j.data.description;count++;}
  if(j.data.booking_url&&!$('bookingUrl').value.trim()){$('bookingUrl').value=j.data.booking_url;count++;}
  if(!$('leaderScheduleUrl').value.trim())$('leaderScheduleUrl').value=url;
  message.textContent=count+' details suggested. Review them before saving. No AI was used.';
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
   const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=true;selections.push([checkbox,event]);
   label.append(checkbox,document.createTextNode(event.date+' '+event.start+' '+event.day));results.append(label);}
  if(events.length){const add=document.createElement('button');add.type='button';add.className='button button-primary';add.textContent='Convert selected dates to weekly sessions';
   add.onclick=()=>{let count=0;const existing=new Set(window.BubbaNew.schedule().map(x=>x.day+'|'+x.start+'|'+x.end));
    for(const [checkbox,e] of selections){const key=e.day+'|'+e.start+'|'+e.end;if(checkbox.checked&&e.end&&e.end>e.start&&!existing.has(key)){window.BubbaNew.state.addRow(e);existing.add(key);count++;}}
    message.textContent=count+' weekly schedule rows added from dated events. These repeat weekly; the original event dates are not retained. Confirm this is appropriate before saving.';results.replaceChildren();};results.append(add);}
 }catch(e){message.textContent=e.message;}finally{button.disabled=false;}
});
});