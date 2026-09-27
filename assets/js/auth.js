document.addEventListener("DOMContentLoaded",async()=>{
  const loginPanel=document.getElementById("accountAuth");
  if(!loginPanel)return;
  const message=document.getElementById("accountAuthMessage");
  const title=document.getElementById("accountAuthTitle");
  const form=document.getElementById("accountAuthForm");
  const modeButtons=loginPanel.querySelectorAll("[data-auth-mode]");
  const passwordConfirm=document.getElementById("confirmPassword");
  const submit=form.querySelector("button[type=submit]");
  const next=new URLSearchParams(location.search).get("next")||"";
  let mode="login";
  let csrf="";

  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));

  const setMessage=(text,error=false)=>{
    message.textContent=text||"";
    message.classList.toggle("is-error",!!error);
  };

  const setMode=nextMode=>{
    mode=nextMode;
    title.textContent=mode==="login"?"Sign in to your family account":"Create your family account";
    submit.textContent=mode==="login"?"Sign in":"Create account";
    passwordConfirm.hidden=mode==="login";
    passwordConfirm.required=mode!=="login";
    modeButtons.forEach(button=>{
      button.classList.toggle("active",button.dataset.authMode===mode);
      button.setAttribute("aria-selected",String(button.dataset.authMode===mode));
    });
    setMessage("");
  };

  const load=async()=>{
    try{
      const response=await fetch("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const data=await response.json();
      csrf=data.csrf||"";
      if(data.authenticated){
        loginPanel.innerHTML="<div class='account-signed-in'><span class='feature-icon'>✓</span><div><span class='eyebrow'>Signed in</span><h2>Family account connected</h2><p>"+esc(data.user.email)+"</p><small>Your My Planner can now sync across devices.</small></div><button class='button button-soft' id='accountLogout' type='button'>Sign out</button></div>";
        document.getElementById("accountLogout").onclick=async()=>{
          try{
            await fetch("api/auth.php?action=logout",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf})});
          }finally{location.reload();}
        };
        return true;
      }
    }catch(e){}
    return false;
  };

  if(await load())return;
  modeButtons.forEach(button=>button.addEventListener("click",()=>setMode(button.dataset.authMode)));
  setMode("login");

  form.addEventListener("submit",async e=>{
    e.preventDefault();
    submit.disabled=true;
    setMessage(mode==="login"?"Signing in…":"Creating your account…");
    try{
      const payload=Object.fromEntries(new FormData(form));
      const response=await fetch("api/auth.php?action="+mode,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(payload)});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||"Something went wrong. Please try again.");
      csrf=data.csrf||"";
      setMessage("Signed in successfully.");
      if(next){location.href=next;return;}
      await load();
    }catch(error){
      setMessage(error.message||"Unable to sign in. Please try again.",true);
      submit.disabled=false;
    }
  });
});