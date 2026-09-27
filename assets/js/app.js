const BH_KEYS={saved:"bhSavedActivities",planner:"bhPlanner"};

async function bhActivities(){
  if(window.__bhActivities)return window.__bhActivities;

  const apiUrl=new URL("api/activities.php",document.baseURI);
  apiUrl.searchParams.set("page","1");
  apiUrl.searchParams.set("per_page","50");

  const response=await fetch(apiUrl.toString(),{cache:"no-store",headers:{Accept:"application/json"}});
  if(!response.ok)throw new Error("Could not load activities");

  const payload=await response.json();
  if(!payload.ok)throw new Error(payload.error||"Could not load activities");

  const rows=Array.isArray(payload.data)?payload.data:[];

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
        start:s.start_time||"",
        time:s.start_time||"",
        end:s.end_time||"",
        end_time:s.end_time||"",
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

    const normalizedVenues=venues.map(v=>({
      id:v.id,
      name:v.name||"Venue",
      address:v.address||"",
      town:v.town||"",
      region:v.region||"",
      postcode:v.postcode||"",
      lat:v.latitude,
      long:v.longitude,
      latitude:v.latitude,
      longitude:v.longitude,
      sessions:sessionByVenue[String(v.id)]||[]
    }));

    return {
      ...a,
      id:String(a.id),
      category:a.category||"",
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

function bhGet(key){try{const value=JSON.parse(localStorage.getItem(key)||"[]");return Array.isArray(value)?value.map(String):[]}catch{return[]}}
function bhSet(key,value){localStorage.setItem(key,JSON.stringify(value.map(String)))}
function bhIsSaved(id){return bhGet(BH_KEYS.saved).includes(String(id))}
function bhToggleSaved(id){const a=bhGet(BH_KEYS.saved),key=String(id),i=a.indexOf(key);i>=0?a.splice(i,1):a.push(key);bhSet(BH_KEYS.saved,a);return i<0}
function bhIsPlanned(id){return bhGet(BH_KEYS.planner).includes(String(id))}
function bhTogglePlanned(id){const a=bhGet(BH_KEYS.planner),key=String(id),i=a.indexOf(key);i>=0?a.splice(i,1):a.push(key);bhSet(BH_KEYS.planner,a);return i<0}
function bhActivity(id,items){return items.find(x=>String(x.id)===String(id))||null}
function bhVenues(activity){if(Array.isArray(activity?.venues)&&activity.venues.length)return activity.venues;return [{id:String(activity?.id||"venue"),name:activity?.location||activity?.town||activity?.region||"Venue",address:activity?.location||"",town:activity?.town||"",region:activity?.region||"",lat:activity?.lat,long:activity?.long,sessions:[{day:activity?.day,time:activity?.time,duration:activity?.duration,price:activity?.price}]}]}
function bhSessions(activity){return bhVenues(activity).flatMap(v=>(Array.isArray(v.sessions)?v.sessions:[]).map(s=>({...s,venue:v})))}
function bhEscape(value){return String(value??"").replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch]))}

function bhActivityBySlug(slug,items){const key=String(slug||"").toLowerCase();return items.find(x=>String(x.slug||"").toLowerCase()===key)||null}
function bhActivityUrl(activity){const slug=String(activity?.slug||"").trim();return slug?encodeURI(slug.replace(/^\/+|\/+$/g,"")+"/"):("activity.html?id="+encodeURIComponent(activity?.id||""))}
