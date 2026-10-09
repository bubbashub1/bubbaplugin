document.addEventListener("DOMContentLoaded",async()=>{
  const form=document.getElementById("leaderAuthForm");
  const msg=document.getElementById("leaderAuthMessage");
  const title=document.getElementById("leaderAuthTitle");
  const intro=document.getElementById("leaderAuthIntro");
  const submit=document.getElementById("leaderAuthSubmit");
  const confirm=document.getElementById("leaderConfirmWrap");
  const show=document.getElementById("leaderShowPassword");
  const pass=document.getElementById("leaderPassword");
  const termsWrap=document.getElementById("leaderTermsWrap");
  const tabs=document.querySelectorAll("[data-leader-mode]");
  if(!form)return;

  const qs=new URLSearchParams(location.search);
  const next=(()=>{const candidate=qs.get("next")||"/leader/";try{const u=new URL(candidate,location.origin);return u.origin===location.origin&&/^https?:$/.test(u.protocol)?u.pathname+u.search+u.hash:"/leader/"}catch{return "/leader/"}})();
  let mode=qs.get("mode")==="register"?"register":"login";
  let csrf="";

  const message=(text,isError=false)=>{
    msg.textContent=text;
    msg.classList.toggle("is-error",isError);
  };

  const fieldInput=(wrap)=>wrap?wrap.querySelector("input"):null;

  const setMode=(nextMode)=>{
    mode=nextMode;
    const reg=mode==="register";
    title.textContent=reg?"Create your leader account":"Sign in to your leader portal";
    intro.textContent=reg
      ?"Create your simple Bubba Hub leader account. You can add your class details later."
      :"Manage your classes, venues, schedules and bookings from one dedicated account.";
    submit.textContent=reg?"Create leader account":"Sign in";

    if(termsWrap){termsWrap.hidden=!reg;termsWrap.style.display=reg?"":"none";}
    confirm.hidden=!reg;
    confirm.style.display=reg?"":"none";

    const termsInput=fieldInput(termsWrap);
    const confirmInput=fieldInput(confirm);
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

  setMode(mode);

  try{
    const r=await fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const d=await r.json();
    csrf=d.csrf||"";
    if(d.leader_authenticated&&((d.user||{}).role==="leader")){
      location.replace(next);
      return;
    }
  }catch(e){}

  /* Social sign-in is intentionally disabled for now. Leaders must verify an email address. */
  form.addEventListener("submit",async e=>{
    e.preventDefault();
    const reg=mode==="register";
    message(reg?"Creating your leader account…":"Signing in…");
    submit.disabled=true;

    try{
      const payload=Object.fromEntries(new FormData(form));
      payload.csrf=csrf;
      if(!reg)payload.context="leader";

      const endpoint=reg?"api/auth.php?action=leader_register":"api/auth.php?action=login";
      const options={
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json",Accept:"application/json"},
        body:JSON.stringify(payload)
      };

      const r=await fetch(endpoint,options);
      const raw=await r.text();
      let d; try{d=JSON.parse(raw);}catch(parseError){throw Error(raw.trim()||("Server returned HTTP "+r.status+" instead of JSON."));}
      if(!r.ok||!d.ok)throw Error(d.message||"We could not complete that request.");

      if((d.user||{}).role!=="leader")throw Error("This account is not a class leader account.");

      csrf=d.csrf||csrf;
      if(reg){
        message(d.verification_email_sent===false
          ?"Your account was created, but the verification email could not be sent. Please contact support before trying to sign in."
          :(d.message||"Account created. Check your email for the verification link before signing in."));
        submit.disabled=false;
        return;
      }
      location.replace(next);
    }catch(err){
      message(err.message||"Please try again.",true);
      submit.disabled=false;
    }
  });
});