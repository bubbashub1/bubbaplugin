(()=>{'use strict';
const root=document.querySelector("[data-sector]");if(!root)return;
const type=root.dataset.sector,list=document.getElementById("sectorResults"),status=document.getElementById("sectorStatus"),search=document.getElementById("sectorSearch"),town=document.getElementById("sectorTown"),category=document.getElementById("sectorCategory");
let timer,requestId=0,businessCategoriesLoaded=false;
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const safeUrl=value=>{const v=String(value||"").trim();return /^https?:\/\//i.test(v)?v:""};
function businessCard(x){
 const logo=safeImage(x.logo_url),website=safeUrl(x.website),email=String(x.contact_email||"").trim(),phone=String(x.contact_phone||"").trim(),place=[x.town,x.region].filter(Boolean).join(" · "),count=Number(x.linked_listing_count)||0;
 return '<article class="bh-sector-card">'+(logo?'<img class="bh-business-logo" src="'+esc(logo)+'" alt="" loading="lazy">':'')+'<h2>'+esc(x.title)+'</h2>'+(x.summary||x.description?'<p>'+esc(x.summary||x.description)+'</p>':'')+'<p class="bh-sector-meta">'+esc([x.category,place].filter(Boolean).join(" · "))+'</p>'+(count?'<p class="bh-sector-meta">'+count+' related '+(count===1?'activity':'activities')+'</p>':'')+(website?'<p><a href="'+esc(website)+'" target="_blank" rel="noopener noreferrer">Visit website ↗</a></p>':'')+(email?'<p><a href="mailto:'+esc(email)+'">Email business</a></p>':'')+(phone?'<p><a href="tel:'+esc(phone)+'">'+esc(phone)+'</a></p>':'')+'</article>';
}
async function load(){
 const current=++requestId;status.textContent="Finding approved entries…";list.setAttribute("aria-busy","true");
 try{
  let endpoint,params=new URLSearchParams();
  if(type==="business"){
   endpoint="/api/directory-businesses.php";
   if(search.value.trim())params.set("q",search.value.trim());
   if(town.value.trim())params.set("town",town.value.trim());
   if(category.value)params.set("category",category.value);
  }else{
   endpoint="/api/directory-sector.php";params.set("type",type);
   if(search.value.trim())params.set("q",search.value.trim());
   if(town.value.trim())params.set("town",town.value.trim());
   if(category.value)params.set("category",category.value);
  }
  const response=await fetch(endpoint+(params.size?"?"+params.toString():""),{cache:"no-store",headers:{Accept:"application/json"}}),data=await response.json();
  if(current!==requestId)return;
  if(!response.ok||!data.ok)throw Error("Directory results are temporarily unavailable.");
  if(type!=="business"&&!data.schema_ready){status.textContent="This section is being prepared. Approved entries will appear here once the Directory setup is installed.";list.innerHTML='<div class="bh-sector-empty">No public entries yet. We are getting this section ready.</div>';return}
  const rows=type==="business"?(data.businesses||[]):(data.records||[]);
  if(type==="business"&&!businessCategoriesLoaded&&!search.value.trim()&&!town.value.trim()&&!category.value){
   const values=[...new Set(rows.map(x=>String(x.category||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
   category.innerHTML='<option value="">All categories</option>'+values.map(value=>'<option value="'+esc(value)+'">'+esc(value)+'</option>').join("");
   businessCategoriesLoaded=true;
  }
  status.textContent=rows.length?rows.length+(type==="business"?" published business profile(s)":" approved result(s)"):"No matching entries yet.";
  list.innerHTML=rows.length?rows.map(x=>type==="business"?businessCard(x):'<article class="bh-sector-card"><h2>'+esc(x.title)+'</h2><p>'+esc(x.summary||x.description||"")+'</p><p class="bh-sector-meta">'+esc([x.category,x.town,x.region].filter(Boolean).join(" · "))+'</p>'+(x.website_url?'<p><a href="'+esc(x.website_url)+'" target="_blank" rel="noopener">Visit website ↗</a></p>':'')+'</article>').join(""):'<div class="bh-sector-empty">No matching entries. Try another town or search term.</div>';
 }catch(e){if(current!==requestId)return;status.textContent=e.message;list.innerHTML='<div class="bh-sector-empty">We could not load directory results just now. Please try again shortly.</div>'}
 finally{if(current===requestId)list.removeAttribute("aria-busy")}
}
function changed(){clearTimeout(timer);timer=setTimeout(load,250)}
[search,town,category].forEach(x=>x.addEventListener("input",changed));load();
})();