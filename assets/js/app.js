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

  // The API is paginated for performance. The directory needs the complete
  // published set so filtering, map pins and the calendar work across 1,000+
  // activities rather than silently stopping at the first 50.
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
  const m=raw.match(/^(\\d{1,2}):(\\d{2})(?::\\d{2})?$/);
  return m ? String(m[1]).padStart(2,"0")+":"+m[2] : raw;
}
function bhFormatDate(value){
  const raw=String(value||"").trim();
  const m=raw.match(/^(\\d{4})-(\\d{2})-(\\d{2})$/);
  return m ? m[3]+"/"+m[2]+"/"+m[1] : raw;
}
function bhGet(key){try{const value=JSON.parse(localStorage.getItem(key)||"[]");return Array.isArray(value)?value.map(String):[]}catch{return[]}}
function bhSet(key,value){localStorage.setItem(key,JSON.stringify(value.map(String)))}
function bhIsSaved(id){return bhGet(BH_KEYS.saved).includes(String(id))}
async function bhSavedPersist(activityId,saved){
  try{
    const auth=await bhAuthSession();
    if(!auth?.authenticated||!auth.csrf)return false;
    const current=bhGet(BH_KEYS.saved);
    const response=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"sync_saved",saved:current,csrf:auth.csrf})});
    if(!response.ok)return false;
    const data=await response.json();
    if(data.ok&&Array.isArray(data.saved))bhSet(BH_KEYS.saved,data.saved);
    return !!data.ok;
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
