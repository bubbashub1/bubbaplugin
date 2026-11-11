(() => {
'use strict';
const $=id=>document.getElementById(id);
const search=$('bhActivitySearch'),select=$('bhActivitySelect'),area=$('bhSessionArea'),rows=$('bhSessionRows'),info=$('bhActivityInfo'),empty=$('bhTimetableEmpty'),status=$('bhTimetableStatus');
let activities=[],current=null,csrf='';
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const days=[['','Choose day'],['1','Monday'],['2','Tuesday'],['3','Wednesday'],['4','Thursday'],['5','Friday'],['6','Saturday'],['7','Sunday']];
const frequencies=[['weekly','Weekly'],['seven_weeks','7-week block'],['fortnightly','Fortnightly'],['monthly','Monthly'],['once','One-off']];
function filteredActivities(){const term=search.value.trim().toLowerCase();return activities.filter(a=>!term||[a.title,a.organisation_name,a.town,a.region].some(v=>String(v||'').toLowerCase().includes(term)));}
function fillSelect(){const selected=select.value;select.replaceChildren(new Option('Choose an activity',''));filteredActivities().forEach(a=>select.add(new Option(a.title+' — '+[a.organisation_name,a.town].filter(Boolean).join(' · '),String(a.id))));if([...select.options].some(o=>o.value===selected))select.value=selected;}
function field(label,name,value,type='text',wide=false){return '<label class="'+(wide?'bh-session-wide':'')+'">'+label+'<input data-field="'+name+'" type="'+type+'" value="'+esc(value??'')+'" '+(type==='number'?'min="0" step="0.01"':'')+'></label>';}
function selectField(label,name,value,options){return '<label>'+label+'<select data-field="'+name+'">'+options.map(([v,t])=>'<option value="'+v+'" '+(String(value??'')===v?'selected':'')+'>'+t+'</option>').join('')+'</select></label>';}
function addRow(s={}){
 const row=document.createElement('article');row.className='bh-session-row';row.dataset.id=String(s.id||'');
 const venueOptions=(current.venues||[]).map((v,i)=>[String(i),v.venue_name+' — '+[v.town,v.postcode].filter(Boolean).join(' ')]);
 row.innerHTML=selectField('Location','venue_index',String(s.venue_index??0),venueOptions)+field('Class name','class_name',s.class_name||'')+selectField('Day','day_of_week',String(s.day_of_week??''),days)+field('Start time','start_time',String(s.start_time||'').slice(0,5),'time')+field('End time','end_time',String(s.end_time||'').slice(0,5),'time')+selectField('Repeat pattern','frequency',s.frequency||'weekly',frequencies)+field('First date (optional)','start_date',String(s.start_date||'').slice(0,10),'date')+field('Last date (optional)','end_date',String(s.end_date||'').slice(0,10),'date')+field('Price per session (£)','price',s.price??'','number')+'<label class="bh-session-wide"><span><input data-field="term_time_only" type="checkbox" '+(String(s.term_time_only??'0')==='1'||s.term_time_only===true?'checked':'')+'> Term-time only</span></label><div class="bh-session-actions bh-session-wide"><button type="button" class="button button-soft" data-remove>Remove this session</button><small class="bh-timetable-muted">'+(s.id?'Saved session #'+esc(s.id):'New session')+'</small></div>';
 row.querySelector('[data-remove]').addEventListener('click',()=>{if(s.id&&!window.confirm('Remove this session from the timetable? If it has bookings, the server will prevent deletion.'))return;row.remove();});
 rows.append(row);
}
async function loadActivity(id){
 status.textContent='Loading timetable…';area.hidden=true;empty.hidden=false;empty.textContent='Loading selected activity…';
 const response=await fetch('/api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'});
 if(response.status===401){location.href='/admin/';return;}
 const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Could not load this activity.');
 current=data.data;csrf=data.csrf||'';rows.replaceChildren();
 const venues=current.venues||[];
 info.hidden=false;info.innerHTML='<strong>'+esc(current.title)+'</strong><p style="margin:5px 0">'+esc(current.organisation_name||'Organiser not set')+' · '+esc(venues.map(v=>[v.venue_name,v.town].filter(Boolean).join(', ')).join(' | '))+'</p><small>Activity #'+esc(current.id)+' · '+(current.sessions||[]).length+' session(s) currently saved</small>';
 (current.sessions||[]).forEach(s=>{const index=venues.findIndex(v=>Number(v.id)===Number(s.venue_id));addRow({...s,venue_index:index<0?0:index});});
 area.hidden=false;empty.hidden=true;$('bhOpenListing').href='/admin/admin-activities-add-listing.html?id='+encodeURIComponent(id);status.textContent='';
}
function collect(){return [...rows.children].map(row=>{const s={id:Number(row.dataset.id||0)};row.querySelectorAll('[data-field]').forEach(el=>s[el.dataset.field]=el.type==='checkbox'?(el.checked?1:0):el.value);return s;});}
search.addEventListener('input',fillSelect);
select.addEventListener('change',()=>{if(!select.value){area.hidden=true;empty.hidden=false;info.hidden=true;current=null;return;}loadActivity(select.value).catch(e=>status.textContent=e.message);});
$('bhAddSession').addEventListener('click',()=>{if(current)addRow();});
$('bhReloadTimetable').addEventListener('click',()=>{if(select.value)loadActivity(select.value).catch(e=>status.textContent=e.message);});
$('bhSaveTimetable').addEventListener('click',async()=>{
 if(!current)return;const sessions=collect();
 if(!sessions.length&&!window.confirm('This will remove every session from the selected activity. Continue?'))return;
 if(sessions.some(s=>!s.day_of_week||!s.start_time)){status.textContent='Every session needs a day and a start time.';return;}
 const button=$('bhSaveTimetable');button.disabled=true;status.textContent='Saving timetable…';
 try{
  const response=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({action:'save_sessions',id:current.id,csrf,sessions})});
  const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Could not save timetable.');
  status.textContent='Saved '+data.sessions_saved+' session(s). Checking the saved records…';
  await loadActivity(current.id);
  status.textContent='✓ Timetable saved and reloaded successfully. '+data.sessions_saved+' session(s) now recorded.';
 }catch(error){status.textContent=error.message||'Could not save timetable.';}
 finally{button.disabled=false;}
});
fetch('/api/admin-activities.php',{credentials:'same-origin',cache:'no-store'}).then(async r=>{if(r.status===401){location.href='/admin/';throw new Error('Admin login required.');}const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not load activities.');return d;}).then(d=>{activities=Array.isArray(d.data)?d.data:[];fillSelect();if(!activities.length){empty.textContent='No activities were returned.';}}).catch(e=>{empty.textContent=e.message;});
})();