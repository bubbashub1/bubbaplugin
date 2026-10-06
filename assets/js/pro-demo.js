/* Bubba Hub demo-data adapter.
   Demo data is read-only and intentionally mirrors the future API shape.
   Live APIs remain the source of truth whenever they are available. */
window.BH_DEMO_MODE = window.BH_DEMO_MODE ?? (new URLSearchParams(location.search).get('demo') === '1');
window.bhLoadDemoActivities = async function(){
  if(window.__bhDemoActivities) return window.__bhDemoActivities;
  const response = await fetch("/data/pro-demo.json",{cache:"no-store",headers:{Accept:"application/json"}});
  if(!response.ok) throw new Error("Demo data unavailable");
  const payload = await response.json();
  const rows = Array.isArray(payload.activities) ? payload.activities : [];
  const extra = JSON.parse(localStorage.getItem("BH_DEMO_LEADER_EXTRA") || "[]");
  rows.push(...extra.map(a=>({...a, category:a.category||"Family activity"})));
  window.__bhDemoActivities = rows.map((a,index)=>{
    const venues = Array.isArray(a.venues) ? a.venues : [{
      id:String(a.id||index+1)+"-venue",
      name:a.venue||"Demo venue",
      address:a.venue||"",
      town:a.town||"",
      region:a.region||"",
      postcode:"",
      lat:null,long:null,latitude:null,longitude:null,
      sessions:[{
        id:String(a.id||index+1)+"-session",
        day:a.day||"",
        day_of_week:null,
        start:a.time||"",
        time:a.time||"",
        end:"",
        end_time:"",
        duration:"",
        duration_minutes:null,
        price:a.price!=null?"£"+Number(a.price).toFixed(2):"",
        price_value:a.price,
        term_time:"",
        frequency:"",
        start_date:"",
        end_date:""
      }]
    }];
    const sessions = venues.flatMap(v=>Array.isArray(v.sessions)?v.sessions:[]);
    return {
      ...a,
      id:String(a.id||index+1),
      category:a.category||"Family activity",
      categories:Array.isArray(a.categories)?a.categories:[a.category||"Family activity"],
      county:a.county||a.region||"",
      age_range:Array.isArray(a.age_range)?a.age_range:[],
      age_min_months:a.age_min_months??null,
      age_max_months:a.age_max_months??null,
      tags:Array.isArray(a.tags)?a.tags:[],
      accessibility:Array.isArray(a.accessibility)?a.accessibility:[],
      price:a.price!=null?"£"+Number(a.price).toFixed(2):"",
      price_value:a.price??null,
      image_url:a.image_url||"/wp-content/uploads/logo/placeholder.jpeg",
      short_description:a.description||"",
      summary:a.description||"",
      content:a.description||"",
      organiser_id:a.organiser?.id||"",
      organiser_name:a.organiser?.name||a.organiser_name||"",
      organiser:a.organiser?.name||a.organiser_name||"",
      organiser_website:a.organiser?.website||"",
      organiser_email:a.organiser?.email||"",
      organiser_phone:a.organiser?.phone||"",
      organiser_slug:a.organiser?.slug||"",
      venues,
      booking_mode:a.booking_url?"external":"reserve",
      booking_slots:[],
      booking_url:a.booking_url||""
    };
  });
  return window.__bhDemoActivities;
};