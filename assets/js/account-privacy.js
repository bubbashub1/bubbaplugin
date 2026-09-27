document.addEventListener("DOMContentLoaded",async()=>{
 const form=document.getElementById("privacyForm"),msg=document.getElementById("privacyMessage"),status=document.getElementById("privacyStatus");
 const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
 let csrf="";
 try{
  const auth=await fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
  if(!auth.authenticated){msg.innerHTML='<strong>Sign in required.</strong> <a href="account.html?next=privacy.html">Sign in to manage privacy choices.</a>';form.style.display="none";return;}
  const r=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),data=await r.json();
  if(!r.ok||!data.ok)throw new Error(data.message||"Could not load privacy settings.");
  csrf=data.csrf||auth.csrf||"";
  const p=data.preferences||{};
  form.elements.marketing.checked=!!p.smsMarketing;
 }catch(e){msg.textContent=e.message||"Could not load your privacy settings.";}
 form.addEventListener("submit",async e=>{
  e.preventDefault();status.textContent="Saving…";
  try{
   const r=await fetch("api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({
    csrf,
    privacy:{
      personalisation:form.elements.personalisation.checked,
      savedPlanner:form.elements.savedPlanner.checked,
      marketing:form.elements.marketing.checked,
      leaderContact:form.elements.leaderContact.checked
    },
    smsMarketing:form.elements.marketing.checked
   })});
   const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.message||data.error||"Could not save privacy choices.");
   csrf=data.csrf||csrf;status.textContent="Saved";setTimeout(()=>status.textContent="",2500);
  }catch(e){status.innerHTML='<span style="color:#963d3d">'+esc(e.message||"Could not save privacy choices.")+"</span>";}
 });
});