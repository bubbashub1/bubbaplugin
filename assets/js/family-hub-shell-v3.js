/* Adds a consistent family workspace shell while leaving existing page content and IDs untouched. */
(()=> {
 const start=()=>{
  const body=document.body;
  if(!body||body.classList.contains("bh-family-v3")||new URLSearchParams(location.search).get("embed")==="directory")return;
  const main=document.querySelector("main.app-shell");
  if(!main)return;
  body.classList.add("bh-family-v3");
  main.classList.add("family-v3-main");
  const path=location.pathname.replace(/\/+$/,"")||"/";
  const links=[
   ["/my-hub.html","My Hub","⌂","dashboard"],
   ["/family-section.html?section=recent","Recently Viewed","◷","recent"],
   ["/family-section.html?section=saved","Saved activities","♡","saved"],
   ["/family-section.html?section=visited","Visited Groups","✓","visited"],
   ["/family-section.html?section=suggested","Suggested groups & activities","✦","suggested"],
   ["/family-section.html?section=messages","My Messages","✉","messages"],
   ["/planner.html","My Planner","▦","planner"],
   ["/account/saved-activities.html","Saved activities","♡","saved"],
   ["/account/family.html","My Family","♧","family"],
   ["/account/preferences.html","Preferences","⚙","preferences"],
   ["/account/profile.html","My Profile","☺","profile"],
   ["/account/notifications.html","Notifications","♧","notifications"],
   ["/account/privacy.html","Privacy & consent","▣","privacy"],
   ["/account.html","My Account","○","account"]
  ];
  const side=document.createElement("aside");
  side.className="family-v3-sidebar";
  side.setAttribute("aria-label","My Hub navigation");
  side.innerHTML='<div class="family-v3-brand"><strong class="family-v3-user">Your family</strong><span>My Bubba Hub</span></div><span class="family-v3-nav-label">Your family space</span><nav class="family-v3-nav" aria-label="Family workspace">';
  const nav=side.querySelector("nav");
  links.forEach(([href,label,icon])=>{
   const a=document.createElement("a");a.href=href;
   const active=path===href||((href==="/planner.html")&&path==="/account/planner.html");
   if(active){a.setAttribute("aria-current","page")}
   const i=document.createElement("span");i.className="family-v3-nav-icon";i.setAttribute("aria-hidden","true");i.textContent=icon;
   const t=document.createElement("span");t.textContent=label;a.append(i,t);nav.append(a);
  });
  const help=document.createElement("div");help.className="family-v3-help";help.innerHTML='<strong>Need a hand?</strong><p>Find answers or send the Bubba Hub team a question.</p><a href="/help-support.html">Visit Help &amp; Support →</a>';side.append(help);
  const layout=document.createElement("div");layout.className="family-v3-layout";
  const content=document.createElement("div");content.className="family-v3-main";
  while(main.firstChild)content.append(main.firstChild);
  layout.append(side,content);main.append(layout);
  fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}})
   .then(r=>r.ok?r.json():null).then(data=>{
    const user=data?.user||{};
    const name=user.first_name||user.display_name||user.name||user.email||"Your family";
    side.querySelector(".family-v3-user").textContent=name;
   }).catch(()=>{});
 };
 if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",start,{once:true});else start();
})();
