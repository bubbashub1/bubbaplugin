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
  const next=qs.get("next")||"/leader/";
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

    [orgWrap,phoneWrap,websiteWrap,termsWrap].forEach(el=>{if(el)el.hidden=!reg;});
    confirm.hidden=!reg;

    const orgInput=fieldInput(orgWrap);
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

  try{
    const r=await fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});
    const d=await r.json();
    csrf=d.csrf||"";
    if(d.leader_authenticated&&((d.user||{}).role==="leader")){
      location.replace(next);
      return;
    }
  }catch(e){}

  setMode(mode);

  const socialMessage=(text,isError=false)=>message(text,isError);
  const socialLogin=async(provider,payload)=>{
    socialMessage("Signing in with "+(provider==="google"?"Google":"Facebook")+"…");
    try{
      const r=await fetch("api/auth.php?action="+encodeURIComponent(provider),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({context:"leader",provider,...payload})});
      const raw=await r.text();
      let d; try{d=JSON.parse(raw);}catch(parseError){throw Error(raw.trim()||("Server returned HTTP "+r.status+" instead of JSON."));}
      if(!r.ok||!d.ok)throw Error(d.message||"Social sign-in could not be completed.");
      location.replace(next);
    }catch(err){socialMessage(err.message||"Please try again.",true);}
  };

  /* Google Identity Services:
     Use the official rendered button rather than prompt(), which can be
     suppressed by browser/account settings and makes the custom button appear
     to do nothing. The GIS callback still posts the verified credential to our
     existing leader-social.php endpoint. */
  const googleHost=document.getElementById("leaderGoogleSignIn");
  const initGoogle=()=>{
    if(!googleHost||!window.google?.accounts?.id)return false;
    const clientId=String(window.BUBBAHUB_GOOGLE_CLIENT_ID||"").trim();
    if(!clientId){
      googleHost.textContent="Google sign-in is not configured";
      googleHost.setAttribute("aria-disabled","true");
      return true;
    }

    google.accounts.id.initialize({
      client_id:clientId,
      callback:response=>{
        if(response?.credential) socialLogin("google",{credential:response.credential});
        else socialMessage("Google sign-in did not return a credential.",true);
      },
      ux_mode:"popup",
      auto_select:false
    });

    googleHost.innerHTML="";
    google.accounts.id.renderButton(googleHost,{
      type:"standard",
      theme:"outline",
      size:"large",
      text:"continue_with",
      shape:"rectangular",
      width:320,
      logo_alignment:"left"
    });
    return true;
  };

  let googleAttempts=0;
  const waitForGoogle=()=>{
    if(initGoogle()||googleAttempts++>40)return;
    setTimeout(waitForGoogle,250);
  };
  waitForGoogle();

  const initFacebook=()=>{if(window.FB&&window.BUBBAHUB_FACEBOOK_APP_ID){try{FB.init({appId:window.BUBBAHUB_FACEBOOK_APP_ID,cookie:true,xfbml:false,version:"v24.0"});}catch(e){}}};
  window.fbAsyncInit=initFacebook;
  if(window.FB)initFacebook();

  const facebookButton=document.getElementById("leaderFacebookSignIn");
  if(facebookButton){
    facebookButton.onclick=()=>{
      if(!window.FB){socialMessage("Facebook sign-in is still loading. Please try again in a moment.",true);return;}
      FB.login(response=>{
        if(response?.authResponse?.accessToken) socialLogin("facebook",{access_token:response.authResponse.accessToken});
        else socialMessage("Facebook sign-in was cancelled.",true);
      },{scope:"email"});
    };
  }

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
      location.replace(next);
    }catch(err){
      message(err.message||"Please try again.",true);
      submit.disabled=false;
    }
  });
});