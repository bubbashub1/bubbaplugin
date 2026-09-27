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
  try{
    const response=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const data=await response.json();
    if(response.status===401||data.error==="login_required"){location.href="account.html?next="+encodeURIComponent("notifications.html");return}
    if(!response.ok||!data.ok)throw new Error(data.message||"Could not load notification settings.");
    csrf=data.csrf||"";
    apply({...defaults,...(data.preferences||{})});
    status.textContent="Saved to your account";
  }catch(e){status.textContent="Unavailable";setMessage(e.message||"Could not load notification settings.",true)}
  button.addEventListener("click",async()=>{
    button.disabled=true;setMessage("Saving…");
    try{
      const p=collect();
      const response=await fetch("api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Could not save notification settings.");
      csrf=data.csrf||csrf;status.textContent="Saved to your account";setMessage("Notification settings saved.");
    }catch(e){setMessage(e.message||"Could not save notification settings.",true)}
    finally{button.disabled=false}
  });
});