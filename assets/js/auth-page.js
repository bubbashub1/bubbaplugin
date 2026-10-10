document.addEventListener("DOMContentLoaded",async()=>{const form=document.getElementById("authForm"),msg=document.getElementById("authMessage"),title=document.getElementById("authTitle"),intro=document.getElementById("authIntro"),submit=document.getElementById("authSubmit"),confirm=document.getElementById("confirmWrap"),terms=document.getElementById("termsWrap"),show=document.getElementById("showPassword"),pass=document.getElementById("authPassword"),google=document.getElementById("googleSignIn");if(!form)return;const qs=new URLSearchParams(location.search),accountType=document.getElementById("authAccountType"),next=(()=>{const candidate=qs.get("next");if(!candidate)return "";try{const decoded=decodeURIComponent(candidate);const u=new URL(decoded,location.origin);return u.origin===location.origin&&/^https?:$/.test(u.protocol)&&!/%2f|%5c/i.test(u.pathname)?u.pathname+u.search+u.hash:""}catch{return ""}})(),eventMode=qs.get("event")==="1",tabs=document.querySelectorAll("[data-auth-mode]");let mode=qs.get("mode")==="register"?"register":"login",csrf="";const message=(t,e=false)=>{msg.textContent=t;msg.classList.toggle("is-error",e)};const readJson=async r=>{const raw=await r.text();if(!raw.trim())throw Error("The account service returned an empty response (HTTP "+r.status+"). Please try again or check the server error log.");let data;try{data=JSON.parse(raw)}catch{throw Error("The account service returned an invalid response (HTTP "+r.status+"). Please check the PHP error log.")}return data};const setMode=m=>{mode=m;const forgot=document.getElementById("forgotWrap"),names=document.getElementById("nameWrap"),eventCode=document.getElementById("eventCodeWrap");if(forgot)forgot.hidden=m!=="login";const reg=m==="register";if(accountType){accountType.disabled=false;}if(names)names.hidden=!reg||(accountType&&accountType.value==="leader");if(eventCode)eventCode.hidden=!reg||(accountType&&accountType.value==="leader");title.textContent=reg?"Create your Bubba Hub account":"Sign in to your Bubba Hub account";intro.textContent=reg?"Choose Family or Class leader, then create your account with your email and password.":"Access your family hub or class leader portal from one sign-in page.";submit.textContent=reg?"Create account":"Sign in";confirm.hidden=!reg;confirm.querySelector("input").required=reg;terms.hidden=!reg;terms.querySelector("input").required=reg;tabs.forEach(t=>{const a=t.dataset.authMode===m;t.classList.toggle("active",a);t.setAttribute("aria-selected",String(a))});message("")};show.onclick=()=>{const on=pass.type==="password";pass.type=on?"text":"password";show.textContent=on?"Hide password":"Show password"};tabs.forEach(t=>t.onclick=()=>setMode(t.dataset.authMode));if(accountType){accountType.value=qs.get("context")==="leader"?"leader":"family";accountType.addEventListener("change",()=>setMode(mode));}try{const r=await fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}),d=await readJson(r);csrf=d.csrf||"";if(d.authenticated){const wantsLeader=accountType?.value==="leader"||next.startsWith("/leader/");if(wantsLeader){if(d.leader_authenticated&&d.user?.role==="leader"){location.replace(next.startsWith("/leader/")?next:"/leader/");return}message("Your leader session is not active. Please sign in as a class leader.",true)}else if(d.family_authenticated||d.is_admin){location.replace(next&&!next.startsWith("/leader/")?next:"/account.html");return}}}catch(e){}setMode(eventMode?"register":mode);if(eventMode){const ec=form.elements.event_code;if(ec)ec.value="BUBBAEVENT26";}

const verifyWrap=document.getElementById("leaderVerifyWrap"),verifyButton=document.getElementById("resendLeaderVerification"),verifyMessage=document.getElementById("leaderVerifyMessage");
const syncVerify=()=>{if(verifyWrap)verifyWrap.hidden=accountType?.value!=="leader";};
accountType?.addEventListener("change",syncVerify);
tabs.forEach(t=>t.addEventListener("click",syncVerify));
syncVerify();
if(verifyButton)verifyButton.addEventListener("click",async()=>{
 const email=String(form.elements.email?.value||"").trim();
 if(!email||!form.elements.email.checkValidity()){verifyMessage.textContent="Enter your leader account email address first.";return;}
 verifyButton.disabled=true;verifyMessage.textContent="Requesting verification email…";
 try{
  if(!csrf){const r=await fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store"});const d=await readJson(r);csrf=d.csrf||"";}
  const r=await fetch("/api/auth.php?action=resend_verification",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({email,csrf,context:"leader"})});
  const d=await readJson(r);
  verifyMessage.textContent=d.message||(r.ok?"If an unverified account exists, a link will be sent.":"Please try again later.");
 }catch(e){verifyMessage.textContent=e.message||"Could not request a verification email.";}
 finally{verifyButton.disabled=false;}
});

if(google){
  google.classList.add("social-signin-host");
  const loadGoogle=async()=>{
    const fallbackText="Continue with Google";
    try{
      google.disabled=true;
      google.setAttribute("aria-busy","true");
      google.innerHTML='<span class="google-mark" aria-hidden="true">G</span><span>Connecting to Google…</span>';
      const started=Date.now();
      while((!window.BUBBAHUB_GOOGLE_CLIENT_ID||!window.google?.accounts?.id)&&Date.now()-started<12000){
        await new Promise(r=>setTimeout(r,150));
      }
      if(!window.BUBBAHUB_GOOGLE_CLIENT_ID)throw Error("Google sign-in is not configured yet.");
      if(!window.google?.accounts?.id)throw Error("Google sign-in could not be loaded. Please check your connection and try again.");
      window.google.accounts.id.initialize({client_id:window.BUBBAHUB_GOOGLE_CLIENT_ID,callback:async response=>{
        try{
          const r=await fetch("/api/auth.php?action=google",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify({credential:response.credential,csrf,context:"family"})});
          const d=await readJson(r);
          if(!r.ok||!d.ok)throw Error(d.message||"Google sign-in could not be completed.");
          location.href=next||"account.html";
        }catch(e){message(e.message||"Please try again.",true);google.disabled=false}
      }});
      google.textContent="";
      google.className="social-signin-host";
      google.disabled=false;
      google.removeAttribute("aria-busy");
      window.google.accounts.id.renderButton(google,{type:"standard",theme:"outline",size:"large",text:"continue_with",shape:"rectangular",width:400});
    }catch(e){
      google.disabled=false;
      google.removeAttribute("aria-busy");
      google.className="google-signin-button";
      google.innerHTML='<span class="google-mark" aria-hidden="true">G</span><span>'+fallbackText+'</span>';
      google.onclick=async()=>{
        message("Trying Google sign-in again…");
        google.disabled=true;
        await loadGoogle();
      };
      message(e.message||"Google sign-in could not be loaded. Please try again.",true);
    }
  };
  loadGoogle();
}


form.addEventListener("submit",async e=>{e.preventDefault();message(mode==="login"?"Signing in…":"Creating your account…");submit.disabled=true;try{const p=Object.fromEntries(new FormData(form));delete p.terms;p.csrf=csrf;const leader=accountType&&accountType.value==="leader";if(mode==="register"&&leader){p.terms=!!document.getElementById("terms")?.checked;p.organisation_name="New Bubba Hub Leader";}const action=mode==="register"&&leader?"leader_register":mode;const r=await fetch("/api/auth.php?action="+action,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify(p)}),d=await readJson(r);if(!r.ok||!d.ok)throw Error(d.message||"We could not complete that request.");csrf=d.csrf||csrf;if(mode==="register"&&leader){message(d.verification_email_sent===false?"Your leader account was created, but we could not send the verification email. Please contact support.":"Your leader account was created. Check your email for the verification link.");submit.disabled=false;return;}if(mode==="login"&&p.context==="leader"){const check=await fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const session=await readJson(check);if(!check.ok||!session.leader_authenticated||session.user?.role!=="leader"){message("Sign-in succeeded, but your leader session is not active. Please check email verification or contact support.",true);submit.disabled=false;return}location.replace(next.startsWith("/leader/")?next:"/leader/");return}location.href=next&&!next.startsWith("/leader/")?next:"/account.html"}catch(e){message(e.message||"Please try again.",true);submit.disabled=false}})});