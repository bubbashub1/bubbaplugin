(()=>{'use strict';
const root=document.querySelector("[data-sector]");if(!root)return;
const type=root.dataset.sector,list=document.getElementById("sectorResults"),status=document.getElementById("sectorStatus"),search=document.getElementById("sectorSearch"),town=document.getElementById("sectorTown"),category=document.getElementById("sectorCategory");
let timer,requestId=0,businessCategoriesLoaded=false;
const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const safeUrl=value=>{const v=String(value||"").trim();return /^https?:\/\//i.test(v)?v:""};
const safeImage=value=>{const v=String(value||"").trim();return safeUrl(v)||(/^\/(?!\/)/.test(v)?v:"")};
function formatDate(value){const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value||''));return match?match[3]+'/'+match[2]+'/'+match[1]:String(value||'')}
function sectorDetails(x){
 const parts=[],verification=safeUrl(x.verification_url);
 if(type==='childcare'){
  const registration=[x.registration_body,x.registration_reference].filter(Boolean).join(' · ');
  if(registration)parts.push('<p><strong>Registration:</strong> '+esc(registration)+'</p>');
  if(x.verified_on)parts.push('<p><strong>Information checked:</strong> '+esc(formatDate(x.verified_on))+'</p>');
  if(verification)parts.push('<p><a href="'+esc(verification)+'" target="_blank" rel="noopener noreferrer">View registration information ↗</a></p>');
 }else if(type==='place'){
  if(x.facilities)parts.push('<p><strong>Facilities:</strong> '+esc(x.facilities)+'</p>');
 }else if(type==='school_club'){
  if(x.school_name)parts.push('<p><strong>School:</strong> '+esc(x.school_name)+'</p>');
  const min=x.age_min!==null&&x.age_min!==undefined&&x.age_min!==''?Number(x.age_min):null,max=x.age_max!==null&&x.age_max!==undefined&&x.age_max!==''?Number(x.age_max):null;
  const age=min!==null&&max!==null?'Ages '+min+'–'+max:min!==null?'From age '+min:max!==null?'Up to age '+max:'';
  if(age)parts.push('<p><strong>Age range:</strong> '+esc(age)+'</p>');
  if(x.days_times)parts.push('<p><strong>Days and times:</strong> '+esc(x.days_times)+'</p>');
  if(x.collection_arrangements)parts.push('<p><strong>Collection:</strong> '+esc(x.collection_arrangements)+'</p>');
  if(x.price_details)parts.push('<p><strong>Cost:</strong> '+esc(x.price_details)+'</p>');
  if(x.verified_on)parts.push('<p><strong>Information checked:</strong> '+esc(formatDate(x.verified_on))+'</p>');
  if(verification)parts.push('<p><a href="'+esc(verification)+'" target="_blank" rel="noopener noreferrer">View club information ↗</a></p>');
 }
 return parts.length?'<div class="bh-sector-details">'+parts.join('')+'</div>':'';
}
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
  list.innerHTML=rows.length?rows.map(x=>type==="business"?businessCard(x):'<article class="bh-sector-card"><h2>'+esc(x.title)+'</h2>'+(x.summary||x.description?'<p>'+esc(x.summary||x.description)+'</p>':'')+'<p class="bh-sector-meta">'+esc([x.category,x.town,x.region].filter(Boolean).join(" · "))+'</p>'+sectorDetails(x)+(safeUrl(x.website_url)?'<p><a href="'+esc(safeUrl(x.website_url))+'" target="_blank" rel="noopener noreferrer">Visit website ↗</a></p>':'')+'</article>').join(""):'<div class="bh-sector-empty">No matching entries. Try another town or search term.</div>';
 }catch(e){if(current!==requestId)return;status.textContent=e.message;list.innerHTML='<div class="bh-sector-empty">We could not load directory results just now. Please try again shortly.</div>'}
 finally{if(current===requestId)list.removeAttribute("aria-busy")}
}
function changed(){clearTimeout(timer);timer=setTimeout(load,250)}
[search,town,category].forEach(x=>x.addEventListener("input",changed));load();
})();