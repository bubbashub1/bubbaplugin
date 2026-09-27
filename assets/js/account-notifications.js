document.addEventListener("DOMContentLoaded",async()=>{
  const status=document.getElementById("notificationStatus"),message=document.getElementById("notificationMessage"),button=document.getElementById("saveNotifications"),pushStatus=document.getElementById("pushStatus");
  let csrf="";
  const ids=["emailEnabled","pushEnabled"];
  const defaults={emailEnabled:true,pushEnabled:false,plannerReminders:true,bookingUpdates:true,savedSearches:false,supportReplies:true,eventReminders:true};
  const setMessage=(text,error=false)=>{message.textContent=text||"";message.className="library-message"+(error?" is-error":"")};
  const apply=p=>{
    ids.forEach(id=>{const el=document.getElementById(id);if(el)el.checked=!!p[id]});
    document.querySelectorAll("[data-pref]").forEach(el=>el.checked=!!p[el.dataset.pref]);
  };
  const collect=()=>{
    const p={}; ids.forEach(id=>p[id]=document.getElementById(id).checked);
    document.querySelectorAll("[data-pref]").forEach(el=>p[el.dataset.pref]=el.checked);
    return p;
  };
  const pushApi=async(method,body=null)=>{
    const options={method,credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json","X-CSRF-Token":csrf}};
    if(body!==null){options.headers["Content-Type"]="application/json";options.body=JSON.stringify(body)}
    const response=await fetch("api/push.php",options),data=await response.json();
    if(!response.ok||!data.ok)throw new Error(data.message||data.error||"Push notifications are unavailable.");
    csrf=data.csrf||csrf; return data;
  };
  const enablePush=async()=>{
    if(!("serviceWorker" in navigator)||!("PushManager" in window)||!("Notification" in window)) throw new Error("Push notifications are not supported by this browser.");
    const configResponse=await fetch("api/push.php?action=config",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),config=await configResponse.json();
    if(!configResponse.ok||!config.ok)throw new Error(config.message||"Push notifications are not configured yet.");
    const permission=Notification.permission==="granted"? "granted":await Notification.requestPermission();
    if(permission!=="granted")throw new Error("Push permission was not granted. You can enable it in your browser settings.");
    const registration=await navigator.serviceWorker.register("/push-sw.js",{scope:"/"});
    await navigator.serviceWorker.ready;
    let subscription=await registration.pushManager.getSubscription();
    if(!subscription)subscription=await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:Uint8Array.from(atob(config.publicKey.replace(/-/g,"+").replace(/_/g,"/")),c=>c.charCodeAt(0))});
    const key=subscription.getKey("p256dh"),auth=subscription.getKey("auth");
    const toBase64Url=buffer=>btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\\+/g,"-").replace(/\\//g,"_").replace(/=+$/,"");
    await pushApi("POST",{endpoint:subscription.endpoint,p256dh:toBase64Url(key),auth:toBase64Url(auth),contentEncoding:subscription.options&&subscription.options.applicationServerKey?"aes128gcm":"aes128gcm"});
    pushStatus.textContent="Push notifications are enabled on this device.";
  };
  const disablePush=async()=>{
    if("serviceWorker" in navigator){const registration=await navigator.serviceWorker.getRegistration("/push-sw.js");if(registration){const subscription=await registration.pushManager.getSubscription();if(subscription){const endpoint=subscription.endpoint;await subscription.unsubscribe();await pushApi("DELETE",{endpoint})}else await pushApi("DELETE",{})}else await pushApi("DELETE",{})}else await pushApi("DELETE",{});
    pushStatus.textContent="Push notifications are switched off.";
  };
  const loadNewsletter=async()=>{const statusEl=document.getElementById("newsletterStatus");try{const response=await fetch("api/newsletter.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||"Could not load Round Up settings.");const x=data.newsletter||{};document.getElementById("newsletterEnabled").checked=!!x.enabled;document.getElementById("newsletterFrequency").value=["daily","weekly","monthly"].includes(x.frequency)?x.frequency:"weekly";statusEl.textContent=x.lastSentAt?"Last sent: "+new Date(x.lastSentAt.replace(" ","T")).toLocaleString("en-GB"):"No Round Up sent yet.";csrf=data.csrf||csrf}catch(e){statusEl.textContent=e.message||"Round Up settings unavailable."}};
  try{
    const response=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),data=await response.json();
    if(response.status===401||data.error==="login_required"){location.href="account.html?next="+encodeURIComponent("notifications.html");return}
    if(!response.ok||!data.ok)throw new Error(data.message||"Could not load notification settings.");
    csrf=data.csrf||"";apply({...defaults,...(data.preferences||{})});
    pushStatus.textContent=data.preferences&&data.preferences.pushEnabled?"Push notifications are enabled on your account.":"Push notifications are currently switched off.";
    status.textContent="Saved to your account";await loadNewsletter();
  }catch(e){status.textContent="Unavailable";setMessage(e.message||"Could not load notification settings.",true)}
  document.getElementById("pushEnabled").addEventListener("change",async e=>{if(!e.target.checked)return;try{await enablePush()}catch(err){e.target.checked=false;pushStatus.textContent=err.message;setMessage(err.message,true)}});
  button.addEventListener("click",async()=>{
    button.disabled=true;setMessage("Saving…");
    try{
      const p=collect();
      if(p.pushEnabled) await enablePush(); else await disablePush();
      p.csrf=csrf;
      const response=await fetch("api/preferences.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)}),data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Could not save notification settings.");
      csrf=data.csrf||csrf;
      const nResponse=await fetch("api/newsletter.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf,enabled:document.getElementById("newsletterEnabled").checked,frequency:document.getElementById("newsletterFrequency").value})}),nData=await nResponse.json();
      if(!nResponse.ok||!nData.ok)throw new Error(nData.message||"Could not save Round Up settings.");
      csrf=nData.csrf||csrf;status.textContent="Saved to your account";const nn=nData.newsletter||{};document.getElementById("newsletterStatus").textContent=nn.enabled?"Round Up set to "+nn.frequency+".":"Round Up is switched off.";setMessage("Notification settings saved.");
    }catch(e){setMessage(e.message||"Could not save notification settings.",true)}finally{button.disabled=false}
  });
});