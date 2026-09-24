const BH_KEYS={saved:"bhSavedActivities",planner:"bhPlanner"};
async function bhActivities(){if(window.__bhActivities)return window.__bhActivities;const r=await fetch("data/activities.json");if(!r.ok)throw new Error("Could not load activities");window.__bhActivities=await r.json();return window.__bhActivities;}
function bhGet(key){try{return JSON.parse(localStorage.getItem(key)||"[]")}catch{return[]}}
function bhSet(key,value){localStorage.setItem(key,JSON.stringify(value))}
function bhIsSaved(id){return bhGet(BH_KEYS.saved).includes(id)}
function bhToggleSaved(id){const a=bhGet(BH_KEYS.saved);const i=a.indexOf(id);i>=0?a.splice(i,1):a.push(id);bhSet(BH_KEYS.saved,a);return i<0}
function bhIsPlanned(id){return bhGet(BH_KEYS.planner).includes(id)}
function bhTogglePlanned(id){const a=bhGet(BH_KEYS.planner);const i=a.indexOf(id);i>=0?a.splice(i,1):a.push(id);bhSet(BH_KEYS.planner,a);return i<0}
function bhActivity(id,items){return items.find(x=>x.id===id)||items[0]}
