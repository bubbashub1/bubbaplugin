const setAuthMessage=(m,e=false)=>{const x=document.querySelector("#adminAuthMessage");x.textContent=m;x.classList.toggle("is-error",e)};
async function logout(){try{await fetch("admin-auth.php?action=logout",{credentials:"same-origin",cache:"no-store"});}finally{location.reload()}}
const escapeHtml=v=>String(v??"").replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch]));
async function verifyAdmin(){const r=await fetch("admin-auth.php?action=check",{credentials:"same-origin",cache:"no-store"});let d={};try{d=await r.json()}catch{}if(!r.ok||!d.ok)throw new Error(d.error||"Admin login required.");return d}

async function loadDashboard(){
 const r=await fetch("api/admin-activities.php",{credentials:"same-origin",cache:"no-store"});
 if(!r.ok)throw new Error("Could not load activities.");
 const payload=await r.json();
 if(!payload.ok)throw new Error(payload.error||"Could not load activities.");
 const all=Array.isArray(payload.data)?payload.data:[];
 document.querySelector("#statActivities").textContent=all.filter(a=>a.status==="published").length;
 document.querySelector("#statRegions").textContent="—";
 document.querySelector("#statSaved").textContent="—";
 document.querySelector(".admin-stats article:last-child strong").textContent="Live";
 const render=()=>{
  const q=document.querySelector("#adminSearch").value.trim().toLowerCase();
  const list=all.filter(a=>`${a.title??""} ${a.category??""} ${a.organisation_name??""}`.toLowerCase().includes(q));
  document.querySelector("#adminActivities").innerHTML=list.map(a=>`<tr><td><strong>${escapeHtml(a.title)}</strong><small>${escapeHtml(a.organisation_name||"")}</small></td><td>${escapeHtml(a.category)}</td><td>${escapeHtml(a.venue_count)} venue${Number(a.venue_count)===1?"":"s"}</td><td>—</td><td>${a.price_from!==null&&a.price_from!==undefined?"£"+Number(a.price_from).toFixed(2):"—"}</td><td><span class="admin-status">${escapeHtml(a.status)}</span></td><td><button type="button" class="button button-soft admin-edit-activity" data-id="${escapeHtml(a.id)}">Edit</button></td></tr>`).join("")||'<tr><td colspan="7">No activities found.</td></tr>';
 };
 document.querySelector("#adminSearch").oninput=render;
 render();
}

function initAddressAutocomplete(){
 const input=document.querySelector("#activityAddress");
 const suggestions=document.querySelector("#addressSuggestions");
 if(!input||!suggestions)return;
 let timer=null,controller=null,cache=new Map();
 const hide=()=>{suggestions.hidden=true;suggestions.innerHTML=""};
 const fill=result=>{
   const x=result.address||{};
   input.value=[x.house_number,x.road].filter(Boolean).join(" ")||result.display_name||"";
   document.querySelector("#activityTown").value=x.city||x.town||x.village||x.municipality||"";
   document.querySelector("#activityRegion").value=x.county||x.state||"Devon";
   document.querySelector("#activityPostcode").value=x.postcode||"";
   document.querySelector("#activityLatitude").value=result.lat||"";
   document.querySelector("#activityLongitude").value=result.lon||"";
   hide();
 };
 const search=async()=>{
   const q=input.value.trim();
   if(q.length<3){hide();return}
   if(cache.has(q)){render(cache.get(q));return}
   if(controller)controller.abort();
   controller=new AbortController();
   try{
     const r=await fetch("api/geocode.php?q="+encodeURIComponent(q),{credentials:"same-origin",signal:controller.signal,cache:"no-store"});
     const d=await r.json();
     if(!r.ok||!d.ok)throw new Error(d.error||"Address lookup failed");
     cache.set(q,d.results||[]);render(d.results||[]);
   }catch(e){if(e.name!=="AbortError")hide()}
 };
 const render=results=>{
   suggestions.innerHTML="";
   if(!results.length){hide();return}
   results.slice(0,5).forEach((result,i)=>{
     const b=document.createElement("button");
     b.type="button";b.className="bh-address-suggestion";b.setAttribute("role","option");
     b.innerHTML='<strong>'+escapeHtml(result.address?.house_number?((result.address.house_number+" "+(result.address.road||"")).trim()):result.display_name.split(",")[0])+'</strong><span>'+escapeHtml(result.display_name)+'</span>';
     b.onclick=()=>{fill(result);setTimeout(()=>updateEditorMapFromFields(true),50)};suggestions.appendChild(b);
   });
   suggestions.hidden=false;
 };
 input.addEventListener("input",()=>{clearTimeout(timer);timer=setTimeout(search,900)});
 input.addEventListener("focus",()=>{if(input.value.trim().length>=3)search()});
 document.querySelector("#activityLatitude")?.addEventListener("change",()=>updateEditorMapFromFields(true));
 document.querySelector("#activityLongitude")?.addEventListener("change",()=>updateEditorMapFromFields(true));
 document.querySelector("#useAddressLocation")?.addEventListener("click",()=>{updateEditorMapFromFields(true);});
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
function openEditor(){setEditorMode(!!editingActivityId);document.querySelector("#activityEditor").hidden=false;document.querySelector("#activityEditor").scrollIntoView({behavior:"smooth",block:"start"});setTimeout(initEditorMap,80)}
function closeEditor(){editingActivityId=null;setEditorMode(false);document.querySelector("#activityEditor").hidden=true;document.querySelector("#activityForm").reset();document.querySelector("#sessionRows").innerHTML="";document.querySelector("#activityFormMessage").textContent="";resetEditorMap()}
let editingActivityId=null;
function field(name){return document.querySelector('#activityForm [name="'+name+'"]')}
function addSessionRow(session={}){const wrap=document.querySelector('#sessionRows');if(!wrap)return;const row=document.createElement('div');row.className='admin-form-grid admin-session-row';row.innerHTML='<label>Day<select data-field="day_of_week"><option value="">No session</option><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option></select></label><label>Start<input data-field="start_time" type="time"></label><label>End<input data-field="end_time" type="time"></label><label>Session price (£)<input data-field="price" type="number" step="0.01" min="0"></label><label>Term time only<input data-field="term_time_only" type="checkbox"></label><label>Frequency<input data-field="frequency" type="text"></label><label>Start date<input data-field="start_date" type="date"></label><label>End date<input data-field="end_date" type="date"></label><button type="button" class="button button-soft remove-session">Remove</button>';wrap.appendChild(row);row.querySelector('[data-field="day_of_week"]').value=String(session.day_of_week||'');row.querySelector('[data-field="start_time"]').value=(session.start_time||'').slice(0,5);row.querySelector('[data-field="end_time"]').value=(session.end_time||'').slice(0,5);row.querySelector('[data-field="price"]').value=session.price??'';row.querySelector('[data-field="term_time_only"]').checked=!!Number(session.term_time_only||0);row.querySelector('[data-field="frequency"]').value=session.frequency||'weekly';row.querySelector('[data-field="start_date"]').value=session.start_date||'';row.querySelector('[data-field="end_date"]').value=session.end_date||'';row.querySelector('.remove-session').onclick=()=>row.remove()}
function collectSessions(){return [...document.querySelectorAll('.admin-session-row')].map(row=>{const g=n=>row.querySelector('[data-field="'+n+'"]');return {day_of_week:Number(g('day_of_week').value||0),start_time:g('start_time').value,end_time:g('end_time').value,price:g('price').value,term_time_only:g('term_time_only').checked,frequency:g('frequency').value,start_date:g('start_date').value,end_date:g('end_date').value}}).filter(x=>x.day_of_week&&x.start_time)}
function setEditorMode(edit){document.querySelector('#activityEditorTitle').textContent=edit?'Edit activity':'Add activity';document.querySelector('#activityEditorIntro').textContent=edit?'Update the complete listing, venue, image, pricing, age range and sessions.':'Create a real activity in the Bubba Hub MySQL database.';document.querySelector('#saveActivity').textContent=edit?'Save changes':'Save activity'}
async function editActivity(id){const r=await fetch('api/admin-activities.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Could not load activity.');const a=d.data;editingActivityId=String(a.id);const form=document.querySelector('#activityForm');form.reset();Object.entries({title:a.title,category:a.category,age_range:a.age_range,price_from:a.price_from??'',image_path:a.image_path||'',status:a.status||'published',description:a.description||'',booking_url:a.booking_url||'',organisation_name:a.organisation_name||'',email:a.email||'',phone:a.phone||'',website:a.website||'',venue_name:a.venue_name||'',address:a.address||'',town:a.town||'',region:a.region||'',postcode:a.postcode||'',latitude:a.latitude??'',longitude:a.longitude??''}).forEach(([k,v])=>{const el=field(k);if(el)el.value=v??''});document.querySelector('#sessionRows').innerHTML='';(a.sessions||[]).forEach(addSessionRow);if(!(a.sessions||[]).length)addSessionRow();setEditorMode(true);openEditor();setTimeout(()=>updateEditorMapFromFields(true),160)}

async function createActivity(e){
 e.preventDefault();
 const form=e.currentTarget,fd=new FormData(form);
 const body=Object.fromEntries(fd.entries());
 body.sessions=collectSessions();body.id=editingActivityId||null;
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

async function login(){
 const u=document.querySelector("#adminUsername"),p=document.querySelector("#adminPassword"),username=u.value.trim(),password=p.value;
 if(!username||!password){setAuthMessage("Enter your username and password.",true);return}
 try{
  const r=await fetch("admin-auth.php?action=login",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({username,password})});
  const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||"Login failed.");
  u.value="";p.value="";document.querySelector("#adminAccess").hidden=true;document.querySelector("#adminContent").hidden=false;await loadDashboard();
 }catch(e){setAuthMessage(e.message,true)}
}

document.querySelector("#saveAdminLogin").addEventListener("click",login);
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
document.querySelector("#newActivity").addEventListener("click",openEditor);
document.querySelector("#cancelActivity").addEventListener("click",closeEditor);
document.querySelector("#cancelActivity2").addEventListener("click",closeEditor);
document.querySelector("#activityForm").addEventListener("submit",createActivity);document.querySelector("#addSession").addEventListener("click",()=>addSessionRow());document.querySelector("#adminActivities").addEventListener("click",async e=>{const b=e.target.closest(".admin-edit-activity");if(!b)return;try{await editActivity(b.dataset.id)}catch(err){alert(err.message)}});
initAddressAutocomplete();
verifyAdmin().then(()=>{document.querySelector("#adminAccess").hidden=true;document.querySelector("#adminContent").hidden=false;loadDashboard()}).catch(()=>{});
