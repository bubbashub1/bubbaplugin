document.addEventListener("DOMContentLoaded",()=>{
 const form=document.getElementById("consentForm"), topics=document.getElementById("supportTopics"), status=document.getElementById("consentStatus"), key="bhConsentProfiles";
 let state={};
 try{state=JSON.parse(localStorage.getItem(key)||"{}")}catch{}
 const childSelect=form.querySelector("#consentChildProfile");
 async function loadChildren(){
   if(!childSelect)return;
   try{
     const res=await fetch("/api/my-hub.php?view=family",{credentials:"same-origin",headers:{Accept:"application/json"}});
     if(!res.ok)return;
     const data=await res.json();
     (data.children||[]).forEach(child=>{const option=document.createElement("option");option.value=String(child.id);option.textContent=child.name+(child.date_of_birth?" · "+child.date_of_birth:"");childSelect.appendChild(option)});
     if(state.selected_child_id)childSelect.value=String(state.selected_child_id);
     childSelect.addEventListener("change",()=>{
       const child=(data.children||[]).find(x=>String(x.id)===String(childSelect.value));
       if(child){
         form.querySelector('[name="child_name"]').value=child.name||"";
         form.querySelector('[name="date_of_birth"]').value=child.date_of_birth||"";
       }
     });
   }catch{}
 }
 loadChildren();
 Object.entries(state).forEach(([name,value])=>{
   const els=form.querySelectorAll('[name="'+name+'"]');
   els.forEach(el=>{if(el.type==="checkbox") el.checked=Array.isArray(value)?value.includes(el.value):!!value; else el.value=value??""});
 });
 const support=form.querySelector('[name="support_prefill_enabled"]');
 function toggleTopics(){topics.hidden=!support.checked}
 support.addEventListener("change",toggleTopics); toggleTopics();
 form.addEventListener("submit",e=>{
   e.preventDefault();
   if(!form.querySelector('[name="consent_to_share_with_organiser"]').checked){status.textContent="Please confirm the booking-sharing permission.";return}
   const data={};
   form.querySelectorAll("input,textarea").forEach(el=>{
     if(el.name==="support_prefill_topics") return;
     data[el.name]=el.type==="checkbox"?el.checked:el.value.trim();
   });
   data.support_prefill_topics=[...form.querySelectorAll('[name="support_prefill_topics"]:checked')].map(x=>x.value);
   data.consent_version="1.1";data.consent_given_at=new Date().toISOString();
   localStorage.setItem(key,JSON.stringify(data));
   status.textContent="Consent profile saved. It can now be attached to Bubba Hub bookings when the booking system is connected.";
 });
});