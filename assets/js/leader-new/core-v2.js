async function readApiJson(response){const raw=await response.text();if(!raw.trim())throw Error("Class setup service returned an empty response (HTTP "+response.status+"). Please check the PHP error log.");let data;try{data=JSON.parse(raw)}catch(e){throw Error("Class setup service returned invalid JSON (HTTP "+response.status+"). Please check the PHP error log.");}return data;}
(()=>{"use strict";
const Q=id=>document.getElementById(id);
const API="../api/leader-portal.php";
const uniq=a=>[...new Set(a.map(x=>String(x).trim()).filter(Boolean))];
const vals=s=>[...s.selectedOptions].map(o=>o.value).filter(Boolean);
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const age=m=>{m=+m;if(m<12)return m+" month"+(m===1?"":"s");let y=Math.floor(m/12),r=m%12;return y+" year"+(y===1?"":"s")+(r?" "+r+" month"+(r===1?"":"s"):"")};
const state={current:1,meta:{categories:[],tags:[],accessibility:[],venues:[],max_images:3},files:[],validators:{},inits:[]};
const register=(name,init,validate)=>{if(init)state.inits.push(init);if(validate)state.validators[name]=validate};
const say=(t,ok)=>{const m=Q("formMessage");if(m){m.textContent=t||"";m.className=ok?"bh-ok":"bh-error"}};
const addOpt=(s,v,on)=>{v=String(v||"").trim();if(!v)return;let o=[...s.options].find(x=>x.value.toLowerCase()===v.toLowerCase());if(o)o.selected=on||o.selected;else s.add(new Option(v,v,!!on))};
const post=async body=>{let r=await fetch(API,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)}),d=await readApiJson(r);if(!r.ok||!d.ok)throw Error(d.message||d.error||"The class could not be submitted.");return d};
const syncDescription=()=>{if(Q("descriptionEditor"))Q("description").value=Q("descriptionEditor").innerHTML.trim()};
const schedule=()=>[...document.querySelectorAll(".bh-row")].map(r=>({day:r.querySelector(".day").value,start:r.querySelector(".start").value,end:r.querySelector(".end").value}));
function saveDraft(){
 const data={fields:{},multi:{},schedule:schedule(),accessibility:[...Q("accessibility").querySelectorAll("input:checked")].map(x=>x.value)};
 if(Q("descriptionEditor"))data.fields.descriptionEditor=Q("descriptionEditor").innerHTML;
 document.querySelectorAll(".bh-form input,.bh-form select,.bh-form textarea").forEach(x=>{
  if(!x.id)return;
  if(x.tagName==="SELECT"&&x.multiple)data.multi[x.id]=vals(x);
  else if(x.type==="checkbox")data.fields[x.id]=x.checked;
  else if(x.type==="radio"){if(x.checked)data.fields[x.id]=x.value}
  else data.fields[x.id]=x.value;
 });
 localStorage.setItem("bh_add_class_draft",JSON.stringify(data));
}
function loadDraft(){
 try{
  const d=JSON.parse(localStorage.getItem("bh_add_class_draft")||"{}"),fields=d.fields||{},multi=d.multi||{};
  Object.entries(multi).forEach(([id,values])=>{const x=Q(id);if(!x||!x.multiple)return;values.forEach(v=>addOpt(x,v,true))});
  Object.entries(fields).forEach(([id,v])=>{const x=Q(id);if(!x)return;if(x.type==="checkbox")x.checked=!!v;else if(x.type==="radio"){if(x.value===v)x.checked=true}else x.value=v});
  if(Q("descriptionEditor")&&fields.descriptionEditor)Q("descriptionEditor").innerHTML=fields.descriptionEditor;
  if(Array.isArray(d.schedule)&&d.schedule.length){Q("scheduleRows").innerHTML="";d.schedule.forEach(x=>state.addRow(x))}
  if(Array.isArray(d.accessibility))Q("accessibility").querySelectorAll("input").forEach(x=>x.checked=d.accessibility.includes(x.value));
 }catch(e){}
 syncDescription();if(state.ageUpdate)state.ageUpdate();if(state.photoRender)state.photoRender();if(state.renderPicker){state.renderPicker("categories");state.renderPicker("tags")}
}
function sectionSummary(n){
 const p=document.querySelector('[data-panel="'+n+'"]');if(!p)return "";
 return [...p.querySelectorAll("input,select,textarea,[contenteditable=true]")].map(x=>{
  if(x.type==="checkbox")return x.checked?x.parentElement?.textContent?.trim():"";
  if(x.type==="radio")return x.checked?x.parentElement?.textContent?.trim():"";
  if(x.multiple)return vals(x).join(", ");
  return x.value||x.textContent||"";
 }).filter(Boolean).slice(0,4).join(" · ")||"Not started";
}
function sectionState(n){
 const p=document.querySelector('[data-panel="'+n+'"]');if(!p)return"not-started";
 const inputs=[...p.querySelectorAll("input,select,textarea,[contenteditable=true]")];
 return inputs.some(x=>x.type==="checkbox"?x.checked:x.type==="radio"?x.checked:(x.value||x.textContent||"").trim())?"in-progress":"not-started";
}
function renderOverview(){
 const o=Q("sectionOverview .bh-section-list");if(!o)return;
 const items=[[1,"Basics","Class name and description"],[2,"About","Categories, tags, age range and pricing"],[3,"Extra","Booking, family information and useful details"],[4,"Schedule","Days and session times"],[5,"Venue","Venue, town and accessibility"],[6,"Photos","Listing images"],[7,"Review","Final check before submission"]];
 o.innerHTML=items.map(([n,title,desc])=>{const st=sectionState(n),summary=sectionSummary(n),label=st==="in-progress"?"In progress":st==="not-started"?"Not started":"Complete";return '<article class="bh-section-card '+st+'"><div class="bh-section-card-copy"><div class="bh-section-kicker">Section '+n+'</div><h3>'+esc(title)+'</h3><p>'+esc(desc)+'</p><small>'+esc(summary)+'</small></div><div class="bh-section-card-actions"><span class="bh-section-status">'+label+'</span><button type="button" class="button button-soft" data-edit-section="'+n+'">Edit</button></div></article>'}).join("");
 o.querySelectorAll("[data-edit-section]").forEach(b=>b.onclick=()=>openSection(+b.dataset.editSection));
}
function openSection(n){
 state.current=n;Q("sectionOverview").hidden=true;document.querySelector(".bh-form").classList.add("bh-editing");
 document.querySelectorAll(".bh-panel").forEach(p=>p.classList.toggle("active",+p.dataset.panel===n));
 document.querySelectorAll(".bh-step").forEach(b=>b.classList.toggle("active",+b.dataset.step===n));
 document.querySelector(".bh-steps").hidden=false;Q("prevStep").hidden=true;Q("nextStep").hidden=true;Q("submitListing").hidden=n!==7;Q("sectionBack").hidden=false;Q("sectionSave").hidden=false;
 if(n===6&&state.photoRender)state.photoRender();if(n===7&&state.photoRender)state.photoRender();if(n===7&&state.review)state.review();say("");window.scrollTo({top:document.querySelector(".bh-card").offsetTop-20,behavior:"smooth"});
}
function closeSection(save=true){
 if(save){syncDescription();renderOverview();saveDraft()}
 document.querySelector(".bh-form").classList.remove("bh-editing");document.querySelectorAll(".bh-panel").forEach(p=>p.classList.remove("active"));document.querySelectorAll(".bh-step").forEach(b=>b.classList.remove("active"));document.querySelector(".bh-steps").hidden=false;
 Q("sectionOverview").hidden=false;Q("sectionBack").hidden=true;Q("sectionSave").hidden=true;Q("submitListing").hidden=true;Q("prevStep").hidden=true;Q("nextStep").hidden=true;renderOverview();
}
function valid(n){syncDescription();const fn=state.validators[n];return fn?fn():true}
function validateAll(){return [1,2,4,5].every(n=>valid(n))}
async function submit(){
 if(!validateAll())return;
 const p=document.querySelector('input[name="pricing"]:checked');
 if(!p){openSection(2);say("Please select a pricing option: Free, Per session or Per family.");return;}
 const payload={action:"create_listing",title:Q("title").value.trim(),description:Q("description").value.trim(),category:vals(Q("categories")).join(", "),tags:vals(Q("tags")).join(", "),age_min_months:+Q("ageMin").value,age_max_months:+Q("ageMax").value,age_range:age(Q("ageMin").value)+" – "+age(Q("ageMax").value),price_from:p.value==="free"?"":+Q("price").value,price_free:p.value==="free",price_per_family:p.value==="family",price_per_session:p.value==="session",booking_url:Q("bookingUrl").value.trim(),booking_required:Q("bookingRequired").checked,drop_in_welcome:Q("dropInWelcome").checked,trial_available:Q("trialAvailable").checked,term_time_only:Q("termTimeOnly").checked,holiday_sessions:Q("holidaySessions").checked,siblings_welcome:Q("siblingsWelcome").checked,what_to_bring:Q("whatToBring").value.trim(),good_to_know:Q("goodToKnow").value.trim(),schedule:schedule(),accessibility:[...Q("accessibility").querySelectorAll("input:checked")].map(x=>x.value),existing_venue_id:Q("existingVenue").value?+Q("existingVenue").value:0,venue_name:Q("venueName").value.trim(),address:Q("address").value.trim(),town:Q("town").value.trim(),region:Q("region").value.trim(),postcode:Q("postcode").value.trim(),latitude:Q("latitude").value.trim(),longitude:Q("longitude").value.trim(),photos:uniq(Q("photoUrls").value.split(/\n+/))};
 Q("submitListing").disabled=true;say("Submitting your class…");
 try{let d;try{d=await post(payload)}catch(liveError){if(window.BH_DEMO_MODE&&window.bhLoadDemoLeader){const demo={id:"demo-"+Date.now(),title:payload.title,category:payload.category.split(",")[0]||"Family activity",age_range:payload.age_range,price_from:payload.price_from===""?0:payload.price_from,booking_url:payload.booking_url||"",description:payload.description,status:"published",image_path:"/wp-content/uploads/logo/placeholder.jpeg",accessibility:payload.accessibility||[],venues:[{id:"demo-venue-"+Date.now(),venue_name:payload.venue_name,address:payload.address,town:payload.town,region:payload.region,postcode:payload.postcode,latitude:payload.latitude,longitude:payload.longitude,status:"published",sessions:(payload.schedule||[]).map((s,i)=>({id:"demo-session-"+i,day_of_week:s.day_of_week||s.day,start_time:s.start_time||s.start,end_time:s.end_time||s.end,term_time_only:payload.term_time_only||false}))}]};const extras=JSON.parse(localStorage.getItem("BH_DEMO_LEADER_EXTRA")||"[]");extras.push(demo);localStorage.setItem("BH_DEMO_LEADER_EXTRA",JSON.stringify(extras));d={ok:true,id:demo.id,demo:true}}else throw liveError}let id=d.id;if(state.files.length&&!d.demo){for(const f of state.files){let fd=new FormData();fd.append("action","upload_activity_image");fd.append("activity_id",id);fd.append("image",f);let r=await fetch(API,{method:"POST",credentials:"same-origin",body:fd}),u=await r.json();if(!r.ok||!u.ok)throw Error(u.message||"An uploaded image could not be saved.")}}
  say("Class submitted successfully. It is now awaiting review.",true);localStorage.removeItem("bh_add_class_draft");setTimeout(()=>location.href="../leader/classes.html",1000)
 }catch(err){say(err.message);Q("submitListing").disabled=false}
}
async function loadMeta(){try{let r=await fetch(API,{credentials:"same-origin",cache:"no-store"}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.message||d.error||"Unable to load class setup.");state.meta.categories=Array.isArray(d.category_options)?d.category_options:Object.values(d.category_options||{});state.meta.tags=Array.isArray(d.tag_options)?d.tag_options:Object.values(d.tag_options||{});state.meta.venues=d.venues||[];state.meta.max_images=+d.max_images||3;state.meta.accessibility=Object.entries(d.accessibility_options||{}).map(([key,label])=>({key,label}));if(!state.meta.accessibility.length)state.meta.accessibility=[{key:"step_free",label:"Step-free access"},{key:"accessible_toilet",label:"Accessible toilet"},{key:"baby_changing",label:"Baby changing"},{key:"pram_access",label:"Pram / pushchair friendly"},{key:"parking",label:"Parking available"},{key:"quiet_space",label:"Quiet / low-sensory space"},{key:"hearing_loop",label:"Hearing loop / assistive listening"},{key:"visual_supports",label:"Visual supports"},{key:"sensory_friendly",label:"Sensory-friendly"},{key:"send_support",label:"SEND / additional-needs support"},{key:"outdoor_access",label:"Outdoor access"},{key:"toilets",label:"Toilets available"}];}catch(e){if(window.BH_DEMO_MODE&&window.bhLoadDemoLeader){const d=await window.bhLoadDemoLeader();state.meta.categories=["Baby & toddler","Classes & groups","Music & singing","Sport & movement","Arts & crafts","Messy play","Dance","Outdoor activities","Family wellbeing","SEND & additional needs","Pregnancy & new parents","Other"];state.meta.tags=["baby","toddler","play","sensory","messy play","music","outdoor"];state.meta.venues=(d.classes||[]).flatMap(c=>c.venues||[]);state.meta.max_images=3;state.meta.accessibility=Object.entries(d.accessibility_options||{}).map(([key,label])=>({key,label}));say("Using basic class setup defaults while the leader service is unavailable.",true);}else say(e.message)}}
async function init(){
 const overview=document.createElement("section");overview.id="sectionOverview";overview.className="bh-section-overview";overview.innerHTML='<div class="bh-overview-head"><div><span class="eyebrow">Your listing</span><h2>Class details</h2><p>Choose a section to add or update it. You can come back to any section at any time.</p></div></div><div class="bh-section-list"></div>';document.querySelector(".bh-card").insertBefore(overview,document.querySelector(".bh-steps"));
 Q("descriptionEditor").oninput=syncDescription;document.querySelectorAll(".bh-rich-toolbar [data-cmd]").forEach(b=>b.onclick=()=>{Q("descriptionEditor").focus();document.execCommand(b.dataset.cmd,false,null);syncDescription()});
 document.querySelectorAll(".bh-step").forEach(b=>b.onclick=()=>openSection(+b.dataset.step));
 Q("sectionBack").onclick=()=>closeSection(false);Q("saveProgress").onclick=()=>{syncDescription();saveDraft();say("Progress saved",true);setTimeout(()=>say(""),1800)};Q("sectionSave").onclick=()=>{if(valid(state.current))closeSection(true)};Q("prevStep").onclick=()=>openSection(Math.max(1,state.current-1));Q("nextStep").onclick=()=>{if(valid(state.current))openSection(Math.min(7,state.current+1))};
 document.querySelector(".bh-form").addEventListener("input",()=>saveDraft());
 document.querySelector(".bh-form").addEventListener("submit",e=>{e.preventDefault();submit()});
 await loadMeta();state.inits.forEach(fn=>fn());if(state.ageUpdate)state.ageUpdate();if(state.addRow)state.addRow();loadDraft();renderOverview();
}
window.BubbaNew={Q,API,uniq,vals,esc,age,state,register,say,addOpt,post,schedule,saveDraft,loadDraft,renderOverview,openSection,closeSection,valid,submit};
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();
})();