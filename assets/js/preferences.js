const KEY="bhPreferences";
const defaults={
 emailEnabled:true,smsEnabled:false,pushEnabled:false,phone:"",smsMarketing:false,
 plannerReminders:true,bookingUpdates:true,savedSearches:false,supportReplies:true,eventReminders:true,
 region:"",town:"",day:"",categories:[],freeActivities:false,termTime:false,maxPrice:""
};
function loadPreferences(){try{return {...defaults,...JSON.parse(localStorage.getItem(KEY)||"{}")}}catch{return {...defaults}}}
function setStatus(text){const el=document.getElementById("saveStatus");if(el)el.textContent=text}
function collect(){
 const p={...loadPreferences()};
 ["emailEnabled","smsEnabled","pushEnabled","smsMarketing"].forEach(id=>p[id]=document.getElementById(id).checked);
 p.phone=document.getElementById("phone").value.trim();
 document.querySelectorAll("[data-pref]").forEach(el=>p[el.dataset.pref]=el.checked);
 p.region=document.getElementById("region").value;
 p.town=document.getElementById("town").value;
 p.day=document.getElementById("day").value;
 p.maxPrice=document.getElementById("maxPrice").value;
 p.categories=[...document.querySelectorAll("[data-interest]:checked")].map(el=>el.value);
 return p;
}
function apply(p){
 ["emailEnabled","smsEnabled","pushEnabled","smsMarketing"].forEach(id=>document.getElementById(id).checked=!!p[id]);
 document.getElementById("phone").value=p.phone||"";
 document.querySelectorAll("[data-pref]").forEach(el=>el.checked=!!p[el.dataset.pref]);
 document.getElementById("region").value=p.region||"";
 document.getElementById("day").value=p.day||"";
 document.getElementById("maxPrice").value=p.maxPrice===""?"":String(p.maxPrice);
 const push=document.getElementById("pushStatus");if(push)push.textContent=p.pushEnabled?"Enabled":"Not connected";
}
function renderInterests(categories){
 const wrap=document.getElementById("interestChoices");if(!wrap)return;
 const chosen=new Set((loadPreferences().categories||[]).map(String));
 if(!categories.length){wrap.innerHTML="<span class='preferences-loading'>No activity categories are available yet.</span>";return}
 wrap.innerHTML=categories.map(c=>"<label class='preferences-interest'><input type='checkbox' data-interest value='"+String(c).replace(/"/g,"&quot;")+"'><span>"+String(c).replace(/[&<>]/g,function(m){return ({ "&":"&amp;","<":"&lt;",">":"&gt;" }[m])})+"</span></label>").join("");
 wrap.querySelectorAll("[data-interest]").forEach(el=>el.checked=chosen.has(el.value));
}
function fillAreas(activities,p){
 const region=document.getElementById("region"),town=document.getElementById("town");
 const regions=[...new Set(activities.map(a=>String(a.region||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
 region.innerHTML="<option value=''>Any region</option>"+regions.map(v=>"<option value='"+v.replace(/"/g,"&quot;")+"'>"+v+"</option>").join("");
 region.value=p.region||"";
 const refresh=()=>{
   const towns=[...new Set(activities.filter(a=>!region.value||a.region===region.value).map(a=>String(a.town||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
   town.innerHTML="<option value=''>Any town</option>"+towns.map(v=>"<option value='"+v.replace(/"/g,"&quot;")+"'>"+v+"</option>").join("");
   town.disabled=!region.value;
   town.value=towns.includes(p.town||"")?p.town:"";
 };
 region.onchange=()=>{p.region=region.value;p.town="";refresh()};
 town.onchange=()=>{p.town=town.value};
 refresh();
}
async function apiGet(){
 const auth=window.bhAuthSession?await bhAuthSession():null;
 if(!auth?.authenticated)return {preferences:null,csrf:""};
 const r=await fetch("api/preferences.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
 const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not load account preferences.");
 return {preferences:j.preferences||{},csrf:j.csrf||auth.csrf||""};
}
async function apiSave(p,csrf){
 if(!csrf)return false;
 const r=await fetch("api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({...p,action:"save",csrf})});
 const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not save preferences.");
 return true;
}
document.addEventListener("DOMContentLoaded",async()=>{
 let p=loadPreferences(),csrf="",accountBacked=false;
 try{
   const remote=await apiGet();csrf=remote.csrf;
   if(remote.preferences){
     p={...p,...remote.preferences,categories:Array.isArray(remote.preferences.categories)?remote.preferences.categories:p.categories};
     accountBacked=true;
     localStorage.setItem(KEY,JSON.stringify(p));
   }
 }catch(e){setStatus("Saved on this device");}
 apply(p);
 setStatus(accountBacked?"Saved to your account":localStorage.getItem(KEY)?"Saved on this device":"Not saved yet");

 let activities=[];
 try{
   activities=await bhActivities();
   fillAreas(activities,p);
   const cats=[...new Set(activities.map(a=>String(a.category||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
   renderInterests(cats);
   apply(p);
   const region=document.getElementById("region"),town=document.getElementById("town");
   if(region.value)town.value=p.town||"";
 }catch(e){renderInterests([])}

 document.getElementById("savePreferences").addEventListener("click",async()=>{
   const next=collect();
   localStorage.setItem(KEY,JSON.stringify(next));
   try{
     if(csrf){
       await apiSave(next,csrf);
       accountBacked=true;
       setStatus("Saved to your account");
       document.getElementById("message").textContent="Your preferences are saved to your Bubba Hub account.";
     }else{
       setStatus("Saved on this device");
       document.getElementById("message").textContent="Your preferences have been saved on this device.";
     }
   }catch(e){
     setStatus("Saved on this device");
     document.getElementById("message").textContent="Saved on this device. Account sync could not be completed.";
   }
 });
 document.getElementById("enablePush").addEventListener("click",async()=>{
   const next=collect();next.pushEnabled=true;localStorage.setItem(KEY,JSON.stringify(next));apply(next);
   try{if(csrf)await apiSave(next,csrf)}catch(e){}
   document.getElementById("pushMessage").textContent="Push preference saved. Device permission will be connected when the push service is enabled.";
   document.getElementById("message").textContent="Push notifications are marked as enabled.";
 });
});