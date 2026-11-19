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
  const groups=[
   {title:"Explore",links:[["/family-section.html?section=recent","Recently Viewed","◷"],["/family-section.html?section=saved","Saved activities","♡"],["/family-section.html?section=visited","Visited Groups","✓"],["/family-section.html?section=suggested","Suggested groups & activities","✦"]]},
   {title:"My Family",links:[["/account/family.html","Family profiles","♧"],["/account/preferences.html","Family preferences","⚙"]]},
   {title:"My Space",links:[["/family-section.html?section=messages","My Messages","✉"],["/account/profile.html","My Profile","☺"],["/account/notifications.html","Notifications","♧"],["/account/privacy.html","Privacy & consent","▣"]]},
   {title:"My Planner",links:[["/planner.html","My Planner","▦"],["/account/bookings.html","Bookings","▣"]]},
   {title:"Account",links:[["/account.html","My Account","○"]]}
  ];
  const side=document.createElement("aside");
  side.className="family-v3-sidebar";
  side.setAttribute("aria-label","My Hub navigation");
  side.innerHTML='<button class="family-v3-menu-toggle" type="button" aria-expanded="false" aria-controls="familyV3Nav"><span>My Hub menu</span><span class="family-v3-menu-chevron" aria-hidden="true">⌄</span></button><nav class="family-v3-nav" aria-label="Family workspace">';
  const nav=side.querySelector("nav");nav.id="familyV3Nav";
  const menuToggle=side.querySelector(".family-v3-menu-toggle");
  const setMenuOpen=open=>{menuToggle.setAttribute("aria-expanded",String(open));menuToggle.classList.toggle("is-open",open);nav.classList.toggle("is-open",open);};
  menuToggle.addEventListener("click",()=>setMenuOpen(menuToggle.getAttribute("aria-expanded")!=="true"));
  nav.addEventListener("click",event=>{if(event.target.closest("a")&&window.matchMedia("(max-width:767px)").matches)setMenuOpen(false);});
  side.addEventListener("keydown",event=>{if(event.key==="Escape")setMenuOpen(false);});
  groups.forEach(group=>{
    const section=document.createElement("section");section.className="family-v3-group";
    const button=document.createElement("button");button.type="button";button.className="family-v3-group-toggle";button.setAttribute("aria-expanded","true");
    button.innerHTML="<span>"+group.title+"</span><span class='family-v3-group-chevron' aria-hidden='true'>⌄</span>";
    const subnav=document.createElement("div");subnav.className="family-v3-group-links";
    button.addEventListener("click",()=>{const open=button.getAttribute("aria-expanded")!=="true";button.setAttribute("aria-expanded",String(open));section.classList.toggle("is-collapsed",!open);});
    group.links.forEach(([href,label,icon])=>{
      const a=document.createElement("a");a.href=href;const target=new URL(href,location.origin),currentParams=new URLSearchParams(location.search);
      const pathNow=location.pathname.replace(/\/+$/,"")||"/",targetPath=target.pathname.replace(/\/+$/,"")||"/";
      const samePath=pathNow===targetPath||(href==="/planner.html"&&pathNow==="/account/planner.html");
      const sameQuery=[...target.searchParams.keys()].every(key=>currentParams.get(key)===target.searchParams.get(key));
      if(samePath&&sameQuery)a.setAttribute("aria-current","page");
      const i=document.createElement("span");i.className="family-v3-nav-icon";i.setAttribute("aria-hidden","true");i.textContent=icon;
      const t=document.createElement("span");t.textContent=label;a.append(i,t);subnav.append(a);
    });
    section.append(button,subnav);nav.append(section);
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
