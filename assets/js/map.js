const bhInitMap=async()=>{
  const $ = id => document.getElementById(id);
  const esc = value => {
    if (typeof window.bhEscape === "function") return window.bhEscape(value);
    return String(value ?? "").replace(/[&<>"']/g, char => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[char]));
  };
  const categoryMap = {"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
  const agePresets = {baby:[0,1], toddler:[1,3], preschool:[3,5], school:[5,9]};
  const parseAgeRange = value => {
    const text=String(value||"").toLowerCase();
    const nums=[...text.matchAll(/(\d+(?:\.\d+)?)/g)].map(m=>Number(m[1]));
    if(!nums.length)return null;
    if(/\+/.test(text))return {min:nums[0],max:9};
    if(nums.length>=2)return {min:nums[0],max:nums[1]};
    return {min:nums[0],max:nums[0]};
  };
  const ageMatches=(activity,min,max)=>{
    if(min===0&&max===9)return true;
    const ranges=Array.isArray(activity.age_range)?activity.age_range:[activity.age_range];
    return ranges.some(r=>{const p=parseAgeRange(r);return p&&p.max>=min&&p.min<=max;});
  };
  const fill=(id,values,selected,label)=>{
    const el=$(id);if(!el)return;
    el.innerHTML='<option value="">'+label+'</option>'+values.map(v=>'<option value="'+esc(v)+'">'+esc(v)+'</option>').join("");
    if(values.includes(selected))el.value=selected;
  };
  const truthy=v=>["1","true","yes","on"].includes(String(v||"").toLowerCase());
  try{
    const activities=await bhActivities();
    const params=new URLSearchParams(location.search);
    const categoryValues=[...new Set(activities.map(x=>categoryMap[x.category]||x.category).filter(Boolean))].sort();
    const regionValues=[...new Set(activities.flatMap(x=>bhVenues(x).map(v=>v.region||x.region)).filter(Boolean))].sort();
    const townValues=[...new Set(activities.flatMap(x=>bhVenues(x).map(v=>v.town||x.town)).filter(Boolean))].sort();
    fill("mapCategory",categoryValues,params.get("category")||"","All categories");
    fill("mapRegion",regionValues,params.get("region")||"","All regions");
    fill("mapTown",townValues,params.get("town")||"","All towns");
    fill("category",categoryValues,params.get("category")||"","All categories");
    fill("area",regionValues,params.get("region")||"","All regions");
    fill("town",townValues,params.get("town")||"","All towns");
    if ($("mapSearch")) $("mapSearch").value=params.get("keyword")||params.get("search")||params.get("q")||"";
    if ($("mapRegion")) $("mapRegion").value=params.get("region")||"";
    if ($("mapTown")) $("mapTown").value=params.get("town")||"";
    if ($("mapCategory")) $("mapCategory").value=params.get("category")||"";
    if ($("mapAge")) $("mapAge").value=params.get("age_preset")||"";
    if ($("ageRange")) $("ageRange").value=params.get("age_preset")||"";
    if ($("mapDay")) $("mapDay").value=params.get("day")||"";
    if ($("mapPrice")) $("mapPrice").value=params.get("max_price")||"";

    let map=null,markers=[],heatLayer=null,viewMode="pins";
    const setMapMode=mode=>{
      viewMode=mode;
      $("mapPinsMode")?.setAttribute("aria-pressed",String(mode==="pins"));
      $("mapHeatMode")?.setAttribute("aria-pressed",String(mode==="heat"));
      render();
    };
    $("mapPinsMode")?.addEventListener("click",()=>setMapMode("pins"));
    $("mapHeatMode")?.addEventListener("click",()=>setMapMode("heat"));
    const render=()=>{
      if(!map){
        map=window.bhMapEngine?.init("mapView");
      }
      markers.forEach(m=>m.remove());markers=[];
      if(heatLayer){heatLayer.remove();heatLayer=null;}
      const search=($("mapSearch")?.value||"").trim().toLowerCase();
      const category=$("mapCategory")?.value||"",region=$("mapRegion")?.value||"",town=$("mapTown")?.value||"";
      const agePreset=$("mapAge")?.value||$("mapAgeAdvanced")?.value||"";
      const age=agePresets[agePreset]||[Number(params.get("age_min")||0),Number(params.get("age_max")||9)];
      const day=$("mapDay")?.value||"",maxPrice=$("mapPrice")?.value||"";
      const free=params.get("free")||"",sessionLength=params.get("sessionLength")||"",sen=params.get("sen")||"",termTime=params.get("termTime")||"",bookingRequired=params.get("bookingRequired")||"",accessibility=(params.get("accessibility")||"").split(",").filter(Boolean),saved=params.get("saved")||"";
      const list=activities.filter(activity=>{
        const venues=bhVenues(activity),sessions=bhSessions(activity);
        const text=[activity.title,activity.description,activity.category,activity.organiser_name,activity.county,activity.age_range,...venues.flatMap(v=>[v.name,v.town,v.region,v.address,v.postcode])].filter(Boolean).join(" ").toLowerCase();
        const locationMatch=(!region&&!town)||venues.some(v=>(!region||String(v.region||activity.region||"").toLowerCase()===region.toLowerCase())&&(!town||String(v.town||activity.town||"").toLowerCase()===town.toLowerCase()));
        const rawPrice=activity.price_value??sessions[0]?.price_value;
        const price=Number(rawPrice);
        const priceKnown=rawPrice!==null&&rawPrice!==undefined&&rawPrice!==""&&Number.isFinite(price);
        const priceText=String(activity.price||sessions[0]?.price||"").toLowerCase();
        const durations=sessions.map(s=>Number(s.duration_minutes||s.session_length||s.duration)).filter(Number.isFinite).filter(v=>v>0);
        const duration=durations[0]||Number(activity.session_length||0);
        const activityText=JSON.stringify(activity).toLowerCase();
        const senValue=activity.sen_friendly??activity.sen_friendly_flag;
        const termValue=activity.term_time??activity.term_time_only;
        const hasBooking=!!(activity.booking_url||activity.bookingUrl||activity.book_url||activity.reserve_url);
        const durationMatch=!sessionLength||(sessionLength==="181"?durations.some(v=>v>=181):sessionLength==="60"?durations.some(v=>v<=60):sessionLength==="120"?durations.some(v=>v>60&&v<=120):durations.some(v=>v>120&&v<=180));
        const accessibilityMatch=!accessibility.length||accessibility.every(key=>activityText.includes(key));
        const senMatch=!sen||(sen==="yes"?truthy(senValue):!truthy(senValue));
        const termMatch=!termTime||(termTime==="yes"?truthy(termValue):!truthy(termValue));
        const freeMatch=!free||price===0||priceText.includes("free");
        const priceMatch=!maxPrice||(maxPrice==="over30"?(priceKnown&&price>30):((priceKnown&&price<=Number(maxPrice))||((maxPrice==="0")&&priceText.includes("free"))));
        return(!search||text.includes(search))&&(!category||(categoryMap[activity.category]||activity.category)===category)&&locationMatch&&ageMatches(activity,age[0],age[1])&&(!day||sessions.some(s=>s.day===day))&&priceMatch&&freeMatch&&durationMatch&&senMatch&&termMatch&&(!bookingRequired||hasBooking)&&accessibilityMatch&&(!saved||bhIsSaved(activity.id));
      });
      const valid=list.flatMap(activity=>bhVenues(activity).map(venue=>({activity,venue}))).filter(({venue})=>Number.isFinite(Number(venue.lat))&&Number.isFinite(Number(venue.long)));
      const icon=L.divIcon({className:"custom-sleek-dark-marker",html:'<div class="bubba-dark-pin"><div class="dark-core"></div></div>',iconSize:[36,36],iconAnchor:[18,36],popupAnchor:[0,-36]});
      if(viewMode==="heat" && typeof L.heatLayer==="function"){
        heatLayer=L.heatLayer(valid.map(({venue})=>[Number(venue.lat),Number(venue.long),1]),{radius:24,blur:18,maxZoom:13,minOpacity:0.3}).addTo(map);
      }
      if(viewMode!=="heat" || !heatLayer) valid.forEach(({activity,venue})=>{
        const sessions=bhSessions(activity),session=sessions.find(s=>s.venue_id==null||String(s.venue_id)===String(venue.id))||sessions[0]||{};
        const ageText=Array.isArray(activity.age_range)?activity.age_range.join(" · "):(activity.age_range||"All ages");
        const price=activity.price||session.price||"Price on request";
        const time=[session.day,session.start&&session.start.slice(0,5)].filter(Boolean).join(" · ");
        const image=activity.image_url?'<img class="bh-map-popup-image" src="'+esc(activity.image_url)+'" alt="'+esc(activity.title)+'">':'<img class="bh-map-popup-image" src="/wp-content/uploads/logo/placeholder.jpeg" alt="" aria-hidden="true">';
        const popup='<article class="bh-map-popup-card">'+image+'<div class="bh-map-popup-body"><span class="bh-map-popup-category">'+esc(categoryMap[activity.category]||activity.category||"Family activity")+'</span><h3>'+esc(activity.title)+'</h3><p class="bh-map-popup-location">📍 '+esc(venue.name||venue.town||venue.address||"")+'</p><div class="bh-map-popup-meta"><span>👶 '+esc(ageText)+'</span><span>💷 '+esc(price)+'</span>'+(time?'<span>🕒 '+esc(time)+'</span>':'')+'</div><a class="button button-primary bh-map-popup-link" href="'+bhActivityUrl(activity)+'">View activity →</a></div></article>';
        markers.push(L.marker([Number(venue.lat),Number(venue.long)],{icon}).addTo(map).bindPopup(popup,{maxWidth:330,minWidth:250,className:"bh-map-popup"}));
      });
      if(valid.length)map.fitBounds(L.latLngBounds(valid.map(({venue})=>[Number(venue.lat),Number(venue.long)])),{padding:[30,30],maxZoom:14});else map.setView([50.42,-3.57],10);
      $("mapStatus").textContent=list.length+" activit"+(list.length===1?"y":"ies")+" matching your search · "+valid.length+" mapped venue"+(valid.length===1?"":"s")+" shown on map";
      setTimeout(()=>map.invalidateSize(),50);
    };
    const syncUrl=()=>{
      const p=new URLSearchParams();
      const vals={keyword:$("mapSearch")?.value.trim()||"",region:$("mapRegion")?.value||"",town:$("mapTown")?.value||"",category:$("mapCategory")?.value||"",age_preset:$("mapAge")?.value||$("mapAgeAdvanced")?.value||"",day:$("mapDay")?.value||"",max_price:$("mapPrice")?.value||$("mapPriceAdvanced")?.value||""};
      Object.entries(vals).forEach(([k,v])=>{if(v)p.set(k,v)});
      ["age_min","age_max","free","sessionLength","sen","termTime","bookingRequired","accessibility","saved"].forEach(k=>{const v=params.get(k);if(v)p.set(k,v)});
      history.replaceState({}, "", p.toString()?"map.html?"+p.toString():"map.html");
    };
    const advancedMapFields = {
      category: $("category"), region: $("area"), town: $("town"),
      age: $("ageRange"), day: $("day"), price: $("maxPrice"),
      sessionLength: $("sessionLength"), sen: $("sen"), termTime: $("termTime"),
      bookingRequired: $("bookingRequired"), free: $("free")
    };
    const syncAdvancedControls = () => {
      if (advancedMapFields.category) advancedMapFields.category.value = $("mapCategory")?.value || "";
      if (advancedMapFields.region) advancedMapFields.region.value = $("mapRegion")?.value || "";
      if (advancedMapFields.town) advancedMapFields.town.value = $("mapTown")?.value || "";
      if (advancedMapFields.age) advancedMapFields.age.value = $("mapAge")?.value || "";
      if (advancedMapFields.day) advancedMapFields.day.value = $("mapDay")?.value || "";
      if (advancedMapFields.price) advancedMapFields.price.value = $("mapPrice")?.value || "";
      if (advancedMapFields.sessionLength) advancedMapFields.sessionLength.value = params.get("sessionLength")||"";
      if (advancedMapFields.sen) advancedMapFields.sen.value = params.get("sen")||"";
      if (advancedMapFields.termTime) advancedMapFields.termTime.value = params.get("termTime")||"";
      if (advancedMapFields.bookingRequired) advancedMapFields.bookingRequired.checked = params.get("bookingRequired")==="1";
      if (advancedMapFields.free) advancedMapFields.free.checked = params.get("free")==="1";
    };
    const applyAdvancedMapControls = () => {
      if (advancedMapFields.category) $("mapCategory").value = advancedMapFields.category.value;
      if (advancedMapFields.region) $("mapRegion").value = advancedMapFields.region.value;
      if (advancedMapFields.town) $("mapTown").value = advancedMapFields.town.value;
      if (advancedMapFields.age) { if ($("mapAge")) $("mapAge").value = advancedMapFields.age.value; params.set("age_preset",advancedMapFields.age.value); }
      if (advancedMapFields.day) $("mapDay").value = advancedMapFields.day.value;
      if (advancedMapFields.price) $("mapPrice").value = advancedMapFields.price.value;
      if(advancedMapFields.sessionLength?.value) params.set("sessionLength",advancedMapFields.sessionLength.value); else params.delete("sessionLength");
      if(advancedMapFields.sen?.value) params.set("sen",advancedMapFields.sen.value); else params.delete("sen");
      if(advancedMapFields.termTime?.value) params.set("termTime",advancedMapFields.termTime.value); else params.delete("termTime");
      if(advancedMapFields.bookingRequired?.checked) params.set("bookingRequired","1"); else params.delete("bookingRequired");
      if(advancedMapFields.free?.checked) params.set("free","1"); else params.delete("free");
    };
    $("mapFiltersPanel")?.querySelector(".bh-advanced-filters")?.addEventListener("submit",e=>{e.preventDefault();applyAdvancedMapControls();syncUrl();render();});
    $("mapFiltersPanel")?.querySelector("#clear")?.addEventListener("click",()=>{history.replaceState({}, "", "map.html");location.reload()});
    $("mapApplyFilters")?.addEventListener("click",e=>{
      e.preventDefault();
      applyAdvancedMapControls();
      syncUrl();
      render();
      $("mapFiltersPanel")?.classList.remove("is-open");
    });
    $("openMapFilters")?.addEventListener("click",()=>{$("mapFiltersPanel")?.classList.add("is-open");});
    $("closeMapFilters")?.addEventListener("click",()=>{$("mapFiltersPanel")?.classList.remove("is-open");});
    document.querySelectorAll(".directory-popular-chip").forEach(button=>{
      button.addEventListener("click",()=>{
        const category=button.dataset.category||"";
        const select=$("mapCategory");
        if(select){
          const option=[...select.options].find(o=>o.value===category||o.textContent.trim().toLowerCase()===category.toLowerCase()||o.textContent.toLowerCase().includes(category.toLowerCase().split(" ")[0]));
          if(option)select.value=option.value;
        }
        syncUrl();render();
        document.querySelectorAll(".directory-popular-chip").forEach(x=>x.classList.remove("active"));
        button.classList.add("active");
      });
    });
    syncAdvancedControls();
    document.querySelectorAll(".accessibility-option").forEach(el=>el.addEventListener("change",()=>{
      const selected=[...document.querySelectorAll(".map-accessibility-option:checked")].map(x=>x.value);
      if(selected.length) params.set("accessibility",selected.join(",")); else params.delete("accessibility");
      syncUrl(); render();
    }));
    ["mapSearch","mapRegion","mapTown","mapCategory","mapAge","mapDay","mapPrice"].forEach(id=>{const el=$(id);if(!el)return;el.addEventListener("change",()=>{syncUrl();render()});if(id==="mapSearch")el.addEventListener("keydown",e=>{if(e.key==="Enter"){e.preventDefault();syncUrl();render()}})});
    const back=document.querySelector(".map-back-link");if(back)back.href="directory.html"+(location.search||"");
    render();
  }catch(error){$("mapStatus").textContent=error.message||"Activities could not be loaded."}
};
const bhInitMapStart=async()=>{await (window.bhDirectoryHeroReady||Promise.resolve());await (window.bhAdvancedFiltersReady||Promise.resolve());await bhInitMap();};
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",bhInitMapStart,{once:true});else bhInitMapStart();
