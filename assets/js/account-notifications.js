document.addEventListener("DOMContentLoaded",async()=>{
  const status=document.getElementById("notificationStatus"),message=document.getElementById("notificationMessage"),button=document.getElementById("saveNotifications"),pushStatus=document.getElementById("pushStatus");
  let csrf="";
  const ids=["emailEnabled","pushEnabled"];
  const defaults={emailEnabled:true,pushEnabled:false,plannerReminders:true,bookingUpdates:true,savedSearches:false,supportReplies:true,eventReminders:true};
  const setMessage=(text,error=false)=>{message.textContent=text||"";message.className="library-message"+(error?" is-error":"")};
  const apply=p=>{ids.forEach(id=>{const el=document.getElementById(id);if(el)el.checked=!!p[id]});document.querySelectorAll("[data-pref]").forEach(el=>el.checked=!!p[el.dataset.pref]);};
  const collect=()=>{const p={};ids.forEach(id=>p[id]=document.getElementById(id).checked);document.querySelectorAll("[data-pref]").forEach(el=>p[el.dataset.pref]=el.checked);return p;};
  const getOneSignal=async()=>{if(!window.__bubbaOneSignalPromise)throw new Error("OneSignal is not loaded yet.");return await window.__bubbaOneSignalPromise;};
  const syncOneSignalUser=async(data)=>{const OneSignal=await getOneSignal();if(data.userId)await OneSignal.login(String(data.userId));if(data.email&&data.preferences?.emailEnabled){await OneSignal.User.addEmail(String(data.email));}return OneSignal;};
  const setPushSubscription=async(enabled)=>{
    const OneSignal=await getOneSignal();
    if(!OneSignal.Notifications.isPushSupported())throw new Error("Push notifications are not supported by this browser.");
    if(enabled){
      await OneSignal.User.PushSubscription.optIn();
      if(!OneSignal.User.PushSubscription.optedIn)throw new Error("Push permission was not granted. You can enable it in your browser settings.");
      pushStatus.textContent="Push notifications are enabled on this device.";
    }else{
      await OneSignal.User.PushSubscription.optOut();
      pushStatus.textContent="Push notifications are switched off.";
    }
  };
  const loadNewsletter=async()=>{const statusEl=document.getElementById("newsletterStatus");try{const response=await fetch("../api/newsletter.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||"Could not load Round Up settings.");const x=data.newsletter||{};document.getElementById("newsletterEnabled").checked=!!x.enabled;document.getElementById("newsletterFrequency").value=["daily","weekly","monthly"].includes(x.frequency)?x.frequency:"weekly";statusEl.textContent=x.lastSentAt?"Last sent: "+new Date(x.lastSentAt.replace(" ","T")).toLocaleString("en-GB"):"No Round Up sent yet.";csrf=data.csrf||csrf}catch(e){statusEl.textContent=e.message||"Round Up settings unavailable."}};
  try{
    const response=await fetch("../api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),data=await response.json();
    if(response.status===401||data.error==="login_required"){status.textContent="Sign in required";setMessage("Please sign in to manage your notifications.",true);const link=document.createElement("a");link.href="../auth.html?next="+encodeURIComponent(location.pathname+location.search+location.hash);link.textContent="Sign in to continue →";link.className="button button-primary";message.append(" ",link);button.disabled=true;return;}
    if(!response.ok||!data.ok)throw new Error(data.message||"Could not load notification settings.");
    csrf=data.csrf||"";apply({...defaults,...(data.preferences||{})});
    try{const OneSignal=await syncOneSignalUser(data);const optedIn=!!OneSignal.User.PushSubscription.optedIn;document.getElementById("pushEnabled").checked=optedIn;pushStatus.textContent=optedIn?"Push notifications are enabled on this device.":"Push notifications are currently switched off.";}catch(e){pushStatus.textContent="Push notifications are ready to be enabled.";}
    status.textContent="Saved to your account";await loadNewsletter();
  }catch(e){status.textContent="Unavailable";setMessage(e.message||"Could not load notification settings.",true);}
  document.getElementById("pushEnabled").addEventListener("change",async e=>{try{await setPushSubscription(e.target.checked);}catch(err){e.target.checked=!e.target.checked;pushStatus.textContent=err.message;setMessage(err.message,true);}});
  button.addEventListener("click",async()=>{
    button.disabled=true;setMessage("Saving…");
    try{
      const p=collect();
      p.csrf=csrf;
      const response=await fetch("../api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)}),data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Could not save notification settings.");
      csrf=data.csrf||csrf;
      try{
        if(p.pushEnabled) await setPushSubscription(true);
      }catch(pushError){
        pushStatus.textContent=pushError.message||"Push could not be enabled on this device.";
      }
      const nResponse=await fetch("../api/newsletter.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf,enabled:document.getElementById("newsletterEnabled").checked,frequency:document.getElementById("newsletterFrequency").value})}),nData=await nResponse.json();
      if(!nResponse.ok||!nData.ok)throw new Error(nData.message||"Could not save Round Up settings.");
      csrf=nData.csrf||csrf;
      status.textContent="Saved to your account";
      const nn=nData.newsletter||{};
      document.getElementById("newsletterStatus").textContent=nn.enabled?"Round Up set to "+nn.frequency+".":"Round Up is switched off.";
      setMessage("Notification settings saved.");
    }catch(e){setMessage(e.message||"Could not save notification settings.",true);}finally{button.disabled=false;}
  });
});
