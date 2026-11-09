document.addEventListener("DOMContentLoaded",()=>{
 const card=document.querySelector(".auth-card"),layout=document.querySelector(".auth-layout"),type=document.getElementById("authAccountType");
 if(!card||!layout||!type)return;
 const params=new URLSearchParams(location.search);
 const choices=document.createElement("section");choices.className="auth-choice-grid";choices.setAttribute("aria-label","Choose your Bubba Hub account");
 choices.innerHTML='<button type="button" class="auth-choice auth-choice-family" data-open-auth="family"><span class="auth-choice-icon" aria-hidden="true">👨‍👩‍👧‍👦</span><h2>Family account</h2><p>Find local activities, save your favourites, build your family planner and keep everything in one place.</p><span class="auth-choice-cta">Family sign in or create an account →</span></button><button type="button" class="auth-choice auth-choice-leader" data-open-auth="leader"><span class="auth-choice-icon" aria-hidden="true">🎨</span><h2>Class leader account</h2><p>Manage your activity listings, share your timetable and help local families discover your classes.</p><span class="auth-choice-cta">Class leader sign in or register →</span></button>';
 layout.parentNode.insertBefore(choices,layout);
 const foot=document.createElement("p");foot.className="auth-choice-foot";foot.innerHTML='Part of the Bubba Hub team? <button type="button" data-open-auth="volunteer">Team / volunteer sign in</button>';choices.insertAdjacentElement("afterend",foot);
 const backdrop=document.createElement("div");backdrop.className="auth-modal-backdrop";backdrop.id="authModalBackdrop";backdrop.innerHTML='<section class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="authModalTitle"><button type="button" class="auth-modal-close" aria-label="Close sign in">×</button><div class="auth-modal-heading"><h2 id="authModalTitle">Welcome back</h2><p id="authModalIntro">Choose how you use Bubba Hub.</p></div><div class="auth-role-switch" aria-label="Account type"><button type="button" data-role="family" aria-pressed="true">Family</button><button type="button" data-role="leader" aria-pressed="false">Class leader</button></div></section>';
 document.body.appendChild(backdrop);
 const modal=backdrop.querySelector(".auth-modal"),close=backdrop.querySelector(".auth-modal-close"),heading=backdrop.querySelector("#authModalTitle"),sub=backdrop.querySelector("#authModalIntro"),roleSwitch=backdrop.querySelector(".auth-role-switch");
 modal.appendChild(card);
 const roleButtons=roleSwitch.querySelectorAll("[data-role]");
 const setRole=role=>{type.value=role;roleSwitch.hidden=role==="volunteer";roleButtons.forEach(b=>b.setAttribute("aria-pressed",String(b.dataset.role===role)));const label=role==="leader"?"Class leader":role==="volunteer"?"Team / volunteer":"Family";heading.textContent=label+" sign in";sub.textContent=role==="leader"?"Manage your listings, classes and organisation.":"Access your saved activities, planner and family profiles.";type.dispatchEvent(new Event("change",{bubbles:true}));};
 const open=role=>{setRole(role);backdrop.classList.add("is-open");document.body.classList.add("auth-modal-open");close.focus();};
 const shut=()=>{backdrop.classList.remove("is-open");document.body.classList.remove("auth-modal-open");};
 choices.querySelectorAll("[data-open-auth]").forEach(b=>b.addEventListener("click",()=>open(b.dataset.openAuth)));
 foot.querySelector("button").addEventListener("click",()=>open("volunteer"));
 roleButtons.forEach(b=>b.addEventListener("click",()=>setRole(b.dataset.role)));
 close.addEventListener("click",shut);backdrop.addEventListener("click",e=>{if(e.target===backdrop)shut()});document.addEventListener("keydown",e=>{if(e.key==="Escape"&&backdrop.classList.contains("is-open"))shut()});
 const mode=params.get("mode")==="register"?"register":"login";
 if(params.has("context"))open(["leader","volunteer"].includes(params.get("context"))?params.get("context"):"family");
 else if(params.has("mode")||params.has("next")||params.has("event"))open(params.get("context")==="leader"?"leader":"family");
 const tabs=document.querySelectorAll("[data-auth-mode]");tabs.forEach(t=>t.addEventListener("click",()=>{heading.textContent=(type.value==="leader"?"Class leader":type.value==="volunteer"?"Team / volunteer":"Family")+(t.dataset.authMode==="register"?" registration":" sign in")}));
 if(mode==="register"){const reg=document.querySelector('[data-auth-mode="register"]');if(reg)reg.click();}
});
