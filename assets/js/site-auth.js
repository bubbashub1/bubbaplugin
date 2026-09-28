(function(){
  if(window.__bhSiteAuthLoaded)return;
  window.__bhSiteAuthLoaded=true;
  "use strict";

  const RESTRICTED_PAGES=new Set(["my-hub.html","account.html","account-profile.html","account-planner.html","preferences.html","family.html","notifications.html","saved-activities.html","planner.html","choose.html","consent.html","privacy.html","subscription.html"]);
  const LEADER_PAGES=new Set(["leader.html","booking-manager.html","leader-account.html"]);
  const pageName=(location.pathname.split("/").filter(Boolean).pop()||"index.html").toLowerCase();
  const authUrl=new URL("auth.html",document.baseURI);
  const nextUrl=()=>location.pathname+location.search+location.hash;
  const isRestricted=()=>RESTRICTED_PAGES.has(pageName);
  const isLeaderPage=()=>LEADER_PAGES.has(pageName);

  function makeLink(label,href,className){
    const a=document.createElement("a"); a.textContent=label; a.href=href;
    if(className)a.className=className; return a;
  }
  function buildAuthTarget(){
    const u=new URL(authUrl.href),next=nextUrl();
    if(next&&!/\/auth\.html$/i.test(location.pathname))u.searchParams.set("next",next);
    return u.href;
  }
  function leaderAuthTarget(){const u=new URL("leader-auth.html",document.baseURI);u.searchParams.set("next",nextUrl());return u.href;}

  function updateHeader(auth){
    document.querySelectorAll(".site-header").forEach(header=>{
      const nav=header.querySelector(".main-nav");
      if(nav){
        nav.innerHTML="";
        const links=[
          ["Find activities",new URL("directory.html",document.baseURI).href],
          ["Events",new URL("events",document.baseURI).href],
          ["Venues",new URL("venues",document.baseURI).href],
          ["Calendar",new URL("calendar.html",document.baseURI).href],
          ["My Hub",new URL("my-hub.html",document.baseURI).href],
          ["Support & Guidance",new URL("help-support.html",document.baseURI).href]
        ];
        links.forEach(x=>nav.appendChild(makeLink(x[0],x[1])));
        const leader=makeLink(auth.authenticated&&auth.user&&auth.user.role==="leader"?"Class Leaders":"Class Leaders",auth.authenticated&&auth.user&&auth.user.role==="leader"?new URL("leader.html",document.baseURI).href:leaderAuthTarget(),"bh-leader-nav-button");
        leader.setAttribute("data-bh-auth-link","leader");nav.appendChild(leader);
        const accountHref=auth.authenticated&&auth.user&&auth.user.role==="leader"?new URL("leader-account.html",document.baseURI).href:new URL("account.html",document.baseURI).href;
        const account=makeLink(auth.authenticated?"My account":"My account",auth.authenticated?accountHref:buildAuthTarget(),"bh-auth-link");
        account.setAttribute("data-bh-auth-link","account");nav.appendChild(account);
        nav.appendChild(makeLink("Admin",new URL("admin.html",document.baseURI).href));
        if(auth.authenticated)nav.appendChild(makeLink("Log out","#","bh-logout-link"));
      }

      const existing=header.querySelector(".header-account-actions");
      if(existing){
        existing.querySelectorAll(".bh-logout-button,.bh-signin-button,#siteLogout").forEach(el=>el.remove());
        if(auth.authenticated){
          const b=document.createElement("button");b.type="button";b.textContent="Log out";b.className="button button-soft bh-logout-button";existing.appendChild(b);
        }else existing.appendChild(makeLink("Sign in",buildAuthTarget(),"button button-soft bh-signin-button"));
      }else{
        const account=header.querySelector(":scope > a.button[href*='account']");
        if(account){account.textContent=auth.authenticated?"My account":"Sign in";account.href=auth.authenticated?(auth.user&&auth.user.role==="leader"?new URL("leader-account.html",document.baseURI).href:new URL("account.html",document.baseURI).href):buildAuthTarget();account.classList.add("bh-account-link");}
      }
    });
  }

  function updateFooter(auth,menus){
    document.querySelectorAll(".site-footer").forEach(footer=>{
      let nav=footer.querySelector(".site-footer-nav");
      if(!nav){nav=document.createElement("nav");nav.className="site-footer-nav";nav.setAttribute("aria-label","Site navigation");footer.appendChild(nav);}
      nav.innerHTML="";
      [["Find activities","directory.html"],["Events","events"],["Venues","venues"],["Calendar","calendar.html"],["Help & Support","help-support.html"],["Privacy","privacy.html"],["Terms","terms.html"]].forEach(x=>nav.appendChild(makeLink(x[0],new URL(x[1],document.baseURI).href)));
      if(auth.authenticated)nav.appendChild(makeLink("Log out","#","bh-logout-link"));
      else {nav.appendChild(makeLink("Sign in",buildAuthTarget(),"bh-auth-link"));nav.appendChild(makeLink("Register",buildAuthTarget()+"&mode=register","bh-auth-link"));}
    });
  }

  async function logout(){
    try{
      const r=await fetch(new URL("api/auth.php?action=me",document.baseURI),{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const d=await r.json();
      if(d.authenticated)await fetch(new URL("api/auth.php?action=logout",document.baseURI),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf:d.csrf||""})});
    }catch(e){}
    location.href=new URL("index.html",document.baseURI).href;
  }

  document.addEventListener("click",e=>{
    const t=e.target.closest(".bh-logout-link,.bh-logout-button");if(!t)return;
    e.preventDefault();if(t.dataset.bhLoggingOut==="1")return;t.dataset.bhLoggingOut="1";t.textContent="Logging out…";void logout();
  });

  async function init(){
    let auth={authenticated:false,is_admin:false};
    try{const r=await fetch(new URL("api/auth.php?action=me",document.baseURI),{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});if(r.ok)auth=await r.json();}catch(e){}
    if(isLeaderPage()&&(!auth.authenticated||(auth.user&&auth.user.role!=="leader"&&!auth.is_admin))){location.replace(leaderAuthTarget());return;}
    if(isRestricted()&&!auth.authenticated&&!auth.is_admin){location.replace(buildAuthTarget());return;}
    updateHeader(auth);updateFooter(auth);window.bhSiteAuth=auth;
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else void init();
})();