/* Adds a consistent family workspace shell while leaving existing page content and IDs untouched. */
(()=> {
 const start=()=>{
  const body=document.body;
  if(!body||document.querySelector(".family-v3-sidebar")||new URLSearchParams(location.search).get("embed")==="directory")return;
  const main=document.querySelector("main.app-shell");
  if(!main)return;
  body.classList.add("bh-family-v3");
  main.classList.add("family-v3-main");
  const path=location.pathname.replace(/\/+$/,"")||"/";
  const groups=[
   {id:"explore",label:"Explore",items:[
    ["/my-hub.html","My Hub","⌂"],
    ["/choose.html","What should we do?","✦"],
    ["/family-section.html?section=recent","Recently Viewed","◷"],
    ["/family-section.html?section=saved","Saved activities","♡"],
    ["/family-section.html?section=visited","Visited Groups","✓"]
   ]},
   {id:"family",label:"My Space",items:[
    ["/account/family.html","My Family","♧"],
    ["/planner.html","My Planner","▦"],
    ["/family-section.html?section=messages","My Messages","✉"],
    ["/account/preferences.html","Preferences","⚙"],
    ["/account/notifications.html","Notifications","♧"],
    ["/account/privacy.html","Privacy & consent","▣"]
   ]}
  ];
  const side=document.createElement("aside");
  side.className="family-v3-sidebar";
  side.setAttribute("aria-label","My Hub navigation");
  side.innerHTML='<button class="family-v3-menu-toggle" type="button" aria-expanded="false" aria-controls="familyV3Nav"><span>My Hub menu</span><span class="family-v3-menu-chevron" aria-hidden="true">⌄</span></button><div class="family-v3-brand"><strong class="family-v3-user">Your family</strong><span>My Bubba Hub</span></div><nav class="family-v3-nav" id="familyV3Nav" aria-label="Family workspace"></nav>';
  const nav=side.querySelector("nav");
  const menuToggle=side.querySelector(".family-v3-menu-toggle");
  const setMenuOpen=open=>{menuToggle.setAttribute("aria-expanded",String(open));menuToggle.classList.toggle("is-open",open);nav.classList.toggle("is-open",open);};
  menuToggle.addEventListener("click",()=>setMenuOpen(menuToggle.getAttribute("aria-expanded")!=="true"));
  nav.addEventListener("click",event=>{if(event.target.closest("a"))setMenuOpen(false);});
  side.addEventListener("keydown",event=>{if(event.key==="Escape")setMenuOpen(false);});
  const isMobile=()=>window.matchMedia("(max-width:850px)").matches;
  groups.forEach(group=>{
   const section=document.createElement("section");section.className="family-v3-nav-group";section.dataset.group=group.id;
   const heading=document.createElement("button");heading.type="button";heading.className="family-v3-nav-group-toggle";
   heading.setAttribute("aria-expanded","false");
   const title=document.createElement("span");title.textContent=group.label;
   const chevron=document.createElement("span");chevron.className="family-v3-group-chevron";chevron.setAttribute("aria-hidden","true");chevron.textContent="⌄";
   heading.append(title,chevron);
   const items=document.createElement("div");items.className="family-v3-nav-group-items";
   let groupActive=false;
   group.items.forEach(([href,label,icon])=>{
    const a=document.createElement("a");a.href=href;
    const target=new URL(href,location.origin);
    const currentParams=new URLSearchParams(location.search);
    const targetPath=target.pathname.endsWith("/")?target.pathname.slice(0,-1)||"/":target.pathname;
    const samePath=path===targetPath||((href==="/planner.html")&&path==="/account/planner.html");
    const sameQuery=[...target.searchParams.keys()].every(key=>currentParams.get(key)===target.searchParams.get(key));
    if(samePath&&sameQuery){a.setAttribute("aria-current","page");groupActive=true;}
    const i=document.createElement("span");i.className="family-v3-nav-icon";i.setAttribute("aria-hidden","true");i.textContent=icon;
    const t=document.createElement("span");t.textContent=label;a.append(i,t);items.append(a);
   });
   const setExpanded=open=>{heading.setAttribute("aria-expanded",String(open));section.classList.toggle("is-expanded",open);items.hidden=!open;};
   heading.addEventListener("click",()=>{const open=heading.getAttribute("aria-expanded")!=="true";if(open&&isMobile())nav.querySelectorAll(".family-v3-nav-group").forEach(other=>{if(other!==section){const otherHeading=other.querySelector(".family-v3-nav-group-toggle");otherHeading.setAttribute("aria-expanded","false");other.classList.remove("is-expanded");other.querySelector(".family-v3-nav-group-items").hidden=true;}});setExpanded(open);});
   setExpanded(groupActive||!isMobile());
   section.append(heading,items);nav.append(section);
  });
  window.addEventListener("resize",()=>groups.forEach(group=>{
   const section=nav.querySelector('[data-group="'+group.id+'"]');
   if(!section)return;
   const heading=section.querySelector(".family-v3-nav-group-toggle");
   if(isMobile()&&heading.getAttribute("aria-expanded")==="true")return;
   if(!isMobile()) {heading.setAttribute("aria-expanded","true");section.classList.add("is-expanded");section.querySelector(".family-v3-nav-group-items").hidden=false;}
   else {heading.setAttribute("aria-expanded","false");section.classList.remove("is-expanded");section.querySelector(".family-v3-nav-group-items").hidden=true;}
  }));
  const help=document.createElement("div");help.className="family-v3-help";help.innerHTML='<strong>Need a hand?</strong><p>Find answers or send the Bubba Hub team a question.</p><a class="family-v3-help-link" href="/help-support.html">Visit Help &amp; Support <span aria-hidden="true">→</span></a>';side.append(help);const profileCta=document.createElement("a");profileCta.className="family-v3-profile-cta";profileCta.href="/account/profile.html";profileCta.innerHTML='<span class="family-v3-profile-cta-icon" aria-hidden="true">☺</span><span><strong>My Profile</strong><small>View and update your details</small></span><span class="family-v3-profile-cta-arrow" aria-hidden="true">→</span>';side.append(profileCta);
  const layout=document.createElement("div");layout.className="family-v3-layout";
  const content=document.createElement("div");content.className="family-v3-main";
  while(main.firstChild)content.append(main.firstChild);
  layout.append(side,content);
  main.append(layout);
  fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}})
   .then(r=>r.ok?r.json():null).then(data=>{
    const user=data?.user||{};
    const name=user.first_name||user.display_name||user.name||user.email||"Your family";
    side.querySelector(".family-v3-user").textContent=name;
   }).catch(()=>{});
 };
 if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",start,{once:true});else start();
})();
