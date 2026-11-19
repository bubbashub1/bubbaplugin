(() => {
  "use strict";
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  const normal = value => String(value || "").trim();
  const directoryUrl = values => {
    const params = new URLSearchParams();
    Object.entries(values).forEach(([key,value]) => { if (normal(value)) params.set(key, value); });
    return "/directory.html" + (params.size ? "?" + params.toString() : "");
  };
  const imageFor = activity => {
    const path = normal(activity?.image_path || activity?.image_url);
    if (!path) return "/wp-content/uploads/logo/placeholder.jpeg";
    if (/^https?:\/\//i.test(path)) return path;
    return path.startsWith("/") ? path : "/" + path.replace(/^\.\//,"");
  };
  const getCategories = activity => {
    const raw = activity?.category;
    return (Array.isArray(raw) ? raw : String(raw || "").split(/[,;|]/)).map(normal).filter(Boolean);
  };
  const getVenues = activity => Array.isArray(activity?.venues) ? activity.venues : [];
  const regionOf = activity => normal(getVenues(activity)[0]?.region || activity?.region || activity?.county);
  const townOf = activity => normal(getVenues(activity)[0]?.town || activity?.town);
  const activityUrl = activity => {
    const p = new URLSearchParams();
    if (activity.slug) p.set("slug", activity.slug);
    if (activity.id) p.set("id", activity.id);
    return "/activity.html" + (p.size ? "?" + p.toString() : "");
  };
  const setMessage = (id, message) => { const node=$(id); if(node) node.innerHTML='<p class="explore-loading">'+esc(message)+'</p>'; };

  async function init() {
    const form=$("exploreSearch"), regionSelect=$("exploreRegion"), townSelect=$("exploreTown");
    form?.addEventListener("submit", event => {
      event.preventDefault();
      location.href=directoryUrl({keyword:$("exploreKeyword")?.value,region:regionSelect?.value,town:townSelect?.value});
    });
    regionSelect?.addEventListener("change", () => fillTowns(regionSelect.value, allActivities));
    let response;
    try {
      const res=await fetch("/api/activities.php?page=1&per_page=200",{headers:{Accept:"application/json"},cache:"no-store"});
      if(!res.ok) throw new Error("The activity directory is temporarily unavailable.");
      response=await res.json();
      if(!response?.ok || !Array.isArray(response.data)) throw new Error(response?.error || "Activity data could not be loaded.");
      let rows=response.data;
      const pages=Number(response.pagination?.pages||1);
      for(let page=2;page<=pages;page++){const pageRes=await fetch("/api/activities.php?page="+page+"&per_page=200",{headers:{Accept:"application/json"},cache:"no-store"});if(!pageRes.ok)continue;const pageData=await pageRes.json();if(pageData?.ok&&Array.isArray(pageData.data))rows=rows.concat(pageData.data);}
      response.data=rows;
    } catch(error) {
      setMessage("exploreCategories",error.message);
      setMessage("exploreActivities","Please try the full activity directory instead.");
      setMessage("exploreRegions","Areas are temporarily unavailable.");
      return;
    }
    allActivities=response.data.filter(item=>item && item.status!=="draft");
    render(allActivities);
    window.setInterval(async()=>{try{const res=await fetch("/api/activities.php?page=1&per_page=200",{headers:{Accept:"application/json"},cache:"no-store"});const data=await res.json();if(!res.ok||!data?.ok)return;let rows=data.data||[];for(let page=2;page<=Number(data.pagination?.pages||1);page++){const pageRes=await fetch("/api/activities.php?page="+page+"&per_page=200",{headers:{Accept:"application/json"},cache:"no-store"});const pageData=await pageRes.json();if(pageData?.ok&&Array.isArray(pageData.data))rows=rows.concat(pageData.data);}allActivities=rows.filter(item=>item&&item.status!=="draft");render(allActivities);}catch(error){}},300000);
  }
  let allActivities=[];
  function fillTowns(region, activities) {
    if(!townSelect) return;
    const towns=[...new Set(activities.filter(a=>!region || regionOf(a).toLowerCase()===region.toLowerCase()).flatMap(a=>getVenues(a).map(v=>normal(v.town)).filter(Boolean).concat(!getVenues(a).length && a.town ? [normal(a.town)] : [])))].sort((a,b)=>a.localeCompare(b));
    townSelect.innerHTML='<option value="">All towns</option>'+towns.map(t=>'<option value="'+esc(t)+'">'+esc(t)+'</option>').join("");
    townSelect.disabled=!region;
  }

  function upcomingDate(session, now) {
    const day=Number(session.day_of_week), parts=String(session.start_time||"").split(":").map(Number);
    if(day<1||day>7||!Number.isFinite(parts[0])||!Number.isFinite(parts[1])) return null;
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    const parse=value=>{if(!value)return null;const p=String(value).slice(0,10).split("-").map(Number);return p.length===3&&p.every(Boolean)?new Date(p[0],p[1]-1,p[2]):null;};
    const start=parse(session.start_date),end=parse(session.end_date);
    if(end&&end<today)return null;
    const jsDay=today.getDay()||7;
    let date=new Date(today);date.setDate(date.getDate()+((day-jsDay+7)%7));
    if(start&&date<start){date=new Date(start);date.setDate(date.getDate()+((day-(date.getDay()||7)+7)%7));}
    date.setHours(parts[0],parts[1],0,0);
    if(date<now)date.setDate(date.getDate()+7);
    return end&&date>end?null:date;
  }
  function renderUpcoming(activities) {
    const node=$("exploreUpcoming");if(!node)return;
    const now=new Date(),items=[];
    activities.forEach(a=>(Array.isArray(a.sessions)?a.sessions:[]).forEach(s=>{
      const date=upcomingDate(s,now);if(!date)return;
      const venue=(Array.isArray(a.venues)?a.venues:[]).find(v=>String(v.id)===String(s.venue_id))||a.venues?.[0]||{};
      items.push({a,s,date,venue});
    }));
    items.sort((x,y)=>x.date-y.date);
    const rows=items.slice(0,10);
    node.innerHTML=rows.length?rows.map((x,i)=>{
      const d=x.date,today=new Date(),tomorrow=new Date(today.getFullYear(),today.getMonth(),today.getDate()+1);
      const same=(a,b)=>a.getFullYear()===b.getFullYear()&&a.getMonth()===b.getMonth()&&a.getDate()===b.getDate();
      const day=same(d,today)?"Today":same(d,tomorrow)?"Tomorrow":d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short"});
      const time=d.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit"});
      const place=[x.venue.name,x.venue.town||x.venue.region].filter(Boolean).join(" · ");
      const duration=[x.s.start_time,x.s.end_time].filter(Boolean).map(v=>String(v).slice(0,5)).join("–");
      return '<a class="explore-upcoming-item" href="'+esc(activityUrl(x.a))+'"><span class="explore-upcoming-number">'+String(i+1).padStart(2,"0")+'</span><span class="explore-upcoming-date">'+esc(day+' · '+time)+'</span><span class="explore-upcoming-details"><strong>'+esc(x.a.title||"Family group")+'</strong><small>'+esc(place||"Devon & Cornwall")+'</small></span><span class="explore-upcoming-time">'+esc(duration)+'</span><span class="explore-upcoming-arrow" aria-hidden="true">→</span></a>';
    }).join(""):'<p class="explore-loading">No upcoming scheduled groups are listed just now. Browse all activities to find something for your family.</p>';
    const status=$("exploreRefreshStatus");if(status)status.textContent="Updated "+now.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit"})+" · refreshes every 5 minutes";
  }

  function render(activities) {
    const regions=[...new Set(activities.map(regionOf).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
    if(regionSelect) {
      regionSelect.innerHTML='<option value="">All Devon &amp; Cornwall</option>'+regions.map(r=>'<option value="'+esc(r)+'">'+esc(r)+'</option>').join("");
    }
    fillTowns("",activities);
    const categoryMap=new Map();
    activities.forEach(a=>getCategories(a).forEach(c=>{if(!categoryMap.has(c))categoryMap.set(c,a)}));
    const categoryEntries=[...categoryMap.entries()].sort((a,b)=>a[0].localeCompare(b[0])).slice(0,8);
    const categories=$("exploreCategories");
    if(categories) categories.innerHTML=categoryEntries.length ? categoryEntries.map(([name,activity],i)=>'<a class="explore-category" href="'+esc(directoryUrl({category:name}))+'"><img src="'+esc(imageFor(activity))+'" alt="" loading="lazy"><strong>'+esc(name)+'</strong></a>').join("") : '<p class="explore-loading">No categories are available yet.</p>';
    const featured=activities.filter(a=>a.title).slice(0,6);
    const activityNode=$("exploreActivities");
    if(activityNode) activityNode.innerHTML=featured.length ? featured.map(a=>{
      const category=getCategories(a)[0] || "Family activity";
      const place=[townOf(a),regionOf(a)].filter(Boolean).join(" · ");
      return '<a class="explore-activity" href="'+esc(activityUrl(a))+'"><img src="'+esc(imageFor(a))+'" alt="" loading="lazy"><div class="explore-activity-body"><span class="explore-activity-meta">'+esc(category)+'</span><h3>'+esc(a.title)+'</h3><p>'+esc(place || "Devon & Cornwall")+'</p></div></a>';
    }).join("") : '<p class="explore-loading">No published activities are available at the moment.</p>';
    renderUpcoming(activities);
    const regionNode=$("exploreRegions");
    if(regionNode) regionNode.innerHTML=regions.length ? regions.map(r=>'<a class="explore-region" href="'+esc(directoryUrl({region:r}))+'">'+esc(r)+'</a>').join("") : '<span class="explore-loading">No areas are available yet.</span>';
  }
  let regionSelect, townSelect;
  regionSelect=$("exploreRegion"); townSelect=$("exploreTown");
  document.addEventListener("DOMContentLoaded",init,{once:true});
  if(document.readyState!=="loading") init();
})();