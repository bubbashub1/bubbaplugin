/* Load the single shared Bubba Hub header on every public page. */
(function(){
  if(document.querySelector('script[data-bh-header-loader], script[src*="assets/js/header.js"]'))return;
  const script=document.createElement("script");
  script.src="assets/js/header.js";
  script.defer=true;
  script.dataset.bhHeaderLoader="true";
  document.head.appendChild(script);
})();

const BH_KEYS={saved:"bhSavedActivities",planner:"bhPlanner",visited:"bhVisitedActivities",recent:"bhRecentlyViewed"};

async function bhActivities(){
  if(window.__bhActivities)return window.__bhActivities;

  const apiUrl=new URL("api/activities.php",document.baseURI);
  apiUrl.searchParams.set("page","1");
  apiUrl.searchParams.set("per_page","50");

  const fetchPage=async page=>{
    const url=new URL(apiUrl.toString());
    url.searchParams.set("page",String(page));
    let lastError=null;
    for(let attempt=0;attempt<2;attempt++){
      try{
        const response=await fetch(url.toString(),{cache:"no-store",headers:{Accept:"application/json"}});
        if(!response.ok)throw new Error("Could not load activities");
        const payload=await response.json();
        if(!payload.ok)throw new Error(payload.error||"Could not load activities");
        return payload;
      }catch(error){
        lastError=error;
        if(attempt===0)await new Promise(resolve=>setTimeout(resolve,250));
      }
    }
    throw lastError||new Error("Could not load activities");
  };

  const firstPayload=await fetchPage(1);
  const firstRows=Array.isArray(firstPayload.data)?firstPayload.data:[];
  const totalPages=Number(firstPayload.pagination?.pages||1);
  let rows=firstRows;

  // Page 1 is the critical payload used by the homepage and directory shell.
  // Do not let one later page/API hiccup blank the whole site after a hard refresh.
  for(let start=2;start<=totalPages;start+=4){
    const pages=Array.from({length:Math.min(4,totalPages-start+1)},(_,i)=>start+i);
    const results=await Promise.allSettled(pages.map(fetchPage));
    results.forEach(result=>{
      if(result.status==="fulfilled" && Array.isArray(result.value?.data)){
        rows=rows.concat(result.value.data);
      }
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
      accessibility:(()=>{if(Array.isArray(a.accessibility))return a.accessibility;try{const parsed=JSON.parse(a.accessibility||"[]");return Array.isArray(parsed)?parsed:[]}catch{return a.accessibility?[String(a.accessibility)]:[]}})(),
      price:a.price_from!==null&&a.price_from!==undefined?"£"+Number(a.price_from).toFixed(2):"",
      price_value:a.price_from,
      image_url:(a.image_path||"").replace(
        "gemini_generated_image_20260930-190428-b9e652.png",
        "gemini_generated_image_pq5i56pq5i56pq5i-removebg-preview-20260930-190428-b9e652.png"
      ),
      short_description:a.description||"",
      summary:a.description||"",
      content:a.description||"",
      region:normalizedVenues[0]?.region||"",
      town:normalizedVenues[0]?.town||"",
      location:normalizedVenues[0]?.town||normalizedVenues[0]?.name||"",
      organiser_id:a.organiser?.id||"",
      organiser_name:a.organiser?.name||"",
      organiser:a.organiser?.name||"",
      organiser_website:a.organiser?.website||"",
      organiser_email:a.organiser?.email||"",
      organiser_phone:a.organiser?.phone||"",
      organiser_slug:a.organiser?.slug||"",
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
        header.prepend(toggle);
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


/* Homepage live activity cards — use the same activity data as Directory/Activity. */
(function(){
  const grid=document.querySelector(".home-activity-grid");
  if(!grid||typeof bhActivities!=="function")return;

  const fallbackCards=Array.from(grid.querySelectorAll(".home-activity-card"));
  const setText=(el,value)=>{if(el)el.textContent=value||""};
  const PLACEHOLDER_IMAGE="images/logos/gemini_generated_image_1dzezm1dzezm1dze-20260929-213630-1f8496.jpeg";
  const resolveImage=(path)=>{
    const value=String(path||"").trim();
    if(!value)return PLACEHOLDER_IMAGE;
    if(/^https?:\/\//i.test(value))return value;
    if(value.startsWith("//"))return window.location.protocol+value;
    if(value.startsWith("/beta/"))return value;
    if(value.startsWith("/"))return "/beta"+value;
    if(/^images\//i.test(value)||/^assets\//i.test(value))return value.replace(/^\.\//,"");
    return "images/listings/"+value.replace(/^\.\//,"").replace(/^\/+/,"");
  };
  const ageText=(a)=>{
    const ages=Array.isArray(a?.age_range)?a.age_range.filter(Boolean):[];
    return ages.length?ages.join(", "):"All ages";
  };
  const locationText=(a)=>{
    const venue=Array.isArray(a?.venues)&&a.venues[0]?a.venues[0]:null;
    return venue?.town||venue?.region||a?.town||a?.region||"Devon & Cornwall";
  };
  const priceText=(a)=>{
    if(a?.price)return a.price+" per session";
    return "See booking details";
  };
  const categoryText=(a)=>a?.category||"Family activity";

  const render=async()=>{
    try{
      const items=await bhActivities();
      if(!Array.isArray(items)||!items.length)return;

      const available=items.filter(a=>a&&a.id);
      const readStoredPreferences=()=>{
        try{
          const raw=localStorage.getItem("bhPreferences");
          const parsed=raw?JSON.parse(raw):{};
          return parsed&&typeof parsed==="object"?parsed:{};
        }catch(e){return {}}
      };
      const stored=readStoredPreferences();
      let preferences=stored;
      try{
        const auth=await bhAuthSession();
        if(auth?.authenticated){
          const response=await fetch("api/preferences.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
          if(response.ok){
            const payload=await response.json();
            if(payload?.ok&&payload.preferences){
              preferences={...stored,...payload.preferences};
              localStorage.setItem("bhPreferences",JSON.stringify(preferences));
            }
          }
        }
      }catch(e){}

      const preferredTown=String(preferences.town||"").trim().toLowerCase();
      const preferredRegion=String(preferences.region||"").trim().toLowerCase();
      const venueTown=a=>Array.isArray(a?.venues)
        ?a.venues.map(v=>String(v?.town||"").trim()).filter(Boolean)
        :[];
      const venueRegion=a=>Array.isArray(a?.venues)
        ?a.venues.map(v=>String(v?.region||"").trim()).filter(Boolean)
        :[];

      const townMatches=preferredTown
        ?available.filter(a=>venueTown(a).some(t=>t.toLowerCase()===preferredTown))
        :[];
      const regionMatches=preferredRegion
        ?available.filter(a=>venueRegion(a).some(r=>r.toLowerCase()===preferredRegion))
        :[];

      // Personalise the front door using the user's saved usual town.
      // Matching is against VENUE town, never the activity title/category.
      // If there are fewer than four local matches, fill the remaining cards
      // from the wider directory so the homepage never looks empty.
      const selected=[...townMatches,...regionMatches,...available]
        .filter((a,index,self)=>self.findIndex(x=>String(x.id)===String(a.id))===index)
        .slice(0,12);

      if(!selected.length)return;

      grid.innerHTML="";
      grid.parentElement?.querySelectorAll(".home-carousel-control").forEach(el=>el.remove());
      if(selected.length>4){
        const makeControl=(direction,label)=>{
          const button=document.createElement("button");
          button.type="button";
          button.className="home-carousel-control "+direction;
          button.setAttribute("aria-label",label);
          button.textContent=direction==="prev"?"‹":"›";
          button.addEventListener("click",()=>{
            const amount=Math.max(grid.clientWidth*.82,grid.clientWidth/2);
            grid.scrollBy({left:direction==="next"?amount:-amount,behavior:"smooth"});
          });
          grid.parentElement.appendChild(button);
        };
        makeControl("prev","Previous activities");
        makeControl("next","Next activities");
      }
      selected.forEach((a,index)=>{
        const card=document.createElement("a");
        card.className="home-activity-card";
        card.href="activity.html?slug="+encodeURIComponent(a.slug||"")+"&id="+encodeURIComponent(a.id);
        const imageWrap=document.createElement("div");
        imageWrap.className="home-card-image";
        const img=document.createElement("img");
        img.src=resolveImage(a.image_url);
        img.alt=a.title||"Family activity";
        img.addEventListener("error",()=>{
          if(img.src.endsWith(PLACEHOLDER_IMAGE))return;
          img.src=PLACEHOLDER_IMAGE;
        },{once:true});
        imageWrap.appendChild(img);

        const age=document.createElement("span");
        age.className="home-age-badge";
        if(index%4===1)age.classList.add("pink");
        age.textContent=ageText(a);

        const heart=document.createElement("button");
        heart.className="home-heart";
        heart.type="button";
        heart.setAttribute("aria-label","Save "+(a.title||"activity"));
        heart.textContent=bhIsSaved(a.id)?"♥":"♡";
        if(bhIsSaved(a.id))heart.classList.add("is-saved");
        heart.addEventListener("click",event=>{
          event.preventDefault();
          event.stopPropagation();
          const saved=bhToggleSaved(a.id);
          heart.textContent=saved?"♥":"♡";
          heart.classList.toggle("is-saved",saved);
        });

        const body=document.createElement("div");
        body.className="home-card-body";
        const title=document.createElement("h3");
        setText(title,a.title);
        const location=document.createElement("p");
        setText(location,"⌖ "+locationText(a));
        const category=document.createElement("p");
        setText(category,"♟ "+categoryText(a));
        const price=document.createElement("p");
        setText(price,"◷ "+priceText(a));
        body.append(title,location,category,price);
        card.append(imageWrap,age,heart,body);
        grid.appendChild(card);
      });
    }catch(error){
      /* Keep the approved static cards as a graceful fallback if the API is unavailable. */
    }
  };
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",render,{once:true});
  else render();
})();


/* Load the single site-wide header component. The component owns its markup and CSS. */
(function(){
  const current=document.currentScript;
  const src=current?.src||new URL("assets/js/app.js",document.baseURI).href;
  const headerSrc=new URL("header.js",src).href;
  if(!document.querySelector('script[data-bh-header-loader]')){
    const script=document.createElement("script");
    script.src=headerSrc;
    script.defer=true;
    script.dataset.bhHeaderLoader="true";
    document.head.appendChild(script);
  }
})();

/* Shared Directory / Map hero component.
   Markup lives in components/directory-hero.html and page scripts initialise it. */
(function(){
  const placeholder=document.querySelector("[data-bh-directory-hero-placeholder]");
  if(!placeholder)return;
  window.bhDirectoryHeroReady=(async()=>{
    try{
      const response=await fetch(new URL("components/directory-hero.html",document.baseURI),{cache:"no-store"});
      if(!response.ok)throw new Error("Could not load directory hero");
      const html=await response.text();
      const page=placeholder.dataset.heroPage||(document.body.classList.contains("bh-map-page")?"map":"directory");
      placeholder.outerHTML=html;
      const hero=document.querySelector("[data-bh-directory-hero]");
      const ids=page==="map"
        ?{form:"mapFilters",keyword:"mapSearch",region:"mapRegion",town:"mapTown",category:"mapCategory",day:"mapDay",filters:"openMapFilters"}
        :{form:"directoryHeroSearch",keyword:"search",region:"heroRegion",town:"heroTown",category:"heroCategory",day:"heroDay",filters:"openSearchFilters"};
      if(hero){
        const form=hero.querySelector("[data-bh-hero-form]");
        if(form){form.id=ids.form;form.action=page==="map"?"map.html":"directory.html";}
        Object.entries({keyword:ids.keyword,region:ids.region,town:ids.town,category:ids.category,day:ids.day}).forEach(([key,id])=>{
          const el=hero.querySelector('[data-bh-hero-field="'+key+'"]');if(el)el.id=id;
        });
        const filterButton=hero.querySelector("[data-bh-hero-filters]");if(filterButton)filterButton.id=ids.filters;
        const viewLink=hero.querySelector(".directory-map-hero-button");
        if(viewLink){
          const query=window.location.search||"";
          viewLink.href=page==="map" ? "directory.html"+query : "map.html"+query;
          viewLink.textContent=page==="map" ? "View list of activities →" : "View activities on map →";
          viewLink.setAttribute("aria-label",page==="map" ? "View list of activities" : "View activities on map");
        }
        hero.dataset.heroPage=page;

        if(page==="calendar"){
          if(filterButton){
            filterButton.textContent="More search options →";
            filterButton.addEventListener("click",event=>{
              event.preventDefault();
              window.location.href="directory.html"+(window.location.search||"");
            });
          }
          try{
            const activities=await bhActivities();
            const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
            const values={
              category:[...new Set(activities.map(a=>categoryMap[a.category]||a.category).filter(Boolean))].sort(),
              region:[...new Set(activities.flatMap(a=>bhVenues(a).map(v=>v.region||a.region)).filter(Boolean))].sort(),
              town:[...new Set(activities.flatMap(a=>bhVenues(a).map(v=>v.town||a.town)).filter(Boolean))].sort()
            };
            Object.entries(values).forEach(([key,list])=>{
              const select=hero.querySelector('[data-bh-hero-field="'+key+'"]');
              if(!select)return;
              const current=new URLSearchParams(window.location.search).get(key)||"";
              const label=key.charAt(0).toUpperCase()+key.slice(1);
              select.innerHTML="<option value=\"\">"+label+"</option>"+list.map(value=>"<option value=\""+bhEscape(value)+"\">"+bhEscape(value)+"</option>").join("");
              if(list.includes(current))select.value=current;
            });
            const keyword=hero.querySelector('[data-bh-hero-field="keyword"]');
            if(keyword)keyword.value=new URLSearchParams(window.location.search).get("keyword")||"";
          }catch(_){}
        }

        const popular=hero.querySelector("[data-bh-popular-categories]");
        if(popular && window.bhPopulatePopularCategories) window.bhPopulatePopularCategories(popular);
      }
      return hero;
    }catch(error){console.warn("Bubba Hub directory hero could not load.",error);return null;}
  })();
})();

/* Populate Directory/Map popular categories dynamically. */
window.bhPopulatePopularCategories = async function(container) {
  if (!container) return;
  try {
    const response = await fetch(new URL("data/activities.json", document.baseURI), { cache: "no-store" });
    if (!response.ok) throw new Error("activities data unavailable");
    const data = await response.json();
    const items = Array.isArray(data) ? data : (Array.isArray(data.activities) ? data.activities : []);
    const counts = new Map();
    items.forEach(item => {
      const raw = item.category || item.categories || item.type || "";
      const cats = Array.isArray(raw) ? raw : String(raw).split(/[,|]/);
      cats.map(x => String(x).trim()).filter(Boolean).forEach(cat => {
        const key = cat.toLowerCase();
        const existing = counts.get(key);
        counts.set(key, { label: existing ? existing.label : cat, count: (existing ? existing.count : 0) + 1 });
      });
    });
    const popular = [...counts.values()].sort((a,b) => b.count - a.count || a.label.localeCompare(b.label)).slice(0, 6);
    const icons = {"baby classes":"🍼","toddler groups":"👣","music & movement":"🎵","swimming":"🏊","soft play":"🧸","family activities":"👨‍👩‍👧"};
    container.innerHTML = '<span class="directory-popular-label">Popular:</span>';
    popular.forEach(item => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "directory-popular-chip";
      button.dataset.category = item.label;
      button.textContent = (icons[item.label.toLowerCase()] || "⭐") + " " + item.label;
      container.appendChild(button);
    });
    if (!popular.length) container.innerHTML = "";
    container.dispatchEvent(new CustomEvent("bh:popular-ready", { bubbles: true }));
  } catch (error) {
    console.warn("Bubba Hub popular categories could not be loaded.", error);
    container.innerHTML = "";
  }
};
