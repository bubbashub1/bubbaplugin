/* Builds the shared dashboard navigation around existing leader sub-pages. */
(()=> {
 const body=document.body;
 if(!body||body.dataset.leaderShellApplied==="1")return;
 const main=document.querySelector("main");
 if(!main)return;
 body.dataset.leaderShellApplied="1";
 body.classList.add("bh-leader-v2");
 const links=[
  ["Overview","/leader.html","⌂","dashboard"],
  ["Add a Class","/leader/new.html","＋","new"],
  ["My Classes","/leader/classes.html","▤","classes"],
  ["My Schedule","/leader/schedule.html","▦","schedule"],
  ["My Venues","/leader/venues.html","⌖","venues"],
  ["Bookings","/leader/bookings.html","▣","bookings"],
  ["My Messages","/leader/messages.html","✉","messages"],
  ["My Profile","/leader/profile.html","◎","profile"],
  ["My Specialisms","/leader/specialisms.html","✧","specialisms"],
  ["FAQs","/leader/faqs.html","?","faqs"],
  ["Statistics","/leader/statistics.html","▥","statistics"],
  ["Payments","/leader/payments.html","£","payments"],
  ["Support","/leader/support.html","?","support"]
 ];
 const current=(body.dataset.leaderPage||location.pathname.split("/").pop().replace(/\.html?$/,"")||"dashboard").toLowerCase();
 const shell=document.createElement("div");shell.className="leader-v3-shell";
 const aside=document.createElement("aside");aside.className="leader-v3-sidebar";aside.setAttribute("aria-label","Leader dashboard navigation");
 const brand=document.createElement("div");brand.className="bh-leader-v3-brand";brand.id="leaderV3SidebarBrand";brand.textContent="Your account";
 const label=document.createElement("div");label.className="bh-leader-v3-label";label.textContent="My workspace";
 const nav=document.createElement("nav");nav.className="bh-leader-v3-nav";nav.setAttribute("aria-label","Leader workspace");
 links.forEach(([name,url,icon,key])=>{const a=document.createElement("a");a.href=url;if(current===key||(current==="index"&&key==="dashboard"))a.setAttribute("aria-current","page");const glyph=document.createElement("span");glyph.className="nav-ico";glyph.setAttribute("aria-hidden","true");glyph.textContent=icon;a.append(glyph,document.createTextNode(name));nav.append(a);});
 aside.append(brand,label,nav);
 const top=document.createElement("div");top.className="bh-leader-v3-topline";
 const crumb=document.createElement("span");crumb.textContent="Leader Hub / "+(links.find(x=>x[3]===current)?.[0]||"Workspace");
 const user=document.createElement("span");user.className="bh-leader-v3-user";
 const userName=document.createElement("span");userName.id="leaderV3UserName";userName.textContent="Your account";
 const avatar=document.createElement("span");avatar.className="bh-leader-v3-user-avatar";avatar.id="leaderV3Avatar";avatar.textContent="U";
 user.append(userName,avatar);top.append(crumb,user);
 shell.append(aside);
 main.classList.add("leader-v3-main");
 main.parentNode.insertBefore(shell,main);shell.append(main);main.insertBefore(top,main.firstChild);
 fetch("/api/leader-portal.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"}).then(r=>r.json()).then(data=>{
  if(!data||!data.ok)return;
  const org=data.organisation||{};
  const name=String(org.first_name||org.contact_name||org.name||"").trim().split(/\s+/)[0]||"Your account";
  userName.textContent=name;brand.textContent=name;avatar.textContent=name.slice(0,1).toUpperCase();
 }).catch(()=>{});
})();
