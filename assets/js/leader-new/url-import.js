document.addEventListener('DOMContentLoaded',()=>{
const $=id=>document.getElementById(id);
async function preview(mode,url){
 const r=await fetch('/api/leader-listing-import.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode,url})});
 const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Import unavailable');return j;
}
$('leaderSchedulePreview')?.addEventListener('click',async()=>{
 const button=$('leaderSchedulePreview'),message=$('leaderScheduleMessage'),url=$('leaderScheduleUrl').value.trim(),results=$('leaderScheduleResults');
 if(!url){message.textContent='Paste a public calendar feed URL first.';return;}
 button.disabled=true;results.replaceChildren();message.textContent='Reading public calendar…';
 try{
  const j=await preview('schedule',url),events=j.sessions||[];
  if(!events.length){message.textContent='No dated calendar events were found.';return;}
  // Only offer conversion where the same weekday and exact time occur on at least
  // two separate dates. A single event must never silently become a weekly class.
  const keyOf=e=>[e.day,e.start,e.end].join('|');
  const groups=new Map();
  for(const event of events){const key=keyOf(event);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(event);}
  const repeated=new Set([...groups.entries()].filter(([,items])=>new Set(items.map(x=>x.date)).size>=2).map(([key])=>key));
  const selections=[];
  const intro=document.createElement('p');
  intro.textContent='Calendar feeds contain dated events. Bubba Hub weekly schedules repeat every week, so only patterns found on at least two different dates can be converted. One-off dates are shown but cannot be imported as weekly sessions.';
  results.append(intro);
  for(const event of events){
   const label=document.createElement('label');label.style.cssText='display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px solid #e3eae3';
   const eligible=!!event.end&&event.end>event.start&&repeated.has(keyOf(event));
   const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=eligible;checkbox.disabled=!eligible;
   if(eligible)selections.push([checkbox,event]);
   const detail=document.createElement('span');
   detail.textContent=event.date+' · '+event.day+' '+event.start+(event.end?'–'+event.end:'')+(eligible?' · Repeated pattern':' · One-off / not safe to repeat');
   label.append(checkbox,detail);results.append(label);
  }
  const eligibleCount=selections.length;
  if(!eligibleCount){
   message.textContent='Found '+events.length+' dated event(s), but no repeated weekly pattern. Nothing has been added. Add one-off dates manually where appropriate.';
   return;
  }
  const note=document.createElement('p');note.textContent=eligibleCount+' matching event(s) belong to a repeated weekday/time pattern. Check the selected rows carefully before adding.';
  results.append(note);
  const add=document.createElement('button');add.type='button';add.className='button button-primary';add.textContent='Add selected repeated weekly sessions';
  add.onclick=()=>{
   const B=window.BubbaNew;if(!B?.state?.addRow){message.textContent='The timetable editor is not ready. Please try again.';return;}
   const existing=new Set(B.schedule().map(x=>x.day+'|'+x.start+'|'+x.end));let count=0,duplicates=0;
   for(const [checkbox,e] of selections){const key=e.day+'|'+e.start+'|'+e.end;if(!checkbox.checked)continue;if(existing.has(key)){duplicates++;continue;}B.state.addRow(e);existing.add(key);count++;}
   message.textContent=count+' weekly session(s) added.'+(duplicates?' '+duplicates+' duplicate(s) skipped.':'')+' One-off dates were not converted. Review the timetable before saving.';
   results.replaceChildren();
  };
  results.append(add);
  message.textContent='Found '+events.length+' dated event(s). '+eligibleCount+' belong to repeated weekday/time patterns; one-off dates are excluded.';
 }catch(e){message.textContent=e.message||'Could not read this calendar.';}
 finally{button.disabled=false;}
});
});
