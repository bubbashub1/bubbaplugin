document.addEventListener("DOMContentLoaded",async()=>{
 const form=document.getElementById("profileForm"),signedOut=document.getElementById("profileSignedOut"),status=document.getElementById("profileStatus"),msg=document.getElementById("profileMessage");
 if(!form)return;let csrf="";
 const toUkDate=value=>{const m=String(value||"").match(/^(\d{4})-(\d{2})-(\d{2})/);return m?m[3]+"/"+m[2]+"/"+m[1]:String(value||"")};
 const toIsoDate=value=>{const s=String(value||"").trim();if(!s)return "";const m=s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);if(!m)throw new Error("Enter your date of birth as DD/MM/YYYY.");const day=Number(m[1]),month=Number(m[2]),year=Number(m[3]),d=new Date(year,month-1,day);if(d.getFullYear()!==year||d.getMonth()!==month-1||d.getDate()!==day)throw new Error("Please enter a valid date of birth.");return year+"-"+String(month).padStart(2,"0")+"-"+String(day).padStart(2,"0")};
 const setMsg=(t,error=false)=>{msg.textContent=t||"";msg.classList.toggle("is-error",error);};
 try{
  const r=await fetch("../api/profile.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const d=await r.json();
  if(!r.ok||!d.ok)throw new Error(d.message||"Please sign in.");
  csrf=d.csrf||"";Object.entries(d.user||{}).forEach(([k,v])=>{const el=form.elements[k];if(el)el.value=k==="date_of_birth"?toUkDate(v):v??"";});const a=d.user?.address||{};Object.entries(a).forEach(([k,v])=>{const el=form.elements[k];if(el)el.value=v??"";});
  form.hidden=false;status.textContent="Active";
 }catch(e){status.textContent="Sign in required";signedOut.hidden=false;return;}
 form.addEventListener("submit",async e=>{e.preventDefault();const button=form.querySelector('button[type="submit"]');button.disabled=true;setMsg("Saving…");
  try{const raw=Object.fromEntries(new FormData(form));const body={first_name:raw.first_name,last_name:raw.last_name,date_of_birth:toIsoDate(raw.date_of_birth),address:{address_line1:raw.address_line1,address_line2:raw.address_line2,city:raw.city,county:raw.county,postcode:raw.postcode,country:raw.country}};body.csrf=csrf;const r=await fetch("../api/profile.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(body)});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||"Unable to save your profile.");setMsg(d.message||"Your profile has been saved.");status.textContent="Saved";}catch(e){setMsg(e.message||"Unable to save your profile.",true);}finally{button.disabled=false;}
 });
});