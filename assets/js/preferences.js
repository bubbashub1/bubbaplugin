const KEY="bhPreferences";
const defaults={
 emailEnabled:true,smsEnabled:false,pushEnabled:false,phone:"",smsMarketing:false,
 plannerReminders:true,bookingUpdates:true,savedSearches:false,supportReplies:true,eventReminders:true,
 region:"",day:"",freeActivities:false,termTime:false
};
function loadPreferences(){try{return {...defaults,...JSON.parse(localStorage.getItem(KEY)||"{}")}}catch{return {...defaults}}}
function setStatus(text){const el=document.getElementById("saveStatus");if(el)el.textContent=text}
function collect(){const p={...loadPreferences()};
 ["emailEnabled","smsEnabled","pushEnabled","smsMarketing"].forEach(id=>p[id]=document.getElementById(id).checked);
 p.phone=document.getElementById("phone").value.trim();
 document.querySelectorAll("[data-pref]").forEach(el=>p[el.dataset.pref]=el.checked);
 p.region=document.getElementById("region").value;
 p.day=document.getElementById("day").value;
 return p;
}
function apply(p){
 ["emailEnabled","smsEnabled","pushEnabled","smsMarketing"].forEach(id=>document.getElementById(id).checked=!!p[id]);
 document.getElementById("phone").value=p.phone||"";
 document.querySelectorAll("[data-pref]").forEach(el=>el.checked=!!p[el.dataset.pref]);
 document.getElementById("region").value=p.region||"";
 document.getElementById("day").value=p.day||"";
 const push=document.getElementById("pushStatus");
 if(push)push.textContent=p.pushEnabled?"Enabled":"Not connected";
}
document.addEventListener("DOMContentLoaded",()=>{
 const p=loadPreferences();apply(p);setStatus(localStorage.getItem(KEY)?"Saved":"Not saved yet");
 document.getElementById("savePreferences").addEventListener("click",()=>{
   localStorage.setItem(KEY,JSON.stringify(collect()));
   setStatus("Saved");document.getElementById("message").textContent="Your preferences have been saved on this device.";
 });
 document.getElementById("enablePush").addEventListener("click",()=>{
   const p=collect();p.pushEnabled=true;localStorage.setItem(KEY,JSON.stringify(p));apply(p);
   document.getElementById("pushMessage").textContent="Push preference saved. Device permission will be connected when the Bubba Hub push service is enabled.";
   document.getElementById("message").textContent="Push notifications are marked as enabled for your account.";
 });
});