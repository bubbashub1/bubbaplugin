const KEY="bhPreferences";
const defaults={region:"",town:"",day:"",categories:[],freeActivities:false,termTime:false,maxPrice:""};
function loadPreferences(){try{return {...defaults,...JSON.parse(localStorage.getItem(KEY)||"{}")}}catch{return {...defaults}}}
function setStatus(text){const el=document.getElementById("saveStatus");if(el)el.textContent=text}
function escapeHtml(v){return String(v??"").replace(/[&<>"]/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[m]))}
function renderInterests(categories,chosen){
 const wrap=document.getElementById("interestChoices");if(!wrap)return;
 if(!categories.length){wrap.innerHTML="<span class='preferences-loading'>No activity categories are available yet.</span>";return}
 wrap.innerHTML=categories.map(c=>"<label class='preferences-interest'><input type='checkbox' data-interest value='"+escapeHtml(c)+"'><span>"+escapeHtml(c)+"</span></label>").join("");
 wrap.querySelectorAll("[data-interest]").forEach(el=>el.checked=chosen.has(el.value));
}
function fillAreas(activities,p){
 const region=document.getElementById("region"),town=document.getElementById("town");
 const regions=[...new Set(activities.map(a=>String(a.region||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
 region.innerHTML="<option value=''>Any region</option>"+regions.map(v=>"<option value='"+escapeHtml(v)+"'>"+escapeHtml(v)+"</option>").join("");
 region.value=p.region||"";
 const refresh=()=>{
  const towns=[...new Set(activities.filter(a=>!region.value||a.region===region.value).map(a=>String(a.town||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  town.innerHTML="<option value=''>Any town</option>"+towns.map(v=>"<option value='"+escapeHtml(v)+"'>"+escapeHtml(v)+"</option>").join("");
  town.disabled=!region.value;town.value=towns.includes(p.town||"")?p.town:"";
 };
 region.onchange=()=>{p.region=region.value;p.town="";refresh()};town.onchange=()=>{p.town=town.value};refresh();
}
async function loadRemote(){
 const auth=await bhAuthSession();
 if(!auth?.authenticated)return {preferences:null,csrf:""};
 const r=await fetch("../api/preferences.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
 const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not load preferences.");
 return {preferences:j.preferences||{},csrf:j.csrf||auth.csrf||""};
}
async function saveRemote(p,csrf){
 if(!csrf)return false;
 const r=await fetch("../api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({...p,action:"save",csrf})});
 const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not save preferences.");return true;
}
document.addEventListener("DOMContentLoaded",async()=>{
 let p=loadPreferences(),csrf="",accountBacked=false;
 try{const remote=await loadRemote();csrf=remote.csrf;if(remote.preferences){p={...p,...remote.preferences,categories:Array.isArray(remote.preferences.categories)?remote.preferences.categories:p.categories};accountBacked=true;localStorage.setItem(KEY,JSON.stringify(p));}}catch(e){}
 setStatus(accountBacked?"Saved to your account":localStorage.getItem(KEY)?"Saved on this device":"Not saved yet");
 try{const activities=await bhActivities();fillAreas(activities,p);const cats=[...new Set(activities.map(a=>String(a.category||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));renderInterests(cats,new Set((p.categories||[]).map(String)));}catch(e){renderInterests([],new Set())}
 document.getElementById("savePreferences").addEventListener("click",async()=>{
  const next={...p,region:document.getElementById("region").value,town:document.getElementById("town").value,day:document.getElementById("day").value,maxPrice:document.getElementById("maxPrice").value,categories:[...document.querySelectorAll("[data-interest]:checked")].map(x=>x.value),freeActivities:document.querySelector("[data-pref='freeActivities']").checked,termTime:document.querySelector("[data-pref='termTime']").checked};
  p=next;localStorage.setItem(KEY,JSON.stringify(p));
  try{if(csrf){await saveRemote(p,csrf);accountBacked=true;setStatus("Saved to your account");document.getElementById("message").textContent="Your discovery preferences are saved to your Bubba Hub account."}else{setStatus("Saved on this device");document.getElementById("message").textContent="Saved on this device."}}catch(e){setStatus("Saved on this device");document.getElementById("message").textContent="Saved on this device. Account sync could not be completed."}
 });
});