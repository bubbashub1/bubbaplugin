document.addEventListener("DOMContentLoaded",async()=>{
 const form=document.getElementById("profileForm"),signedOut=document.getElementById("profileSignedOut"),status=document.getElementById("profileStatus"),msg=document.getElementById("profileMessage");
 if(!form)return;let csrf="";
 const setMsg=(t,error=false)=>{msg.textContent=t||"";msg.classList.toggle("is-error",error);};
 try{
  const r=await fetch("api/profile.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.message||"Please sign in.");
  csrf=d.csrf||"";Object.entries(d.user||{}).forEach(([k,v])=>{const el=form.elements[k];if(el)el.value=v??"";});
  form.hidden=false;status.textContent="Active";
 }catch(e){status.textContent="Sign in required";signedOut.hidden=false;return;}
 form.addEventListener("submit",async e=>{e.preventDefault();const button=form.querySelector('button[type="submit"]');button.disabled=true;setMsg("Saving…");
  try{const body=Object.fromEntries(new FormData(form));body.csrf=csrf;const r=await fetch("api/profile.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||"Unable to save your profile.");setMsg(d.message||"Your profile has been saved.");status.textContent="Saved";}catch(e){setMsg(e.message||"Unable to save your profile.",true);}finally{button.disabled=false;}
 });
});