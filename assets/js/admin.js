async function loadVenueSuggestions(){
 const input=document.querySelector("#activityVenueName"),list=document.querySelector("#venueNameList");
 if(!input||!list)return;
 try{
  const r=await fetch("api/venues.php",{credentials:"same-origin",cache:"no-store"});
  const d=await r.json(); if(!r.ok||!d.ok)return;
  list.innerHTML="";
  (d.data||[]).forEach(v=>{
   const o=document.createElement("option");o.value=v.name;o.label=v.name+" · "+[v.town,v.postcode].filter(Boolean).join(" · ");o.dataset.id=v.id;list.appendChild(o);
  });
  input.addEventListener("change",()=>{
   const v=(d.data||[]).find(x=>String(x.name).toLowerCase()===input.value.trim().toLowerCase());
   if(!v)return;
   const set=(id,val)=>{const el=document.querySelector(id);if(el&&val!==null&&val!==undefined)el.value=val};
   set("#activityAddress",v.address||"");set("#activityTown",v.town||"");set("#activityPostcode",v.postcode||"");set("#activityLatitude",v.latitude??"");set("#activityLongitude",v.longitude??"");
   applyTownMatch(v.town||""); if(v.region){const match=bhRegionData.regions.find(x=>String(x.region).toLowerCase()===String(v.region).toLowerCase());if(match)setRegionAndCounty(match.id);}
   updateEditorMapFromFields(true);
  });
 }catch{}
}

function addAdditionalVenue(venue={}){
 const wrap=document.querySelector("#additionalVenues");if(!wrap)return;
 const row=document.createElement("div");row.className="admin-additional-venue";row.dataset.venueId=venue.id||"";
 row.innerHTML='<div class="admin-additional-venue-head"><strong>Additional venue</strong><button type="button" class="button button-soft remove-additional-venue">Remove</button></div><div class="admin-form-grid"><label>Venue name<input data-venue="venue_name" required value="'+escapeHtml(venue.venue_name||"")+'"></label><label>Address<input data-venue="address" value="'+escapeHtml(venue.address||"")+'"></label><label>Town<input data-venue="town" value="'+escapeHtml(venue.town||"")+'"></label><label>Region<input data-venue="region" value="'+escapeHtml(venue.region||"")+'"></label><label>Postcode<input data-venue="postcode" value="'+escapeHtml(venue.postcode||"")+'"></label><label>Latitude<input data-venue="latitude" type="number" step="any" value="'+(venue.latitude??"")+'"></label><label>Longitude<input data-venue="longitude" type="number" step="any" value="'+(venue.longitude??"")+'"></label></div>';
 row.querySelector(".remove-additional-venue").onclick=()=>{row.remove();refreshSessionVenueOptions()};wrap.appendChild(row);refreshSessionVenueOptions();
}
function collectAdditionalVenues(){return [...document.querySelectorAll(".admin-additional-venue")].map(row=>{const v=n=>row.querySelector('[data-venue="'+n+'"]')?.value.trim()||"";return {id:row.dataset.venueId||null,venue_name:v("venue_name"),address:v("address"),town:v("town"),region:v("region"),postcode:v("postcode"),latitude:v("latitude"),longitude:v("longitude")}}).filter(v=>v.venue_name&&v.town)}
function validateSessions(){const rows=[...document.querySelectorAll(".admin-session-row")];for(const row of rows){const day=row.querySelector('[data-field="day_of_week"]')?.value,start=row.querySelector('[data-field="start_time"]')?.value,end=row.querySelector('[data-field="end_time"]')?.value;if(!day&&!start&&!end)continue;if(!day||!start)return "Each session needs a day and start time.";if(end&&end===start)return "Session end time must be different from the start time.";}return ""}
const setAuthMessage=(m,e=false)=>{const x=document.querySelector("#adminAuthMessage");x.textContent=m;x.classList.toggle("is-error",e)};
async function logout(){try{await fetch("admin-auth.php?action=logout",{credentials:"same-origin",cache:"no-store"});}finally{location.reload()}}
const escapeHtml=v=>String(v??"").replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch]));
async function verifyAdmin(){const r=await fetch("admin-auth.php?action=check",{credentials:"same-origin",cache:"no-store"});let d={};try{d=await r.json()}catch{}if(!r.ok||!d.ok)throw new Error(d.error||"Admin login required.");return d}

async function loadDashboard(){
 const table=document.querySelector("#adminActivities");
 try{
  const r=await fetch("api/admin-activities.php",{credentials:"same-origin",cache:"no-store"});
  let payload={};
  try{payload=await r.json()}catch{}
  if(!r.ok)throw new Error(payload.error||("Could not load activities (HTTP "+r.status+")."));
  if(!payload.ok)throw new Error(payload.error||"Could not load activities.");
  const all=Array.isArray(payload.data)?payload.data:[];
  document.querySelector("#statActivities").textContent=all.filter(a=>a.status==="published").length;
  document.querySelector("#statRegions").textContent="—";
  document.querySelector("#statSaved").textContent=String(all.length);
  document.querySelector(".admin-stats article:last-child strong").textContent="Live";
  const render=()=>{
   const q=document.querySelector("#adminSearch").value.trim().toLowerCase();
   const list=all.filter(a=>String([a.title,a.category,a.organisation_name,a.county,a.town,a.region].filter(Boolean).join(" ")).toLowerCase().includes(q));
   table.innerHTML=list.map(a=>{const price=a.price_from!==null&&a.price_from!==undefined?"£"+Number(a.price_from).toFixed(2):"—";const status=a.status==="published"?"Published":"Draft";return '<tr><td><strong>'+escapeHtml(a.title)+'</strong><small>'+escapeHtml(a.organisation_name||"")+'</small></td><td>'+escapeHtml(a.category)+'</td><td>'+escapeHtml(a.town||a.county||"—")+'</td><td>'+escapeHtml(a.session_summary||"—")+'</td><td>'+price+'</td><td><span class="admin-status admin-status-'+escapeHtml(a.status)+'">'+status+'</span></td><td><button type="button" class="button button-soft admin-edit-activity" data-id="'+escapeHtml(a.id)+'">Edit</button></td></tr>';}).join("")||'<tr><td colspan="7">No activities found.</td></tr>';
  };
  document.querySelector("#adminSearch").oninput=render;
  render();
 }catch(error){
  table.innerHTML='<tr><td colspan="7"><strong>Activities could not be loaded.</strong><br><small>'+escapeHtml(error.message)+'</small><br><button type="button" class="button button-soft" id="retryActivities">Try again</button></td></tr>';
  document.querySelector("#statActivities").textContent="—";
  document.querySelector("#statSaved").textContent="—";
  document.querySelector("#retryActivities")?.addEventListener("click",loadDashboard);
  console.error("Bubba Hub admin activities:",error);
 }
}

let bhRegionData={regions:[],towns:[]};

function normaliseTown(value){
 return String(value||"").trim().toLowerCase().replace(/[’']/g,"'").replace(/\\s+/g," ");
}
function regionById(id){
 return bhRegionData.regions.find(r=>String(r.id)===String(id))||null;
}
function findTownMatch(town){
 const key=normaliseTown(town);
 if(!key)return null;
 return bhRegionData.towns.find(t=>normaliseTown(t.town)===key)||null;
}
const BH_COUNTIES=["Devon","Cornwall","Plymouth","Torbay"];
function countyForTown(town){
 const key=normaliseTown(town);
 if(["torquay","paignton","brixham"].includes(key))return "Torbay";
 return "";
}
function setCountyValue(value){
 const county=document.querySelector("#activityCounty");
 if(!county)return;
 const match=BH_COUNTIES.find(x=>normaliseTown(x)===normaliseTown(value));
 if(match)county.value=match;
}
function setRegionAndCounty(regionId){
 const region=regionById(regionId);
 const regionEl=document.querySelector("#activityRegion");
 if(!region)return;
 if(regionEl)regionEl.value=String(region.id);
 setCountyValue(region.county||"");
}
function populateCountySelect(selected=""){
 const county=document.querySelector("#activityCounty");
 if(!county)return;
 county.innerHTML='<option value="">Select county / area</option>';
 BH_COUNTIES.forEach(name=>{
  const option=document.createElement("option");
  option.value=name; option.textContent=name; county.appendChild(option);
 });
 if(selected)setCountyValue(selected);
}
function applyTownMatch(town){
 const match=findTownMatch(town);
 if(match){
  setRegionAndCounty(match.region_id);
  return true;
 }
 return false;
}
function populateRegionSelect(selected=""){
 const select=document.querySelector("#activityRegion");
 if(!select)return;
 const groups={};
 bhRegionData.regions.forEach(r=>{
  const county=r.county||"Other";
  (groups[county] ||= []).push(r);
 });
 select.innerHTML='<option value="">Select region</option>';
 Object.entries(groups).forEach(([county,regions])=>{
  const group=document.createElement("optgroup");
  group.label=county;
  regions.forEach(r=>{
   const option=document.createElement("option");
   option.value=r.id;
   option.textContent=r.region;
   group.appendChild(option);
  });
  select.appendChild(group);
 });
 if(selected!=="")select.value=String(selected);
 populateCountySelect(regionById(selected)?.county||"");
 updateCountyFromRegion();
}
function updateCountyFromRegion(){
 const region=regionById(document.querySelector("#activityRegion")?.value);
 if(region)setCountyValue(region.county||"");
}
function populateTownList(){
 const list=document.querySelector("#activityTownList");
 if(!list)return;
 list.innerHTML="";
 bhRegionData.towns.forEach(t=>{
  const option=document.createElement("option");
  option.value=t.town;
  option.label=(t.region||"")+" · "+(t.county||"");
  list.appendChild(option);
 });
}
async function loadRegionData(selectedRegion="", selectedTown=""){
 try{
  const r=await fetch("api/regions.php",{credentials:"same-origin",cache:"no-store"});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not load regions and towns.");
  bhRegionData={regions:Array.isArray(d.regions)?d.regions:[],towns:Array.isArray(d.towns)?d.towns:[]};
  populateRegionSelect(selectedRegion);
  populateCountySelect();
  populateTownList();
  if(selectedTown && !applyTownMatch(selectedTown) && selectedRegion){
   const region=bhRegionData.regions.find(x=>String(x.id)===String(selectedRegion)||x.region===selectedRegion);
   if(region)setRegionAndCounty(region.id);
  }
  document.querySelector("#statRegions").textContent=String(bhRegionData.regions.length);
 }catch(error){
  const select=document.querySelector("#activityRegion");
  if(select)select.innerHTML='<option value="">Regions unavailable</option>';
  console.error("Bubba Hub regions:",error);
 }
}
function showTownMessage(message,error=false){
 const el=document.querySelector("#townMessage");
 if(!el)return;
 el.textContent=message||"";
 el.classList.toggle("is-error",!!error);
}
async function addNewTown(){
 const input=document.querySelector("#activityTown");
 const typed=(input?.value||"").trim();
 if(!typed){showTownMessage("Enter the new town name first.",true);input?.focus();return;}
 const existing=findTownMatch(typed);
 if(existing){
  setRegionAndCounty(existing.region_id);
  showTownMessage(typed+" is already listed in "+existing.region+".");
  return;
 }
 const currentRegion=regionById(document.querySelector("#activityRegion")?.value);
 if(!currentRegion){
  showTownMessage("Select the region for this new town first.",true);
  document.querySelector("#activityRegion")?.focus();
  return;
 }
 if(!window.confirm('Add "'+typed+'" to '+currentRegion.region+', '+currentRegion.county+'?'))return;
 const button=document.querySelector("#addTownButton");
 button.disabled=true;showTownMessage("Adding town…");
 try{
  const r=await fetch("api/regions.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({town:typed,region_id:currentRegion.id})});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not add town.");
  bhRegionData.towns.push(d.town);
  populateTownList();
  input.value=d.town.town;
  setRegionAndCounty(d.town.region_id);
  showTownMessage("✓ "+d.town.town+" has been added and is now available for everyone.");
 }catch(error){showTownMessage(error.message,true)}
 finally{button.disabled=false}
}
function initAddressAutocomplete(){
 const input=document.querySelector("#activityAddress");
 const suggestions=document.querySelector("#addressSuggestions");
 if(!input||!suggestions)return;
 let timer=null,controller=null,cache=new Map();
 const hide=()=>{suggestions.hidden=true;suggestions.innerHTML=""};
 const fill=result=>{
   const x=result.address||{};
   const town=x.city||x.town||x.village||x.municipality||"";
   input.value=[x.house_number,x.road].filter(Boolean).join(" ")||result.display_name||"";
   document.querySelector("#activityTown").value=town;
   document.querySelector("#activityPostcode").value=x.postcode||"";
   document.querySelector("#activityLatitude").value=result.lat||"";
   document.querySelector("#activityLongitude").value=result.lon||"";
   if(!applyTownMatch(town)){
    const inferred=countyForTown(town);
    if(inferred)setCountyValue(inferred);
    else setCountyValue(x.county||x.state||"");
   }
   hide();
   updateEditorMapFromFields(true);
 };
 const search=async()=>{
   const q=input.value.trim();
   if(q.length<3){hide();return}
   if(cache.has(q)){render(cache.get(q));return}
   if(controller)controller.abort();
   controller=new AbortController();
   try{
    let d=null;
    try{
      const r=await fetch("api/geocode.php?q="+encodeURIComponent(q),{credentials:"same-origin",signal:controller.signal,cache:"no-store"});
      d=await r.json();
      if(!r.ok||!d.ok)throw new Error(d.error||"Address lookup failed");
    }catch(serverError){
      if(serverError.name==="AbortError")throw serverError;
      const fallback=await fetch("https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=gb&q="+encodeURIComponent(q),{signal:controller.signal,cache:"no-store"});
      if(!fallback.ok)throw serverError;
      const results=await fallback.json();
      d={ok:true,results:Array.isArray(results)?results:[]};
    }
    cache.set(q,d.results||[]);render(d.results||[]);
   }catch(e){
    if(e.name!=="AbortError"){
     suggestions.hidden=false;
     suggestions.innerHTML='<div class="bh-address-error">'+escapeHtml(e.message||"Address lookup unavailable.")+'</div>';
    }
   }
 };
 const render=results=>{
  suggestions.innerHTML="";
  if(!results.length){hide();return}
  results.slice(0,5).forEach(result=>{
   const b=document.createElement("button");
   b.type="button";b.className="bh-address-suggestion";b.setAttribute("role","option");
   b.innerHTML='<strong>'+escapeHtml(result.address?.house_number?((result.address.house_number+" "+(result.address.road||"")).trim()):result.display_name.split(",")[0])+'</strong><span>'+escapeHtml(result.display_name)+'</span>';
   b.onclick=()=>{fill(result);setTimeout(()=>updateEditorMapFromFields(true),50)};
   suggestions.appendChild(b);
  });
  suggestions.hidden=false;
 };
 input.addEventListener("input",()=>{clearTimeout(timer);timer=setTimeout(search,900)});
 input.addEventListener("focus",()=>{if(input.value.trim().length>=3)search()});
 document.querySelector("#activityTown")?.addEventListener("change",e=>applyTownMatch(e.target.value));
 document.querySelector("#activityTown")?.addEventListener("blur",e=>applyTownMatch(e.target.value));
 document.querySelector("#activityTown")?.addEventListener("input",e=>{
   const town=e.target.value;
   const match=findTownMatch(town);
   if(match)setRegionAndCounty(match.region_id);
   else{
    const county=countyForTown(town);
    if(county)setCountyValue(county);
   }
 });
 document.querySelector("#activityRegion")?.addEventListener("change",updateCountyFromRegion);
 const addTownToggle=document.querySelector("#addTownButton");
 const addTownPanel=document.querySelector("#addTownPanel");
 const confirmAddTown=document.querySelector("#confirmAddTown");
 const cancelAddTown=document.querySelector("#cancelAddTown");
 addTownToggle?.addEventListener("click",()=>{ const open=!addTownPanel.hidden; addTownPanel.hidden=open; addTownToggle.setAttribute("aria-expanded",String(!open)); if(!open)document.querySelector("#activityTown")?.focus(); });
 confirmAddTown?.addEventListener("click",addNewTown);
 cancelAddTown?.addEventListener("click",()=>{ addTownPanel.hidden=true; addTownToggle?.setAttribute("aria-expanded","false"); });
 document.querySelector("#activityLatitude")?.addEventListener("change",()=>updateEditorMapFromFields(true));
 document.querySelector("#activityLongitude")?.addEventListener("change",()=>updateEditorMapFromFields(true));
 document.querySelector("#useAddressLocation")?.addEventListener("click",async()=>{
   const q=input.value.trim();
   if(q.length<3){suggestions.hidden=false;suggestions.innerHTML='<div class="bh-address-error">Enter at least 3 characters in the address first.</div>';return;}
   try{
    const r=await fetch("api/geocode.php?q="+encodeURIComponent(q),{credentials:"same-origin",cache:"no-store"});
    const d=await r.json();
    if(!r.ok||!d.ok||!Array.isArray(d.results)||!d.results.length)throw new Error(d.error||"We could not find that address.");
    fill(d.results[0]);
   }catch(e){
    suggestions.hidden=false;suggestions.innerHTML='<div class="bh-address-error">'+escapeHtml(e.message||"Address lookup unavailable.")+'</div>';
   }
 });
 document.addEventListener("click",e=>{if(!input.parentElement.contains(e.target))hide()});
}



let editorMap=null,editorMarker=null;
function initEditorMap(){
 const el=document.querySelector('#activityEditorMap');
 if(!el||typeof L==='undefined')return;
 if(!editorMap){
  editorMap=L.map(el,{scrollWheelZoom:true}).setView([50.431, -3.568],10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(editorMap);
 }
 setTimeout(()=>editorMap.invalidateSize(),50);
 updateEditorMapFromFields(false);
}
function updateEditorMapFromFields(fit=true){
 if(!editorMap||typeof L==='undefined')return;
 const lat=parseFloat(document.querySelector('#activityLatitude')?.value);
 const lng=parseFloat(document.querySelector('#activityLongitude')?.value);
 if(!Number.isFinite(lat)||!Number.isFinite(lng))return;
 const pos=[lat,lng];
 if(!editorMarker){
  editorMarker=L.marker(pos,{draggable:true}).addTo(editorMap);
  editorMarker.bindTooltip('Drag the pin to adjust the exact location.');
  editorMarker.on('dragend',()=>{const p=editorMarker.getLatLng();document.querySelector('#activityLatitude').value=p.lat.toFixed(7);document.querySelector('#activityLongitude').value=p.lng.toFixed(7);});
 }else editorMarker.setLatLng(pos);
 if(fit)editorMap.setView(pos,15);
}
function resetEditorMap(){
 if(editorMarker){editorMarker.remove();editorMarker=null;}
 if(editorMap){editorMap.remove();editorMap=null;}
}
function openEditor(){setEditorMode(!!editingActivityId);document.querySelector("#activityEditor").hidden=false;document.querySelector("#activityEditor").scrollIntoView({behavior:"smooth",block:"start"});loadRegionData().then(()=>{if(editingActivityId){const town=field("town")?.value||"";const region=field("region")?.value||"";applyTownMatch(town);if(region&&!regionById(region)){const match=bhRegionData.regions.find(x=>x.region===region);if(match)setRegionAndCounty(match.id);}}});setTimeout(initEditorMap,80)}
function closeEditor(){editingActivityId=null;setEditorMode(false);document.querySelector("#activityEditor").hidden=true;document.querySelector("#activityForm").reset();document.querySelector("#sessionRows").innerHTML="";document.querySelector("#activityFormMessage").textContent="";resetEditorMap()}
let editingActivityId=null;
function field(name){return document.querySelector('#activityForm [name="'+name+'"]')}
function getVenueOptions(){const primary=document.querySelector("#activityVenueName")?.value.trim()||"Main venue";const names=[primary,...[...document.querySelectorAll(".admin-additional-venue")].map(r=>r.querySelector('[data-venue="venue_name"]')?.value.trim()).filter(Boolean)];return names.map((name,i)=>'<option value="'+i+'">'+escapeHtml(name)+'</option>').join("")}
function refreshSessionVenueOptions(){document.querySelectorAll('.admin-session-row [data-field="venue_index"]').forEach(sel=>{const current=sel.value;sel.innerHTML=getVenueOptions();sel.value=current||"0";if(!sel.value)sel.value="0"})}
function addSessionRow(session={}){const wrap=document.querySelector('#sessionRows');if(!wrap)return;const row=document.createElement('div');row.className='admin-form-grid admin-session-row';row.innerHTML='<label>Venue<select data-field="venue_index">'+getVenueOptions()+'</select></label><label>Day<select data-field="day_of_week"><option value="">No session</option><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option></select></label><label>Start<input data-field="start_time" type="time" step="60" lang="en-GB"></label><label>End<input data-field="end_time" type="time" step="60" lang="en-GB"></label><label>Session price (£)<input data-field="price" type="number" step="0.01" min="0"></label><label>Term time only<input data-field="term_time_only" type="checkbox"></label><label>Frequency<input data-field="frequency" type="text"></label><label>Start date<input data-field="start_date" type="date" lang="en-GB"></label><label>End date<input data-field="end_date" type="date" lang="en-GB"></label><button type="button" class="button button-soft remove-session">Remove</button>';wrap.appendChild(row);row.querySelector('[data-field="venue_index"]').value=String(session.venue_index||0);row.querySelector('[data-field="day_of_week"]').value=String(session.day_of_week||'');row.querySelector('[data-field="start_time"]').value=(session.start_time||'').slice(0,5);row.querySelector('[data-field="end_time"]').value=(session.end_time||'').slice(0,5);row.querySelector('[data-field="price"]').value=session.price??'';row.querySelector('[data-field="term_time_only"]').checked=!!Number(session.term_time_only||0);row.querySelector('[data-field="frequency"]').value=session.frequency||'weekly';row.querySelector('[data-field="start_date"]').value=session.start_date||'';row.querySelector('[data-field="end_date"]').value=session.end_date||'';row.querySelector('.remove-session').onclick=()=>row.remove()}
function collectSessions(){return [...document.querySelectorAll('.admin-session-row')].map(row=>{const g=n=>row.querySelector('[data-field="'+n+'"]');return {venue_index:Number(g('venue_index').value||0),day_of_week:Number(g('day_of_week').value||0),start_time:g('start_time').value,end_time:g('end_time').value,price:g('price').value,term_time_only:g('term_time_only').checked,frequency:g('frequency').value,start_date:g('start_date').value,end_date:g('end_date').value}}).filter(x=>x.day_of_week&&x.start_time)}
function setEditorMode(edit){document.querySelector('#activityEditorTitle').textContent=edit?'Edit activity':'Add activity';document.querySelector('#activityEditorIntro').textContent=edit?'Update the complete listing, venue, image, pricing, age range and sessions.':'Create a real activity in the Bubba Hub MySQL database.';document.querySelector('#saveActivity').textContent=edit?'Save changes':'Save activity'}
async function editActivity(id){const r=await fetch('api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not load activity.');const a=d.data;editingActivityId=String(a.id);const form=document.querySelector('#activityForm');form.reset();Object.entries({title:a.title,category:a.category,age_range:a.age_range,county:a.county||'',price_from:a.price_from??'',image_path:a.image_path||'',status:a.status||'published',description:a.description||'',booking_url:a.booking_url||'',organisation_name:a.organisation_name||'',email:a.email||'',phone:a.phone||'',website:a.website||'',venue_name:a.venue_name||'',address:a.address||'',town:a.town||'',region:a.region||'',postcode:a.postcode||'',latitude:a.latitude??'',longitude:a.longitude??''}).forEach(([k,v])=>{const el=field(k);if(el)el.value=v??''});
 document.querySelectorAll('#activityForm input[name="accessibility[]"]').forEach(el=>el.checked=Array.isArray(a.accessibility)&&a.accessibility.includes(el.value));
 applyTownMatch(a.town||'');document.querySelector('#sessionRows').innerHTML='';(a.sessions||[]).forEach(addSessionRow);if(!(a.sessions||[]).length)addSessionRow();setEditorMode(true);openEditor();setTimeout(()=>updateEditorMapFromFields(true),160)}

function statusMessage(message,error=false){const el=document.querySelector("#activityFormMessage");if(el){el.textContent=message;el.classList.toggle("is-error",error)}}

async function createActivity(e){
 e.preventDefault();
 const form=e.currentTarget,fd=new FormData(form);
 const body=Object.fromEntries(fd.entries());
 body.region=document.querySelector("#activityRegion")?.value||"";
 body.county=document.querySelector("#activityCounty")?.value||"";
 const selectedRegion=regionById(body.region);
 if(selectedRegion){body.region=selectedRegion.region;body.county=selectedRegion.county||body.county||"";}
 const sessionError=validateSessions();if(sessionError){statusMessage(sessionError,true);return;}body.sessions=collectSessions();body.venues=collectAdditionalVenues();body.id=editingActivityId||null;
 body.accessibility=[...document.querySelectorAll('#activityForm input[name="accessibility[]"]:checked')].map(el=>el.value);
 const status=document.querySelector("#activityFormMessage"),button=form.querySelector('button[type="submit"]');
 button.disabled=true;status.textContent=editingActivityId?"Saving changes…":"Publishing…";status.classList.remove("is-error");
 try{
  const r=await fetch("api/admin-activities.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not publish activity.");
  status.textContent=editingActivityId?"✓ Listing updated successfully.":"✓ Activity published. It is now live in the directory.";
  form.reset();
  setTimeout(()=>{closeEditor();loadDashboard()},700);
 }catch(err){status.textContent=err.message;status.classList.add("is-error")}
 finally{button.disabled=false}
}

const BH_ADVANCED_FILTER_DEFAULTS={category:true,region:true,town:true,nearby:true,age:true,day:true,price:true,session_length:true,sen:true,term_time:true,booking:true,accessibility:true,free:true};
async function loadAdvancedFilterSettings(){
 const status=document.querySelector("#advancedFilterSettingsStatus");
 const box=document.querySelector("#advancedFilterSettings");
 if(!status||!box)return;
 try{
  const r=await fetch("api/search-settings.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not load filter settings.");
  const settings={...BH_ADVANCED_FILTER_DEFAULTS,...(d.data||{})};
  box.querySelectorAll("[data-filter-setting]").forEach(el=>el.checked=settings[el.dataset.filterSetting]!==false);
  status.textContent="Choose the filters you want families to see, then select Save filter settings.";
  status.classList.remove("is-error");
 }catch(e){
  status.textContent=e.message||"Could not load filter settings.";
  status.classList.add("is-error");
 }
}
async function saveAdvancedFilterSettings(){
 const status=document.querySelector("#advancedFilterSettingsStatus");
 const button=document.querySelector("#saveAdvancedFilterSettings");
 const box=document.querySelector("#advancedFilterSettings");
 if(!status||!button||!box)return;
 const filters={};
 box.querySelectorAll("[data-filter-setting]").forEach(el=>filters[el.dataset.filterSetting]=el.checked);
 button.disabled=true;status.textContent="Saving…";status.classList.remove("is-error");
 try{
  const r=await fetch("api/search-settings.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({filters})});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not save filter settings.");
  status.textContent="✓ Advanced filter settings saved.";
 }catch(e){status.textContent=e.message||"Could not save filter settings.";status.classList.add("is-error");}
 finally{button.disabled=false;}
}

async function login(){
 const u=document.querySelector("#adminUsername"),p=document.querySelector("#adminPassword"),username=u.value.trim(),password=p.value;
 if(!username||!password){setAuthMessage("Enter your username and password.",true);return}
 try{
  const r=await fetch("admin-auth.php?action=login",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({username,password})});
  const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||"Login failed.");
  u.value="";p.value="";document.querySelector("#adminAccess").hidden=true;document.querySelector("#adminContent").hidden=false;await loadDashboard();await loadAdvancedFilterSettings();
 }catch(e){setAuthMessage(e.message,true)}
}

document.querySelector("#saveAdminLogin").addEventListener("click",login);
document.querySelector("#saveAdvancedFilterSettings")?.addEventListener("click",saveAdvancedFilterSettings);
document.querySelector("#adminLogout").addEventListener("click",logout);

document.querySelector("#deployLatest").addEventListener("click",async()=>{
 const status=document.querySelector("#deployStatus");
 const button=document.querySelector("#deployLatest");
 button.disabled=true;
 status.textContent="Starting deployment…";
 try{
  const response=await fetch("deploy.php",{method:"POST",headers:{"Content-Type":"application/json"},credentials:"same-origin",cache:"no-store"});
  let data={};
  try{data=await response.json()}catch{}
  if(!response.ok||!data.ok)throw new Error(data.error||"Deployment failed");
  status.textContent="✓ Deployment started. GitHub Actions is now publishing the latest main branch.";
 }catch(error){
  status.textContent="Deployment failed: "+error.message;
 }finally{
  button.disabled=false;
 }
});
document.querySelector("#adminPassword").addEventListener("keydown",e=>{if(e.key==="Enter")login()});
document.querySelector("#addVenue")?.addEventListener("click",()=>addAdditionalVenue());
document.querySelector("#newActivity").addEventListener("click",openEditor);
document.querySelector("#cancelActivity").addEventListener("click",closeEditor);
document.querySelector("#cancelActivity2").addEventListener("click",closeEditor);
document.querySelector("#activityForm").addEventListener("submit",createActivity);document.querySelector("#addSession").addEventListener("click",()=>addSessionRow());document.querySelector("#adminActivities").addEventListener("click",async e=>{const b=e.target.closest(".admin-edit-activity");if(!b)return;try{await editActivity(b.dataset.id)}catch(err){alert(err.message)}});
initAddressAutocomplete();
loadVenueSuggestions();
verifyAdmin().then(()=>{document.querySelector("#adminAccess").hidden=true;document.querySelector("#adminContent").hidden=false;loadDashboard()}).catch(()=>{});


async function importActivityCsv(){
 const file=document.querySelector("#activityCsvFile")?.files?.[0],status=document.querySelector("#activityImportStatus"),button=document.querySelector("#importActivityCsv");
 if(!file){status.textContent="Choose a CSV file first.";status.classList.add("is-error");return;}
 if(!/\\.csv$/i.test(file.name)){status.textContent="Please choose a .csv file.";status.classList.add("is-error");return;}
 button.disabled=true;status.classList.remove("is-error");status.textContent="Importing CSV…";
 try{
  const fd=new FormData();fd.append("csv",file);fd.append("mode",document.querySelector("#activityCsvMode")?.value||"update");
  const r=await fetch("api/admin-import-export.php",{method:"POST",credentials:"same-origin",body:fd});
  const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||"CSV import failed.");
  const parts=["✓ Import complete.",d.created+" created",d.updated+" updated",d.skipped+" skipped",d.failed+" failed."];
  status.textContent=parts.join(" ");
  if(d.errors?.length)status.textContent+=" "+d.errors.join(" ");
  await loadDashboard();
 }catch(e){status.textContent=e.message||"CSV import failed.";status.classList.add("is-error");}
 finally{button.disabled=false;}
}
document.querySelector("#importActivityCsv")?.addEventListener("click",importActivityCsv);
