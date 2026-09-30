const BH_KEYS={saved:"bhSavedActivities",planner:"bhPlanner"};

async function bhActivities(){
  if(window.__bhActivities)return window.__bhActivities;

  const apiUrl=new URL("api/activities.php",document.baseURI);
  apiUrl.searchParams.set("page","1");
  apiUrl.searchParams.set("per_page","50");

  const fetchPage=async page=>{
    const url=new URL(apiUrl.toString());
    url.searchParams.set("page",String(page));
    const response=await fetch(url.toString(),{cache:"no-store",headers:{Accept:"application/json"}});
    if(!response.ok)throw new Error("Could not load activities");
    const payload=await response.json();
    if(!payload.ok)throw new Error(payload.error||"Could not load activities");
    return payload;
  };

  const firstPayload=await fetchPage(1);
  const firstRows=Array.isArray(firstPayload.data)?firstPayload.data:[];
  const totalPages=Number(firstPayload.pagination?.pages||1);
  let rows=firstRows;

  for(let start=2;start<=totalPages;start+=4){
    const pages=Array.from({length:Math.min(4,totalPages-start+1)},(_,i)=>start+i);
    const payloads=await Promise.all(pages.map(fetchPage));
    payloads.forEach(payload=>{
      if(Array.isArray(payload.data)) rows=rows.concat(payload.data);
    });
  }

  window.__bhActivities=rows.map(a=>{
    const venues=Array.isArray(a.venues)?a.venues:[];
    const sessions=Array.isArray(a.sessions)?a.sessions:[];

    const sessionByVenue={};
    sessions.forEach(s=>{
      const venueId=String(s.venue_id);
      if(!sessionByVenue[venueId])sessionByVenue[venueId]=[];
      sessionByVenue[venueId].push({
        id:s.id,
        day:["","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"][Number(s.day_of_week)]||String(s.day_of_week||""),
        day_of_week:Number(s.day_of_week)||null,
        start:bhFormatTime(s.start_time||""),
        time:bhFormatTime(s.start_time||""),
        end:bhFormatTime(s.end_time||""),
        end_time:bhFormatTime(s.end_time||""),
        duration:s.duration_minutes?String(s.duration_minutes)+" mins":"",
        duration_minutes:s.duration_minutes,
        price:s.price!==null&&s.price!==undefined?"£"+Number(s.price).toFixed(2):"",
        price_value:s.price,
        term_time:s.term_time_only?"Term time":"",
        frequency:s.frequency||"",
        start_date:s.start_date||"",
        end_date:s.end_date||""
      });
    });

    const coordinate=raw=>{
      if(raw===null||raw===undefined||raw==="") return null;
      const n=Number(String(raw).trim().replace(",",".")); 
      return Number.isFinite(n)?n:null;
    };

    const normalizedVenues=venues.map(v=>({
      id:v.id,
      name:v.name||"Venue",
      address:v.address||"",
      town:v.town||"",
      region:v.region||"",
      postcode:v.postcode||"",
      lat:coordinate(v.latitude),
      long:coordinate(v.longitude),
      latitude:coordinate(v.latitude),
      longitude:coordinate(v.longitude),
      sessions:sessionByVenue[String(v.id)]||[]
    }));

    return {
      ...a,
      id:String(a.id),
      category:a.category||"",
      county:a.county||"",
      age_range:a.age_range?[String(a.age_range)]:[],
      accessibility:Array.isArray(a.accessibility)?a.accessibility:[],
      price:a.price_from!==null&&a.price_from!==undefined?"£"+Number(a.price_from).toFixed(2):"",
      price_value:a.price_from,
      image_url:a.image_path||"",
      short_description:a.description||"",
      summary:a.description||"",
      content:a.description||"",
      region:normalizedVenues[0]?.region||"",
      town:normalizedVenues[0]?.town||"",
      location:normalizedVenues[0]?.town||normalizedVenues[0]?.name||"",
      organiser_id:a.organiser?.id||"",
      organiser_name:a.organiser?.name||"",
      organiser:a.organiser?.name||"",
      organiser_website:"",
      venues:normalizedVenues,
      booking_mode:a.booking_url?"external":"reserve",
      booking_slots:[],
      booking_url:a.booking_url||""
    };
  });

  return window.__bhActivities;
}

function bhFormatTime(value){
  const raw=String(value||"").trim();
  if(!raw)return "";
  const m=raw.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
  return m ? String(m[1]).padStart(2,"0")+":"+m[2] : raw;
}
function bhFormatDate(value){
  const raw=String(value||"").trim();
  const m=raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  return m ? m[3]+"/"+m[2]+"/"+m[1] : raw;
}
function bhGet(key){try{const value=JSON.parse(localStorage.getItem(key)||"[]");return Array.isArray(value)?value.map(String):[]}catch{return[]}}
function bhSet(key,value){localStorage.setItem(key,JSON.stringify(value.map(String)))}
function bhIsSaved(id){return bhGet(BH_KEYS.saved).includes(String(id))}
async function bhSavedPersist(activityId,saved){
  try{
    const auth=await bhAuthSession();
    if(!auth?.authenticated||!auth.csrf)return false;
    if(auth.is_admin&&!auth.user?.id)return false;
    const response=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"set_saved",activity_id:Number(activityId),saved:!!saved,csrf:auth.csrf})});
    if(!response.ok)return false;
    const data=await response.json();
    return !!data.ok;
  }catch(e){return false}
}
async function bhHydrateSaved(){
  try{
    const auth=await bhAuthSession();
    if(!auth?.authenticated||!auth.csrf)return false;
    if(auth.is_admin&&!auth.user?.id)return false;
    const response=await fetch("api/my-hub.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
    const data=await response.json();
    if(!response.ok||!data.ok||!Array.isArray(data.saved))return false;
    const local=bhGet(BH_KEYS.saved);
    const merged=[...new Set([...data.saved.map(String),...local])];
    if(merged.length!==data.saved.length){
      await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"sync_saved",saved:merged,csrf:auth.csrf})});
    }
    bhSet(BH_KEYS.saved,merged);
    return true;
  }catch(e){return false}
}
function bhToggleSaved(id){
  const a=bhGet(BH_KEYS.saved),key=String(id),i=a.indexOf(key),saved=i<0;
  if(i>=0)a.splice(i,1);else a.push(key);
  bhSet(BH_KEYS.saved,a);
  void bhSavedPersist(id,saved);
  return saved;
}
function bhIsPlanned(id){return bhGet(BH_KEYS.planner).includes(String(id))}
let bhAuthPromise=null;
async function bhAuthSession(){
  if(!bhAuthPromise){
    bhAuthPromise=fetch("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}})
      .then(r=>r.json())
      .catch(()=>({ok:false,authenticated:false}));
  }
  return bhAuthPromise;
}
async function bhPlannerPersist(activityId,planned){
  try{
    const auth=await bhAuthSession();
    if(!auth?.authenticated||!auth.csrf)return false;
    if(auth.is_admin&&!auth.user?.id)return false;
    const visited=bhGet("bhVisitedActivities").includes(String(activityId));
    const response=await fetch("api/planner.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"set_activity",activity_id:Number(activityId),planned:!!planned,visited})});
    return response.ok && (await response.json()).ok;
  }catch(e){return false}
}
function bhTogglePlanned(id){
  const a=bhGet(BH_KEYS.planner),key=String(id),i=a.indexOf(key),planned=i<0;
  if(i>=0)a.splice(i,1);else a.push(key);
  bhSet(BH_KEYS.planner,a);
  void bhPlannerPersist(id,planned);
  return planned;
}
function bhActivity(id,items){return items.find(x=>String(x.id)===String(id))||null}
function bhVenues(activity){if(Array.isArray(activity?.venues)&&activity.venues.length)return activity.venues;return [{id:String(activity?.id||"venue"),name:activity?.location||activity?.town||activity?.region||"Venue",address:activity?.location||"",town:activity?.town||"",region:activity?.region||"",lat:activity?.lat,long:activity?.long,sessions:[{day:activity?.day,time:activity?.time,duration:activity?.duration,price:activity?.price}]}]}
function bhSessions(activity){return bhVenues(activity).flatMap(v=>(Array.isArray(v.sessions)?v.sessions:[]).map(s=>({...s,venue:v})))}
function bhEscape(value){return String(value??"").replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch]))}

function bhActivityBySlug(slug,items){const key=String(slug||"").toLowerCase();return items.find(x=>String(x.slug||"").toLowerCase()===key)||null}
function bhActivityUrl(activity){const slug=String(activity?.slug||"").trim();return slug?encodeURI(slug.replace(/^\/+|\/+$/g,"")+"/"):("activity.html?id="+encodeURIComponent(activity?.id||""))}

void bhHydrateSaved();

(function(){
  const s=document.createElement("script");
  s.src="assets/js/site-auth.js";
  s.defer=true;
  document.head.appendChild(s);
})();

(function(){
  const footerHTML = `
    <footer class="site-footer">
      <div class="site-footer-grid">
        <div class="site-footer-brand">
          <a href="index.html" class="site-footer-logo" aria-label="Bubba Hub home">
            <img src="images/logos/mainlogo-20260924-221429-7e305d.jpg" alt="Bubba Hub">
          </a>
          <div class="site-footer-brand-copy">
            <a href="index.html" class="site-footer-title">Bubba Hub</a>
            <p>Find, plan &amp; book family activities across Devon &amp; Cornwall.</p>
          </div>
        </div>
        <div class="site-footer-column">
          <h2>Main menu</h2>
          <nav class="site-footer-links" aria-label="Footer main menu">
            <a href="directory.html">Find activities</a>
            <a href="my-hub.html">My Hub</a>
            <a href="help-support.html">Support &amp; Guidance</a>
            <a href="leader.html">Class Leaders</a>
            <a href="account.html">Account</a>
          </nav>
        </div>
        <div class="site-footer-column">
          <h2>Explore &amp; tools</h2>
          <nav class="site-footer-links" aria-label="Footer explore and tools">
            <a href="events.html">Events</a>
            <a href="venues.html">Venues</a>
            <a href="planner.html">Planner</a>
            <a href="calendar.html">Calendar</a>
            <a href="admin/admin.html">Admin</a>
          </nav>
        </div>
        <div class="site-footer-column">
          <h2>Legal &amp; contact</h2>
          <nav class="site-footer-links" aria-label="Footer legal and contact">
            <a href="privacy.html">Privacy</a>
            <a href="terms.html">Terms &amp; Conditions</a>
            <a href="mailto:contact@bubbahub.co.uk">Contact Bubba Hub</a>
          </nav>
        </div>
      </div>
      <div class="site-footer-bottom">
        <span>&copy; ${new Date().getFullYear()} Bubba Hub</span>
        <span>Devon &amp; Cornwall</span>
      </div>
    </footer>`;
  const existing=document.querySelector(".site-footer");
  if(!existing) document.body.insertAdjacentHTML("beforeend",footerHTML);
})();


/* Load the approved homepage visual system across the site. */
(function(){
  try{
    const base=document.querySelector('link[href*="assets/css/styles.css"]');
    if(!base)return;
    const url=new URL(base.href,document.baseURI);
    url.pathname=url.pathname.replace(/\/styles\.css$/,'/sitewide-home.css');
    url.search='v=20260930-1';
    if(!document.querySelector('link[data-bh-sitewide-theme]')){
      const link=document.createElement('link');
      link.rel='stylesheet';
      link.href=url.toString();
      link.dataset.bhSitewideTheme='true';
      document.head.appendChild(link);
    }
  }catch(e){}
})();

/* Shared shell enhancements. CSS lives in styles.css so the UI does not depend on JavaScript injecting styles. */
(function(){
  document.documentElement.classList.add("js-enabled");
})();

(function(){
  function initMobileNav(){
    const headers=document.querySelectorAll(".site-header");
    headers.forEach((header,index)=>{
      const nav=header.querySelector(".main-nav");
      if(!nav) return;

      const navId=nav.id || "mobile-main-menu-"+(index+1);
      nav.id=navId;

      let toggle=header.querySelector(".mobile-nav-toggle");
      if(!toggle){
        toggle=document.createElement("button");
        toggle.type="button";
        toggle.className="mobile-nav-toggle";
        toggle.innerHTML="<span></span><span></span><span></span>";
        header.insertBefore(toggle,nav);
      }

      toggle.setAttribute("aria-expanded","false");
      toggle.setAttribute("aria-controls",navId);
      toggle.setAttribute("aria-label","Open menu");

      const closeMenu=()=>{
        header.classList.remove("mobile-menu-open");
        toggle.setAttribute("aria-expanded","false");
        toggle.setAttribute("aria-label","Open menu");
      };
      const openMenu=()=>{
        header.classList.add("mobile-menu-open");
        toggle.setAttribute("aria-expanded","true");
        toggle.setAttribute("aria-label","Close menu");
      };

      if(toggle.dataset.bhMenuBound==="true") return;
      toggle.dataset.bhMenuBound="true";

      toggle.addEventListener("click",event=>{
        event.preventDefault();
        event.stopPropagation();
        header.classList.contains("mobile-menu-open") ? closeMenu() : openMenu();
      });
      nav.addEventListener("click",event=>{
        if(event.target.closest("a")) closeMenu();
      });
      document.addEventListener("click",event=>{
        if(!header.contains(event.target)) closeMenu();
      });
      document.addEventListener("keydown",event=>{
        if(event.key==="Escape") closeMenu();
      });
    });
  }

  if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",initMobileNav);
  else initMobileNav();
})();

/* Main navigation is intentionally fixed site-wide.
   The Admin menu editor must not override the public header navigation. */
(function(){
  const MAIN_NAV=[
    {label:"Find activities",url:"directory.html"},
    {label:"My Hub",url:"my-hub.html"},
    {label:"Support & Guidance",url:"help-support.html"},
    {label:"Class Leaders",url:"leader.html"},
    {label:"Account",url:"account.html"}
  ];
  const applyMainNav=()=>document.querySelectorAll(".site-header .main-nav").forEach(nav=>{
    nav.innerHTML=MAIN_NAV.map(x=>'<a href="'+bhEscape(x.url)+'">'+bhEscape(x.label)+'</a>').join("");
    const header=nav.closest(".site-header");
    const toggle=header?.querySelector(".mobile-nav-toggle");
    if(nav.dataset.bhCloseBound!=="true"){
      nav.dataset.bhCloseBound="true";
      nav.addEventListener("click",event=>{
        if(event.target.closest("a")){
          header?.classList.remove("mobile-menu-open");
          toggle?.setAttribute("aria-expanded","false");
          toggle?.setAttribute("aria-label","Open menu");
        }
      });
    }
  });
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",applyMainNav,{once:true});
  else applyMainNav();
})();

/* Homepage search: keep the front door useful while sharing the directory filter choices. */
(function(){
  const form=document.getElementById("heroSearch");
  if(!form)return;
  const region=document.getElementById("heroRegion");
  const town=document.getElementById("heroTown");
  const category=document.getElementById("heroCategory");
  const age=document.getElementById("heroAge");
  const day=document.getElementById("heroDay");
  fetch("api/activities.php?page=1&per_page=100",{cache:"no-store",headers:{Accept:"application/json"}})
    .then(r=>r.json()).then(payload=>{
      const items=Array.isArray(payload.data)?payload.data:[];
      const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
      const categories=[...new Set(items.map(a=>categoryMap[a.category]||a.category).filter(Boolean))].sort();
      const regions=[...new Set(items.flatMap(a=>(a.venues||[]).map(v=>v.region||a.region)).filter(Boolean))].sort();
      const towns=[...new Set(items.flatMap(a=>(a.venues||[]).map(v=>v.town||a.town)).filter(Boolean))].sort();
      const addOptions=(el,values)=>{if(el)values.forEach(v=>el.add(new Option(v,v)));};
      addOptions(region,regions); addOptions(town,towns); addOptions(category,categories);
    }).catch(()=>{});
})();
