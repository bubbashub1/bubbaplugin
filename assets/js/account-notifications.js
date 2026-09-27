document.addEventListener("DOMContentLoaded",async()=>{
  const status=document.getElementById("notificationStatus"),message=document.getElementById("notificationMessage"),button=document.getElementById("saveNotifications");
  let csrf="";
  const ids=["emailEnabled","smsEnabled","pushEnabled","smsMarketing"];
  const defaults={emailEnabled:true,smsEnabled:false,pushEnabled:false,smsMarketing:false,plannerReminders:true,bookingUpdates:true,savedSearches:false,supportReplies:true,eventReminders:true,phone:""};
  const setMessage=(text,error=false)=>{message.textContent=text||"";message.className="library-message"+(error?" is-error":"")};
  const apply=p=>{
    ids.forEach(id=>{const el=document.getElementById(id);if(el)el.checked=!!p[id]});
    document.getElementById("phone").value=p.phone||"";
    document.querySelectorAll("[data-pref]").forEach(el=>el.checked=!!p[el.dataset.pref]);
  };
  const collect=()=>{
    const p={};
    ids.forEach(id=>p[id]=document.getElementById(id).checked);
    p.phone=document.getElementById("phone").value.trim();
    document.querySelectorAll("[data-pref]").forEach(el=>p[el.dataset.pref]=el.checked);
    return p;
  };
  const loadNewsletter=async()=>{
    const statusEl=document.getElementById("newsletterStatus");
    try{
      const response=await fetch("api/newsletter.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Could not load Round Up settings.");
      const n=data.newsletter||{};
      document.getElementById("newsletterEnabled").checked=!!n.enabled;
      document.getElementById("newsletterFrequency").value=["daily","weekly","monthly"].includes(n.frequency)?n.frequency:"weekly";
      statusEl.textContent=n.lastSentAt?"Last sent: "+new Date(n.lastSentAt.replace(" ","T")).toLocaleString("en-GB"):"No Round Up sent yet.";
      csrf=data.csrf||csrf;
    }catch(e){statusEl.textContent=e.message||"Round Up settings unavailable."}
  };
  try{
    const response=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const data=await response.json();
    if(response.status===401||data.error==="login_required"){location.href="account.html?next="+encodeURIComponent("notifications.html");return}
    if(!response.ok||!data.ok)throw new Error(data.message||"Could not load notification settings.");
    csrf=data.csrf||"";
    apply({...defaults,...(data.preferences||{})});
    status.textContent="Saved to your account";
    await loadNewsletter();
  }catch(e){status.textContent="Unavailable";setMessage(e.message||"Could not load notification settings.",true)}
  button.addEventListener("click",async()=>{
    button.disabled=true;setMessage("Saving…");
    try{
      const p=collect();
      p.csrf=csrf;
      const response=await fetch("api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Could not save notification settings.");
      csrf=data.csrf||csrf;
      const nResponse=await fetch("api/newsletter.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf,enabled:document.getElementById("newsletterEnabled").checked,frequency:document.getElementById("newsletterFrequency").value})});
      const nData=await nResponse.json();
      if(!nResponse.ok||!nData.ok)throw new Error(nData.message||"Could not save Round Up settings.");
      csrf=nData.csrf||csrf;
      status.textContent="Saved to your account";
      const n=nData.newsletter||{};
      document.getElementById("newsletterStatus").textContent=n.enabled?"Round Up set to "+n.frequency+".":"Round Up is switched off.";
      setMessage("Notification settings saved.");
    }catch(e){setMessage(e.message||"Could not save notification settings.",true)}
    finally{button.disabled=false}
  });
});