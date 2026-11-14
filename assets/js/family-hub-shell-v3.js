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
   ["/account/saved-activities.html","Manage saved activities","♡","saved-library"],
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
  side.innerHTML='<button class="family-v3-menu-toggle" type="button" aria-expanded="false" aria-controls="familyV3Nav"><span>My Hub menu</span><span class="family-v3-menu-chevron" aria-hidden="true">⌄</span></button><div class="family-v3-brand"><strong class="family-v3-user">Your family</strong><span>My Bubba Hub</span></div><span class="family-v3-nav-label">Your family space</span><nav class="family-v3-nav" aria-label="Family workspace">';
  const nav=side.querySelector("nav");
  nav.id="familyV3Nav";
  const menuToggle=side.querySelector(".family-v3-menu-toggle");
  const setMenuOpen=open=>{menuToggle.setAttribute("aria-expanded",String(open));menuToggle.classList.toggle("is-open",open);nav.classList.toggle("is-open",open);};
  menuToggle.addEventListener("click",()=>setMenuOpen(menuToggle.getAttribute("aria-expanded")!=="true"));
  nav.addEventListener("click",event=>{if(event.target.closest("a"))setMenuOpen(false);});
  side.addEventListener("keydown",event=>{if(event.key==="Escape")setMenuOpen(false);});
  links.forEach(([href,label,icon])=>{
   const a=document.createElement("a");a.href=href;
   const target=new URL(href,location.origin);
   const currentParams=new URLSearchParams(location.search);
   const targetParams=target.searchParams;
   const targetPath=target.pathname.endsWith("/")?target.pathname.slice(0,-1)||"/":target.pathname;
   const samePath=path===targetPath||((href==="/planner.html")&&path==="/account/planner.html");
   const queryKeys=[...targetParams.keys()];
   const sameQuery=queryKeys.every(key=>currentParams.get(key)===targetParams.get(key));
   const active=samePath&&sameQuery;
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
