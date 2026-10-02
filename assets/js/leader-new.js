(()=>{"use strict";
const API="../api/leader-portal.php",Q=id=>document.getElementById(id),form=Q("newListingForm"),msg=Q("formMessage");let current=1,meta={categories:[],tags:[],accessibility:[],venues:[],max_images:3},files=[];
const uniq=a=>[...new Set(a.map(x=>String(x).trim()).filter(Boolean))],vals=s=>[...s.selectedOptions].map(o=>o.value).filter(Boolean),esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
function say(t,ok){msg.textContent=t||"";msg.className=ok?"bh-ok":"bh-error"}function age(m){m=+m;if(m<12)return m+" month"+(m===1?"":"s");let y=Math.floor(m/12),r=m%12;return y+" year"+(y===1?"":"s")+(r?" "+r+" month"+(r===1?"":"s"):"")}function ageUpdate(){let a=+Q("ageMin").value,b=+Q("ageMax").value;Q("ageOutput").textContent=b>=a?age(a)+" to "+age(b):"Please check the age range."}
function addOpt(s,v,on){v=String(v||"").trim();if(!v)return;let o=[...s.options].find(x=>x.value.toLowerCase()===v.toLowerCase());if(o)o.selected=on||o.selected;else s.add(new Option(v,v,!!on))}
function renderPicker(type){let s=Q(type),chips=Q(type==="categories"?"categoryChips":"tagChips"),opts=Q(type==="categories"?"categoryOptions":"tagOptions"),search=Q(type==="categories"?"categorySearch":"tagSearch"),pool=[...s.options].map(o=>o.value);chips.innerHTML=vals(s).map(v=>'<span class="bh-selected-chip">'+esc(v)+'<button type="button" data-remove="'+esc(v)+'" aria-label="Remove '+esc(v)+'">×</button></span>').join("");chips.querySelectorAll("[data-remove]").forEach(b=>b.onclick=()=>{[...s.options].find(o=>o.value===b.dataset.remove).selected=false;renderPicker(type);if(type==="categories")Q("categories").dispatchEvent(new Event("change"))});let q=search.value.trim().toLowerCase();opts.innerHTML=pool.filter(v=>!vals(s).includes(v)&&(!q||v.toLowerCase().includes(q))).map(v=>'<button type="button" class="bh-option" data-value="'+esc(v)+'">'+esc(v)+'</button>').join("")||'<div class="bh-option">No matches</div>';opts.querySelectorAll("[data-value]").forEach(b=>b.onclick=()=>{addOpt(s,b.dataset.value,true);renderPicker(type);if(type==="categories")Q("categories").dispatchEvent(new Event("change"));search.focus()})}
function setupPicker(type){let root=document.querySelector('[data-picker="'+type+'"]'),input=Q(type==="categories"?"categorySearch":"tagSearch");input.onfocus=()=>{root.classList.add("open");renderPicker(type)};input.oninput=()=>{root.classList.add("open");renderPicker(type)};document.addEventListener("click",e=>{if(!root.contains(e.target))root.classList.remove("open")})}
function addCustom(s,i){if(i.value.trim()){addOpt(s,i.value,true);i.value=""}}
function render(){let c=Q("categories"),t=Q("tags");c.innerHTML="";t.innerHTML="";meta.categories.forEach(x=>addOpt(c,x));meta.tags.forEach(x=>addOpt(t,x));renderPicker("categories");renderPicker("tags");Q("accessibility").innerHTML=meta.accessibility.map(x=>'<label class="bh-check"><input type="checkbox" value="'+esc(x.key)+'"> '+esc(x.label)+'</label>').join("");meta.venues.forEach(v=>{let o=new Option([v.venue_name,v.town].filter(Boolean).join(" — "),v.id);o.dataset.json=JSON.stringify(v);Q("existingVenue").add(o)});Q("imageLimit").textContent=meta.max_images}
async function load(){try{let r=await fetch(API,{credentials:"same-origin",cache:"no-store"}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.message||d.error||"Unable to load class setup.");meta.categories=d.category_options||[];meta.tags=d.tag_options||[];meta.venues=d.venues||[];meta.max_images=+d.max_images||3;meta.accessibility=Object.entries(d.accessibility_options||{}).map(([key,label])=>({key,label}));render()}catch(e){say(e.message)}}
function addRow(d){d=d||{day:"Monday",start:"09:00",end:"10:00"};let r=document.createElement("div");r.className="bh-row";r.innerHTML='<div class="bh-field"><label>Day</label><select class="day">'+["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"].map(x=>"<option>"+x+"</option>").join("")+'</select></div><div class="bh-field"><label>Start</label><input class="start" type="time" value="'+d.start+'"></div><div class="bh-field"><label>Finish</label><input class="end" type="time" value="'+d.end+'"></div><button class="bh-remove" type="button">Remove</button>';r.querySelector(".day").value=d.day;r.querySelector(".bh-remove").onclick=()=>{if(document.querySelectorAll(".bh-row").length>1)r.remove();else say("Keep at least one session.")};Q("scheduleRows").appendChild(r)}
function schedule(){return [...document.querySelectorAll(".bh-row")].map(r=>({day:r.querySelector(".day").value,start:r.querySelector(".start").value,end:r.querySelector(".end").value}))}
function photoRender(){let u=uniq(Q("photoUrls").value.split(/\n+/));Q("photoPreview").innerHTML=u.map((x,i)=>'<div class="bh-photo"><img src="'+esc(x)+'" alt="Listing image '+(i+1)+'"><button type="button" data-u="'+i+'">×</button></div>').join("")+files.map((f,i)=>'<div class="bh-photo"><img src="'+URL.createObjectURL(f)+'" alt="'+esc(f.name)+'"><button type="button" data-f="'+i+'">×</button></div>').join("");Q("photoPreview").querySelectorAll("[data-u]").forEach(b=>b.onclick=()=>{let a=u;a.splice(+b.dataset.u,1);Q("photoUrls").value=a.join("\n");photoRender()});Q("photoPreview").querySelectorAll("[data-f]").forEach(b=>b.onclick=()=>{files.splice(+b.dataset.f,1);photoRender()})}
function syncDescription(){Q("description").value=Q("descriptionEditor").innerHTML.trim()}function valid(n){syncDescription();if(n===1){if(!Q("title").value.trim())return say("Please add a class name."),false;}if(n===2){if(!vals(Q("categories")).length)return say("Please choose at least one category."),false;if(!vals(Q("tags")).length)return say("Please choose at least one tag."),false;let a=+Q("ageMin").value,b=+Q("ageMax").value,p=document.querySelector('input[name="pricing"]:checked');if(!Number.isInteger(a)||!Number.isInteger(b)||a<0||b<a||b>216)return say("Please enter a valid age range."),false;if(!p)return say("Please choose Free, Per session or Per family."),false;if(p.value!=="free"&&(!Q("price").value||+Q("price").value<0))return say("Please enter a price."),false}
if(n===1&&!Q("description").value.trim())return say("Please add a description for families."),false;
if(n===4&&schedule().some(x=>!x.start||!x.end||x.end<=x.start))return say("Please check each session day and time."),false;
if(n===5&&!Q("existingVenue").value&&(!Q("venueName").value.trim()||!Q("town").value.trim()))return say("Please add a venue name and town."),false;return true}
function sectionSummary(n){
 const p=document.querySelector('[data-panel="'+n+'"]');
 if(!p)return "";
 const text=[...p.querySelectorAll("input,select,textarea,[contenteditable=true]")].map(x=>{
   if(x.type==="checkbox") return x.checked?x.parentElement?.textContent?.trim():"";
   if(x.type==="radio") return x.checked?x.parentElement?.textContent?.trim():"";
   if(x.multiple) return vals(x).join(", ");
   return x.value||x.textContent||"";
 }).filter(Boolean);
 return text.slice(0,4).join(" · ")||"Not started";
}
function sectionState(n){
 const p=document.querySelector('[data-panel="'+n+'"]');
 if(!p)return "not-started";
 const inputs=[...p.querySelectorAll("input,select,textarea,[contenteditable=true]")];
 const meaningful=inputs.some(x=>x.type==="checkbox"?x.checked:x.type==="radio"?x.checked:(x.value||x.textContent||"").trim());
 return meaningful?"in-progress":"not-started";
}
function renderOverview(){
 const o=Q("sectionOverview .bh-section-list"); if(!o)return;
 const items=[
  [1,"Basics","Class name and description"],
  [2,"About","Categories, tags, age range and pricing"],
  [3,"Extra","Booking, family information and useful details"],
  [4,"Schedule","Days and session times"],
  [5,"Venue","Venue, town and accessibility"],
  [6,"Photos","Listing images"],[7,"Review","Final check before submission"]
 ];
 o.innerHTML=items.map(([n,title,desc])=>{
   const state=sectionState(n), summary=sectionSummary(n);
   const label=state==="in-progress"?"In progress":state==="not-started"?"Not started":"Complete";
   return '<article class="bh-section-card '+state+'"><div class="bh-section-card-copy"><div class="bh-section-kicker">Section '+n+'</div><h3>'+esc(title)+'</h3><p>'+esc(desc)+'</p><small>'+esc(summary)+'</small></div><div class="bh-section-card-actions"><span class="bh-section-status">'+label+'</span><button type="button" class="button button-soft" data-edit-section="'+n+'">Edit</button></div></article>';
 }).join("");
 o.querySelectorAll("[data-edit-section]").forEach(b=>b.onclick=()=>openSection(+b.dataset.editSection));
}
function openSection(n){
 current=n;
 Q("sectionOverview").hidden=true;
 document.querySelector(".bh-form").classList.add("bh-editing");
 document.querySelectorAll(".bh-panel").forEach(p=>p.classList.toggle("active",+p.dataset.panel===n));
 document.querySelector(".bh-steps").hidden=true;
 Q("prevStep").hidden=true;
 Q("nextStep").hidden=true;
 Q("submitListing").hidden=n!==7;
 Q("sectionBack").hidden=false;
 Q("sectionSave").hidden=false;
 if(n===6){photoRender()}if(n===7){photoRender();review()}
 say("");
 window.scrollTo({top:document.querySelector(".bh-card").offsetTop-20,behavior:"smooth"});
}
function closeSection(save=true){
 if(save){syncDescription();renderOverview();saveDraft();}
 document.querySelector(".bh-form").classList.remove("bh-editing");
 document.querySelectorAll(".bh-panel").forEach(p=>p.classList.remove("active"));
 document.querySelector(".bh-steps").hidden=false;
 Q("sectionOverview").hidden=false;
 Q("sectionBack").hidden=true;
 Q("sectionSave").hidden=true;
 Q("submitListing").hidden=true;
 Q("prevStep").hidden=true;
 Q("nextStep").hidden=true;
 renderOverview();
}
function setStep(n){openSection(n)}
function review(){
 let p=document.querySelector('input[name="pricing"]:checked');
 let price=!p?"Not set":p.value==="free"?"Free":"£"+(+Q("price").value||0).toFixed(2)+" per "+(p.value==="family"?"family":"session");
 let access=[...Q("accessibility").querySelectorAll("input:checked")].map(x=>x.parentElement.textContent.trim());
 let accessHtml=access.length?access.map(esc).join(", "):"None selected";
 Q("review").innerHTML='<div class="bh-review-card"><h3>'+esc(Q("title").value)+'</h3><p>'+esc(Q("description").value)+'</p></div><div class="bh-review-card"><b>Category:</b> '+esc(vals(Q("categories")).join(", "))+'<br><b>Tags:</b> '+esc(vals(Q("tags")).join(", "))+'<br><b>Age:</b> '+esc(Q("ageOutput").textContent)+'<br><b>Price:</b> '+esc(price)+'</div><div class="bh-review-card"><b>Schedule:</b><br>'+schedule().map(x=>esc(x.day+" "+x.start+"–"+x.end)).join("<br>")+'</div><div class="bh-review-card"><b>Venue:</b> '+esc(Q("venueName").value)+", "+esc(Q("town").value)+'<br><b>Accessibility &amp; family facilities:</b> '+esc(accessHtml)+'</div>';
}

const overview=document.createElement("section");overview.id="sectionOverview";overview.className="bh-section-overview";overview.innerHTML="<div class=\"bh-overview-head\"><div><span class=\"eyebrow\">Your listing</span><h2>Class details</h2><p>Choose a section to add or update it. You can come back to any section at any time.</p></div></div><div class=\"bh-section-list\"></div>";document.querySelector(".bh-card").insertBefore(overview,document.querySelector(".bh-steps"));
const actions=document.querySelector(".bh-actions");Q("descriptionEditor").oninput=syncDescription;document.querySelectorAll(".bh-rich-toolbar [data-cmd]").forEach(b=>b.onclick=()=>{Q("descriptionEditor").focus();document.execCommand(b.dataset.cmd,false,null);syncDescription()});Q("ageMin").oninput=ageUpdate;Q("ageMax").oninput=ageUpdate;Q("addCategory").onclick=()=>{addCustom(Q("categories"),Q("newCategory"));renderPicker("categories");Q("categories").dispatchEvent(new Event("change"))};Q("addTag").onclick=()=>{addCustom(Q("tags"),Q("newTag"));renderPicker("tags")};setupPicker("categories");setupPicker("tags");Q("newCategory").onkeydown=e=>{if(e.key==="Enter"){e.preventDefault();Q("addCategory").click()}};Q("newTag").onkeydown=e=>{if(e.key==="Enter"){e.preventDefault();Q("addTag").click()}};
Q("categories").onchange=async()=>{renderPicker("categories");let selected=vals(Q("categories"));if(!selected.length){Q("tagSuggestions").innerHTML="";return}try{let all=[];for(const c of selected){let r=await fetch(API,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"tag_suggestions",category:c})}),d=await r.json();all.push(...(d.suggestions||[]))}let suggestions=uniq(all);suggestions.forEach(x=>addOpt(Q("tags"),x,true));renderPicker("tags");Q("tagSuggestions").innerHTML=suggestions.map(x=>'<button class="bh-chip" type="button" data-tag="'+esc(x)+'">✓ '+esc(x)+'</button>').join("");Q("tagSuggestions").querySelectorAll("[data-tag]").forEach(b=>b.onclick=()=>addOpt(Q("tags"),b.dataset.tag,true))}catch(e){}};
Q("addSchedule").onclick=()=>addRow();Q("existingVenue").onchange=()=>{let o=Q("existingVenue").selectedOptions[0];if(!o||!o.value)return;let v=JSON.parse(o.dataset.json||"{}");["venueName","address","town","region","postcode","latitude","longitude"].forEach(k=>Q(k).value=v[k==="venueName"?"venue_name":k]??"")};
Q("photoUrls").oninput=photoRender;Q("photoUpload").onchange=()=>{let f=[...Q("photoUpload").files],count=uniq(Q("photoUrls").value.split(/\n+/)).length;if(count+files.length+f.length>meta.max_images)return say("You can add up to "+meta.max_images+" images on your plan.");files.push(...f);Q("photoUpload").value="";photoRender()};
document.querySelectorAll(".bh-step").forEach(b=>b.onclick=()=>openSection(+b.dataset.step));
Q("sectionBack").onclick=()=>closeSection(false);
Q("saveProgress").onclick=()=>{syncDescription();saveDraft();say("Progress saved",true);setTimeout(()=>say(""),1800)};
Q("sectionSave").onclick=()=>{if(valid(current)){closeSection(true)}};
Q("prevStep").onclick=()=>openSection(Math.max(1,current-1));
Q("nextStep").onclick=()=>{if(valid(current))openSection(Math.min(7,current+1))};
document.querySelector(".bh-form").addEventListener("input",()=>{if(current) saveDraft()});
async function post(body){let r=await fetch(API,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.message||d.error||"The class could not be submitted.");return d}
form.onsubmit=async e=>{e.preventDefault();if(!valid(6))return;let p=document.querySelector('input[name="pricing"]:checked'),payload={action:"create_listing",title:Q("title").value.trim(),description:Q("description").value.trim(),category:vals(Q("categories")).join(", "),tags:vals(Q("tags")).join(", "),age_min_months:+Q("ageMin").value,age_max_months:+Q("ageMax").value,age_range:age(Q("ageMin").value)+" – "+age(Q("ageMax").value),price_from:p.value==="free"?"":+Q("price").value,price_free:p.value==="free",price_per_family:p.value==="family",price_per_session:p.value==="session",booking_required:Q("bookingRequired").checked,drop_in_welcome:Q("dropInWelcome").checked,trial_available:Q("trialAvailable").checked,term_time_only:Q("termTimeOnly").checked,holiday_sessions:Q("holidaySessions").checked,siblings_welcome:Q("siblingsWelcome").checked,what_to_bring:Q("whatToBring").value.trim(),good_to_know:Q("goodToKnow").value.trim(),booking_url:Q("bookingUrl").value.trim(),schedule:schedule(),accessibility:[...Q("accessibility").querySelectorAll("input:checked")].map(x=>x.value),existing_venue_id:Q("existingVenue").value?+Q("existingVenue").value:0,venue_name:Q("venueName").value.trim(),address:Q("address").value.trim(),town:Q("town").value.trim(),region:Q("region").value.trim(),postcode:Q("postcode").value.trim(),latitude:Q("latitude").value.trim(),longitude:Q("longitude").value.trim(),photos:uniq(Q("photoUrls").value.split(/\n+/))};Q("submitListing").disabled=true;say("Submitting your class…");try{let d=await post(payload),id=d.id;if(files.length){for(let f of files){let fd=new FormData();fd.append("action","upload_activity_image");fd.append("activity_id",id);fd.append("image",f);let r=await fetch(API,{method:"POST",credentials:"same-origin",body:fd}),u=await r.json();if(!r.ok||!u.ok)throw Error(u.message||"An uploaded image could not be saved.")}}say("Class submitted successfully. It is now awaiting review.",true);setTimeout(()=>location.href="../leader/classes.html",1000)}catch(err){say(err.message);Q("submitListing").disabled=false}};
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
  Object.entries(multi).forEach(([id,values])=>{
    const x=Q(id);if(!x||!x.multiple)return;
    values.forEach(v=>addOpt(x,v,true));
  });
  Object.entries(fields).forEach(([id,v])=>{
    const x=Q(id);if(!x)return;
    if(x.type==="checkbox")x.checked=!!v;
    else if(x.type==="radio"){if(x.value===v)x.checked=true}
    else x.value=v;
  });
  if(Q("descriptionEditor")&&fields.descriptionEditor)Q("descriptionEditor").innerHTML=fields.descriptionEditor;
  if(Array.isArray(d.schedule)&&d.schedule.length){
    Q("scheduleRows").innerHTML="";
    d.schedule.forEach(x=>addRow(x));
  }
  if(Array.isArray(d.accessibility)){
    Q("accessibility").querySelectorAll("input").forEach(x=>x.checked=d.accessibility.includes(x.value));
  }
 }catch(e){}
 syncDescription();ageUpdate();photoRender();renderPicker("categories");renderPicker("tags");
}
ageUpdate();addRow();load().then(()=>{loadDraft();renderOverview()});})();