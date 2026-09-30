document.addEventListener("DOMContentLoaded", async () => {
  const $ = id => document.getElementById(id);
  const esc = value => bhEscape(value);
  const categoryMap = {"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
  const agePresets = {baby:[0,1], toddler:[1,3], preschool:[3,5], school:[5,9]};
  const parseAgeRange = value => {
    const text = String(value || "").toLowerCase();
    const nums = [...text.matchAll(/(\d+(?:\.\d+)?)/g)].map(m => Number(m[1]));
    if (!nums.length) return null;
    if (/\+/.test(text)) return {min:nums[0],max:9};
    if (nums.length >= 2) return {min:nums[0],max:nums[1]};
    return {min:nums[0],max:nums[0]};
  };
  const ageMatches = (activity,min,max) => {
    if (min === 0 && max === 9) return true;
    const ranges = Array.isArray(activity.age_range) ? activity.age_range : [activity.age_range];
    return ranges.some(r => { const p=parseAgeRange(r); return p && p.max >= min && p.min <= max; });
  };
  const fill = (id, values, selected, label) => {
    const el=$(id); if(!el) return;
    el.innerHTML = '<option value="">'+label+'</option>'+values.map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join("");
    if(values.includes(selected)) el.value=selected;
  };

  try {
    const activities = await bhActivities();
    const params = new URLSearchParams(location.search);
    const categoryValues=[...new Set(activities.map(x=>categoryMap[x.category]||x.category).filter(Boolean))].sort();
    const regionValues=[...new Set(activities.flatMap(x=>bhVenues(x).map(v=>v.region||x.region)).filter(Boolean))].sort();
    const townValues=[...new Set(activities.flatMap(x=>bhVenues(x).map(v=>v.town||x.town)).filter(Boolean))].sort();
    fill("mapCategory",categoryValues,params.get("category")||"","All categories");
    fill("mapRegion",regionValues,params.get("region")||"","All regions");
    fill("mapTown",townValues,params.get("town")||"","All towns");
    $("mapSearch").value=params.get("keyword")||params.get("search")||params.get("q")||"";
    $("mapAge").value=params.get("age_preset")||"";
    $("mapDay").value=params.get("day")||"";
    $("mapPrice").value=params.get("max_price")||"";

    let map=null, markers=[];
    const render=()=>{
      if(!map){
        map=L.map("mapView").setView([50.42,-3.57],10);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"© OpenStreetMap contributors"}).addTo(map);
      }
      markers.forEach(m=>m.remove()); markers=[];
      const search=($("mapSearch")?.value||"").trim().toLowerCase();
      const category=$("mapCategory")?.value||"", region=$("mapRegion")?.value||"", town=$("mapTown")?.value||"";
      const age=agePresets[$("mapAge")?.value||""]||[0,9], day=$("mapDay")?.value||"", maxPrice=$("mapPrice")?.value||"";
      const list=activities.filter(activity=>{
        const venues=bhVenues(activity), sessions=bhSessions(activity);
        const text=[activity.title,activity.description,activity.category,activity.organiser_name,activity.county,activity.age_range,...venues.flatMap(v=>[v.name,v.town,v.region,v.address,v.postcode])].filter(Boolean).join(" ").toLowerCase();
        const locationMatch=(!region&&!town)||venues.some(v=>(!region||String(v.region||activity.region||"").toLowerCase()===region.toLowerCase())&&(!town||String(v.town||activity.town||"").toLowerCase()===town.toLowerCase()));
        const price=Number(activity.price_value??sessions[0]?.price_value??0);
        return (!search||text.includes(search))&&(!category||(categoryMap[activity.category]||activity.category)===category)&&locationMatch&&ageMatches(activity,age[0],age[1])&&(!day||sessions.some(s=>s.day===day))&&(!maxPrice||price<=Number(maxPrice));
      });
      const valid=list.flatMap(activity=>bhVenues(activity).map(venue=>({activity,venue}))).filter(({venue})=>Number.isFinite(Number(venue.lat))&&Number.isFinite(Number(venue.long)));
      const icon=L.divIcon({className:"custom-sleek-dark-marker",html:'<div class="bubba-dark-pin"><div class="dark-core"></div></div>',iconSize:[36,36],iconAnchor:[18,36],popupAnchor:[0,-36]});
      valid.forEach(({activity,venue})=>{
        const sessions=bhSessions(activity), session=sessions.find(s=>s.venue_id==null||String(s.venue_id)===String(venue.id))||sessions[0]||{};
        const ageText=Array.isArray(activity.age_range)?activity.age_range.join(" · "):(activity.age_range||"All ages");
        const price=activity.price||session.price||"Price on request";
        const time=[session.day,session.start&&session.start.slice(0,5)].filter(Boolean).join(" · ");
        const image=activity.image_url?'<img class="bh-map-popup-image" src="'+esc(activity.image_url)+'" alt="'+esc(activity.title)+'">':'<img class="bh-map-popup-image" src="images/logos/gemini_generated_image_1dzezm1dzezm1dze-20260929-213630-1f8496.jpeg" alt="" aria-hidden="true">';
        const popup='<article class="bh-map-popup-card">'+image+'<div class="bh-map-popup-body"><span class="bh-map-popup-category">'+esc(activity.category||"Family activity")+'</span><h3>'+esc(activity.title)+'</h3><p class="bh-map-popup-location">📍 '+esc(venue.name||venue.town||venue.address||"")+'</p><div class="bh-map-popup-meta"><span>👶 '+esc(ageText)+'</span><span>💷 '+esc(price)+'</span>'+(time?'<span>🕒 '+esc(time)+'</span>':'')+'</div><a class="button button-primary bh-map-popup-link" href="'+bhActivityUrl(activity)+'">View activity →</a></div></article>';
        const marker=L.marker([Number(venue.lat),Number(venue.long)],{icon}).addTo(map).bindPopup(popup,{maxWidth:330,minWidth:250,className:"bh-map-popup"});
        markers.push(marker);
      });
      if(valid.length) map.fitBounds(L.latLngBounds(valid.map(({venue})=>[Number(venue.lat),Number(venue.long)])),{padding:[30,30],maxZoom:14});
      else map.setView([50.42,-3.57],10);
      $("mapStatus").textContent=valid.length+" mapped activit"+(valid.length===1?"y":"ies")+" matching your search";
      setTimeout(()=>map.invalidateSize(),50);
    };
    $("mapFilters").addEventListener("submit",e=>{e.preventDefault();const p=new URLSearchParams();const vals={keyword:$("mapSearch").value.trim(),region:$("mapRegion").value,town:$("mapTown").value,category:$("mapCategory").value,age_preset:$("mapAge").value,day:$("mapDay").value,max_price:$("mapPrice").value};Object.entries(vals).forEach(([k,v])=>{if(v)p.set(k,v)});history.replaceState({}, "", p.toString()?"map.html?"+p.toString():"map.html");render();});
    render();
  } catch(error) { $("mapStatus").textContent=error.message||"Activities could not be loaded."; }
});