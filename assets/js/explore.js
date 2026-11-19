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
    } catch(error) {
      setMessage("exploreCategories",error.message);
      setMessage("exploreActivities","Please try the full activity directory instead.");
      setMessage("exploreRegions","Areas are temporarily unavailable.");
      return;
    }
    allActivities=response.data.filter(item=>item && item.status!=="draft");
    render(allActivities);
  }
  let allActivities=[];
  function fillTowns(region, activities) {
    if(!townSelect) return;
    const towns=[...new Set(activities.filter(a=>!region || regionOf(a).toLowerCase()===region.toLowerCase()).flatMap(a=>getVenues(a).map(v=>normal(v.town)).filter(Boolean).concat(!getVenues(a).length && a.town ? [normal(a.town)] : [])))].sort((a,b)=>a.localeCompare(b));
    townSelect.innerHTML='<option value="">All towns</option>'+towns.map(t=>'<option value="'+esc(t)+'">'+esc(t)+'</option>').join("");
    townSelect.disabled=!region;
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
    const regionNode=$("exploreRegions");
    if(regionNode) regionNode.innerHTML=regions.length ? regions.map(r=>'<a class="explore-region" href="'+esc(directoryUrl({region:r}))+'">'+esc(r)+'</a>').join("") : '<span class="explore-loading">No areas are available yet.</span>';
  }
  let regionSelect, townSelect;
  regionSelect=$("exploreRegion"); townSelect=$("exploreTown");
  document.addEventListener("DOMContentLoaded",init,{once:true});
  if(document.readyState!=="loading") init();
})();