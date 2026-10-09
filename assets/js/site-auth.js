(function(){
  "use strict";

  const RESTRICTED_PAGES=new Set(["my-hub.html","account.html","account-profile.html","account-planner.html","preferences.html","family.html","notifications.html","saved-activities.html","planner.html","choose.html","consent.html","privacy.html"]);
  const LEADER_PAGES=new Set(["leader.html","booking-manager.html","leader-account.html"]);
  const pageName=(location.pathname.split("/").filter(Boolean).pop()||"index.html").toLowerCase();
  const script=document.currentScript;
  const appRoot=new URL("../../",script?.src||new URL("assets/js/site-auth.js",document.baseURI).href);
  const rootUrl=(path)=>new URL(String(path).replace(/^\/+/, ""),appRoot);
  const authUrl=rootUrl("auth.html");
  const nextUrl=()=>location.pathname+location.search+location.hash;
  const isRestricted=()=>RESTRICTED_PAGES.has(pageName);
  const isLeaderPage=()=>LEADER_PAGES.has(pageName)||/\/leader\//i.test(location.pathname);

  function makeLink(label,href,className){
    const a=document.createElement("a"); a.textContent=label; a.href=href;
    if(className)a.className=className; return a;
  }
  function buildAuthTarget(){
    const u=new URL(authUrl.href),next=nextUrl();
    if(next&&!/\/auth\.html$/i.test(location.pathname))u.searchParams.set("next",next);
    return u.href;
  }
  function leaderAuthTarget(){const u=rootUrl("auth.html");u.searchParams.set("context","leader");u.searchParams.set("next",nextUrl());return u.href;}

  function updateHeader(auth,menus){
    document.querySelectorAll(".site-header").forEach(header=>{
      const nav=header.querySelector(".main-nav");
      if(nav){
        nav.innerHTML="";
        const configured=[
          {label:"Find activities",url:"directory.html"},
          {label:"My Hub",url:"my-hub.html"},
          {label:"Support & Guidance",url:"help-support.html"},
          {label:"Class Leaders",url:"leader.html"},
          {label:"Account",url:"account.html"}
        ];
        configured.forEach(item=>nav.appendChild(makeLink(item.label,rootUrl(item.url).href)));
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
        if(account){account.textContent=auth.authenticated?"My account":"Sign in";account.href=auth.authenticated?(auth.user&&auth.user.role==="leader"?rootUrl("leader-account.html").href:rootUrl("account.html").href):buildAuthTarget();account.classList.add("bh-account-link");}
      }
    });
  }

  function updateFooter(auth,menus){
    document.querySelectorAll(".site-footer").forEach(footer=>{
      let nav=footer.querySelector(".site-footer-nav");
      if(!nav){nav=document.createElement("nav");nav.className="site-footer-nav";nav.setAttribute("aria-label","Site navigation");footer.appendChild(nav);}
      nav.innerHTML="";
      const groups=[menus?.footer_main||[],menus?.footer_tools||[],menus?.footer_legal||[]];
      const configured=groups.flat().filter(item=>item&&item.visible!==false&&item.label&&item.url);
      const fallback=[["Find activities","directory.html"],["Events","events.html"],["Venues","venues.html"],["Calendar","calendar.html"],["Help & Support","help-support.html"],["Privacy","privacy.html"],["Terms","terms.html"]];
      (configured.length?configured.map(item=>[item.label,item.url]):fallback).forEach(x=>nav.appendChild(makeLink(x[0],new URL(x[1],document.baseURI).href)));
      if(auth.authenticated)nav.appendChild(makeLink("Log out","#","bh-logout-link"));
      else {nav.appendChild(makeLink("Sign in",buildAuthTarget(),"bh-auth-link"));nav.appendChild(makeLink("Register",buildAuthTarget()+"&mode=register","bh-auth-link"));}
    });
  }

  async function logout(){
    try{
      const r=await fetch(rootUrl("api/auth.php?action=me"),{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const d=await r.json();
      if(d.authenticated)await fetch(rootUrl("api/auth.php?action=logout"),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({csrf:d.csrf||""})});
    }catch(e){}
    location.href=rootUrl("index.html").href;
  }

  document.addEventListener("click",e=>{
    const t=e.target.closest(".bh-logout-link,.bh-logout-button");if(!t)return;
    e.preventDefault();if(t.dataset.bhLoggingOut==="1")return;t.dataset.bhLoggingOut="1";t.textContent="Logging out…";void logout();
  });

  async function init(){
    let auth={authenticated:false,is_admin:false};
    let authCheckFailed=false;
    try{const r=await fetch(rootUrl("api/auth.php?action=me"),{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});if(!r.ok)throw new Error("HTTP "+r.status);const data=await r.json();if(!data||data.ok===false)throw new Error("Authentication check failed");auth=data;}catch(e){authCheckFailed=true;console.warn("Bubba Hub session check unavailable:",e.message||e);}
    if(authCheckFailed){
      if(isLeaderPage()||isRestricted()){
        const main=document.querySelector("main")||document.body;
        const notice=document.createElement("section");notice.className="admin-panel";notice.setAttribute("role","alert");notice.style.cssText="max-width:620px;margin:2rem auto;padding:1.5rem";
        const title=document.createElement("h2");title.textContent="Connection temporarily unavailable";
        const detail=document.createElement("p");detail.textContent="We couldn’t check your account right now. Your session has not been changed.";
        const retry=document.createElement("button");retry.type="button";retry.className="button button-primary";retry.textContent="Retry";retry.addEventListener("click",()=>location.reload());
        notice.append(title,detail,retry);main.replaceChildren(notice);
      }
      return;
    }
    if(isLeaderPage()&&(!auth.leader_authenticated||(auth.user&&auth.user.role!=="leader"))){location.replace(leaderAuthTarget());return;}
    if(isRestricted()&&!auth.family_authenticated&&!auth.is_admin){location.replace(buildAuthTarget());return;}
    let menus=null;
    try{
      const mr=await fetch(rootUrl("api/menu.php"),{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const md=await mr.json();
      if(mr.ok&&md.ok)menus=md.data||null;
    }catch(e){}
    updateHeader(auth,menus);updateFooter(auth,menus);window.bhSiteAuth=auth;
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else void init();
})();