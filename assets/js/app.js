const BH_KEYS={saved:"bhSavedActivities",planner:"bhPlanner"};
async function bhActivities(){if(window.__bhActivities)return window.__bhActivities;const r=await fetch("data/activities.json",{cache:"no-store"});if(!r.ok)throw new Error("Could not load activities");const data=await r.json();window.__bhActivities=Array.isArray(data)?data:[];return window.__bhActivities}
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
