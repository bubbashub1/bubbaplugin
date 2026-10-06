document.addEventListener("DOMContentLoaded",async()=>{
  const form=document.getElementById("leaderAuthForm");
  const msg=document.getElementById("leaderAuthMessage");
  const title=document.getElementById("leaderAuthTitle");
  const intro=document.getElementById("leaderAuthIntro");
  const submit=document.getElementById("leaderAuthSubmit");
  const confirm=document.getElementById("leaderConfirmWrap");
  const show=document.getElementById("leaderShowPassword");
  const pass=document.getElementById("leaderPassword");
  const orgWrap=document.getElementById("leaderOrganisationWrap");
  const phoneWrap=document.getElementById("leaderPhoneWrap");
  const websiteWrap=document.getElementById("leaderWebsiteWrap");
  const termsWrap=document.getElementById("leaderTermsWrap");
  const tabs=document.querySelectorAll("[data-leader-mode]");
  if(!form)return;

  const qs=new URLSearchParams(location.search);
  const next=qs.get("next")||"/leader/";
  let mode=qs.get("mode")==="register"?"register":"login";
  let csrf="";

  const message=(text,isError=false)=>{
    msg.textContent=text;
    msg.classList.toggle("is-error",isError);
  };

  const fieldInput=(wrap)=>{
    if(!wrap)return null;
    return wrap.querySelector("input");
  };

  const setMode=(nextMode)=>{
    mode=nextMode;
    const reg=mode==="register";
    title.textContent=reg?"Create your leader account":"Sign in to your leader portal";
    intro.textContent=reg
      ?"Set up a dedicated Bubba Hub account for your class, business or family activity."
      :"Manage your classes, venues, schedules and bookings from one dedicated account.";
    submit.textContent=reg?"Create leader account":"Sign in";

    [orgWrap,phoneWrap,websiteWrap,termsWrap].forEach(el=>{if(el)el.hidden=!reg;});
    confirm.hidden=!reg;

    const orgInput=fieldInput(orgWrap);
    const termsInput=fieldInput(termsWrap);
    const confirmInput=fieldInput(confirm);
    if(orgInput)orgInput.required=reg;
    if(confirmInput)confirmInput.required=reg;
    if(termsInput)termsInput.required=reg;

    pass.autocomplete=reg?"new-password":"current-password";
    tabs.forEach(tab=>{
      const active=tab.dataset.leaderMode===mode;
      tab.classList.toggle("active",active);
      tab.setAttribute("aria-selected",String(active));
    });
    message("");
  };

  show.onclick=()=>{
    const visible=pass.type==="password";
    pass.type=visible?"text":"password";
    show.textContent=visible?"Hide password":"Show password";
  };

  tabs.forEach(tab=>tab.addEventListener("click",()=>setMode(tab.dataset.leaderMode)));

  try{
    const r=await fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const d=await r.json();
    csrf=d.csrf||"";
    if(d.authenticated&&((d.user||{}).role==="leader"||d.is_admin)){
      location.replace(next);
      return;
    }
  }catch(e){}

  setMode(mode);

  form.addEventListener("submit",async e=>{
    e.preventDefault();
    const reg=mode==="register";
    message(reg?"Creating your leader account…":"Signing in…");
    submit.disabled=true;

    try{
      const payload=Object.fromEntries(new FormData(form));
      payload.csrf=csrf;

      const endpoint=reg?"api/leader-signup.php":"api/auth.php?action=login";
      const options={
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json",Accept:"application/json"},
        body:JSON.stringify(payload)
      };

      const r=await fetch(endpoint,options);
      const d=await r.json();
      if(!r.ok||!d.ok)throw Error(d.message||"We could not complete that request.");

      if((d.user||{}).role!=="leader")throw Error("This account is not a class leader account.");

      csrf=d.csrf||csrf;
      location.replace(next);
    }catch(err){
      message(err.message||"Please try again.",true);
      submit.disabled=false;
    }
  });
});