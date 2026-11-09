document.addEventListener("DOMContentLoaded",()=>{
 const form=document.getElementById("consentForm");
 if(!form)return;
 const topics=document.getElementById("supportTopics");
 const status=document.getElementById("consentStatus");
 const childSelect=form.querySelector("#consentChildProfile");
 let csrf="", children=[], profiles=[];
 const textFields=["child_name","date_of_birth","gender","additional_needs","allergies","medical_conditions","medications","dietary_requirements","emergency_information","emergency_contact_name","emergency_contact_phone"];
 const checkboxFields=["photo_activity","photo_bubbahub","photo_organiser_marketing","support_prefill_enabled","consent_to_share_with_organiser"];
 function toggleTopics(){topics.hidden=!form.querySelector('[name="support_prefill_enabled"]').checked}
 function applyProfile(profile,child){
   form.reset();
   textFields.forEach(name=>{const el=form.querySelector('[name="'+name+'"]');if(el)el.value=profile?.[name]??(name==="child_name"?(child?.name||""):name==="date_of_birth"?(child?.date_of_birth||""):"")});
   checkboxFields.forEach(name=>{const el=form.querySelector('[name="'+name+'"]');if(el)el.checked=!!Number(profile?.[name]||0)});
   const selectedTopics=String(profile?.support_prefill_topics||"").split(",").filter(Boolean);
   form.querySelectorAll('[name="support_prefill_topics"]').forEach(el=>el.checked=selectedTopics.includes(el.value));
   if(childSelect)childSelect.value=child?.id?String(child.id):"";
   toggleTopics();
 }
 async function load(){
   try{
     const childRes=await fetch("/api/my-hub.php?view=family",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
     if(childRes.ok){
       const childData=await childRes.json();
       children=childData.children||[];
       children.forEach(child=>childSelect.add(new Option(child.name+(child.date_of_birth?" · "+child.date_of_birth:""),String(child.id))));
     }
     const res=await fetch("/api/consent-profile.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
     const data=await res.json().catch(()=>({}));
     if(res.ok&&data.ok){
       csrf=data.csrf||"";
       profiles=data.profiles||[];
       const profile=profiles[0]||null;
       const child=profile?.child_id?children.find(c=>String(c.id)===String(profile.child_id)):null;
       applyProfile(profile,child);
       status.textContent=profile?"Your saved consent profile has been loaded from your family account.":"Choose a child and complete the form, then save it to your family account.";
     }else if(res.status===401){
       status.textContent="Please sign in to save a private consent profile to your family account.";
     }else{
       status.textContent=data.message||"Consent profile storage is not ready yet.";
     }
   }catch(e){status.textContent="Could not load your saved consent profile. Please refresh and try again."}
 }
 childSelect?.addEventListener("change",()=>{
   const child=children.find(c=>String(c.id)===String(childSelect.value));
   const profile=profiles.find(p=>String(p.child_id||"")===String(child?.id||""));
   applyProfile(profile||null,child||null);
 });
 form.querySelector('[name="support_prefill_enabled"]').addEventListener("change",toggleTopics);
 form.addEventListener("submit",async e=>{
   e.preventDefault();
   if(!csrf){status.textContent="Please sign in to your family account before saving this consent profile.";return}
   if(!form.querySelector('[name="consent_to_share_with_organiser"]').checked){status.textContent="Please confirm the booking-sharing permission.";return}
   const data={child_id:Number(childSelect?.value||0)};
   form.querySelectorAll("input,textarea").forEach(el=>{
     if(el.name==="support_prefill_topics")return;
     data[el.name]=el.type==="checkbox"?el.checked:el.value.trim();
   });
   data.support_prefill_topics=[...form.querySelectorAll('[name="support_prefill_topics"]:checked')].map(x=>x.value);
   status.textContent="Saving securely to your family account…";
   const button=form.querySelector('button[type="submit"]');button.disabled=true;
   try{
     const res=await fetch("/api/consent-profile.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify({...data,csrf})});
     const result=await res.json().catch(()=>({}));
     if(!res.ok||!result.ok)throw new Error(result.message||result.error||"Could not save your consent profile.");
     csrf=result.csrf||csrf;
     status.textContent="Consent profile saved securely to your family account. It will be checked again when you make a booking.";
     await load();
   }catch(err){status.textContent=err.message||"Could not save your consent profile."}
   finally{button.disabled=false}
 });
 toggleTopics();
 load();
});