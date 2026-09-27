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
  document.querySelector("#adminActivities").innerHTML=list.map(a=>`<tr><td><strong>${escapeHtml(a.title)}</strong><small>${escapeHtml(a.organisation_name||"")}</small></td><td>${escapeHtml(a.category)}</td><td>${escapeHtml(a.venue_count)} venue${Number(a.venue_count)===1?"":"s"}</td><td>—</td><td>${a.price_from!==null&&a.price_from!==undefined?"£"+Number(a.price_from).toFixed(2):"—"}</td><td><span class="admin-status">${escapeHtml(a.status)}</span></td></tr>`).join("")||'<tr><td colspan="6">No activities found.</td></tr>';
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
     b.onclick=()=>fill(result);suggestions.appendChild(b);
   });
   suggestions.hidden=false;
 };
 input.addEventListener("input",()=>{clearTimeout(timer);timer=setTimeout(search,900)});
 input.addEventListener("focus",()=>{if(input.value.trim().length>=3)search()});
 document.addEventListener("click",e=>{if(!input.parentElement.contains(e.target))hide()});
}

function openEditor(){document.querySelector("#activityEditor").hidden=false;document.querySelector("#activityEditor").scrollIntoView({behavior:"smooth",block:"start"})}
function closeEditor(){document.querySelector("#activityEditor").hidden=true;document.querySelector("#activityForm").reset();document.querySelector("#activityFormMessage").textContent=""}
async function createActivity(e){
 e.preventDefault();
 const form=e.currentTarget,fd=new FormData(form),sessions=[];
 const day=fd.get("day_of_week");
 if(day&&fd.get("start_time"))sessions.push({day_of_week:Number(day),start_time:fd.get("start_time"),end_time:fd.get("end_time"),price:fd.get("session_price"),term_time_only:fd.get("term_time_only")==="on"});
 const body=Object.fromEntries(fd.entries());
 body.sessions=sessions;
 delete body.day_of_week;delete body.start_time;delete body.end_time;delete body.session_price;delete body.term_time_only;
 const status=document.querySelector("#activityFormMessage"),button=form.querySelector('button[type="submit"]');
 button.disabled=true;status.textContent="Publishing…";status.classList.remove("is-error");
 try{
  const r=await fetch("api/admin-activities.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)});
  const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.error||"Could not publish activity.");
  status.textContent="✓ Activity published. It is now live in the directory.";
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
document.querySelector("#activityForm").addEventListener("submit",createActivity);
initAddressAutocomplete();
verifyAdmin().then(()=>{document.querySelector("#adminAccess").hidden=true;document.querySelector("#adminContent").hidden=false;loadDashboard()}).catch(()=>{});
