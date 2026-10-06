(function(){
"use strict";
const $=s=>document.querySelector(s);
const esc=v=>String(v??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[c]));
let results=[];
function status(msg,error=false){const el=$("#listingCheckStatus");el.textContent=msg||"";el.classList.toggle("is-error",!!error)}
function busy(button,on){if(button)button.disabled=on}
function renderSummary(s){$("#listingCheckSummary").innerHTML=[["total","Listings checked"],["changed","Changed"],["missing","Possible missing"],["new","New opportunities"],["same","No change"]].map(([k,l])=>'<article class="listing-check-stat"><strong>'+Number(s?.[k]||0)+'</strong><span>'+l+'</span></article>').join("")}
function sourceLinks(r){return (r.sources||[]).slice(0,6).map(x=>'<a href="'+esc(x.url)+'" target="_blank" rel="noopener">'+esc(x.label||x.url)+'</a>').join("")}
function render(){
 const box=$("#listingCheckResults");
 if(!results.length){box.innerHTML='<div class="listing-check-empty"><strong>No findings to review.</strong><p class="admin-help">Run a check above.</p></div>';return}
 box.innerHTML=results.map(r=>{
  const cls=String(r.status||"same");
  const changes=(r.changes||[]).map(c=>'<div class="listing-check-change"><strong>'+esc(c.field)+'</strong><span>'+esc(c.before||"—")+' → <b>'+esc(c.after||"—")+'</b></span></div>').join("");
  const evidence='<div class="listing-check-evidence"><article><h3>Website</h3><p>'+esc(r.website_evidence||"No reliable website evidence found.")+'</p></article><article><h3>Social / search</h3><p>'+esc(r.social_evidence||"No reliable social evidence found.")+'</p></article></div>';
  const actions=[];
  if(r.status==="new"&&!r.draft_activity_id)actions.push('<button class="button button-primary lc-create-draft" data-id="'+r.id+'">Create draft listing</button>');
  if(r.draft_activity_id)actions.push('<a class="button button-soft" href="admin/admin-activities-listings.html">Review draft</a>');
  actions.push('<button class="button button-soft lc-dismiss" data-id="'+r.id+'">Dismiss</button>');
  return '<article class="listing-check-card"><div class="listing-check-card-head"><div><div class="listing-check-title">'+esc(r.title||"Untitled")+'</div><div class="listing-check-meta">'+esc([r.website,r.town,r.region].filter(Boolean).join(" · "))+' · '+Number(r.confidence||0)+'% confidence</div></div><span class="listing-check-badge '+cls+'">'+esc(r.label||"No change")+'</span></div>'+evidence+(changes?'<div class="listing-check-changes">'+changes+'</div>':"")+(sourceLinks(r)?'<div class="listing-check-sources">'+sourceLinks(r)+'</div>':"")+'<div class="listing-check-actions-row">'+actions.join("")+'</div></article>'
 }).join("");
}
async function request(action,extra={}){
 const body={action,...extra};
 const r=await fetch("api/admin-listing-check.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},cache:"no-store",body:JSON.stringify(body)});
 const d=await r.json().catch(()=>({}));
 if(!r.ok||!d.ok)throw new Error(d.error||("Listing Check failed (HTTP "+r.status+")."));
 return d;
}
async function load(){
 try{const d=await request("results");results=d.results||[];renderSummary(d.summary||{});render()}catch(e){status(e.message,true)}
}
async function run(mode,button){
 busy(button,true);status(mode==="existing"?"Checking existing listings…":"Searching for new local listings…");
 $("#listingCheckResults").innerHTML='<div class="listing-check-empty"><strong>Checking sources…</strong><div class="listing-check-progress"><span></span></div></div>';
 try{const d=await request("scan",{mode,region:$("#checkRegion").value,query:$("#checkQuery").value});results=d.results||[];renderSummary(d.summary||{});render();status("✓ Listing Check complete. "+results.length+" findings ready to review.")}catch(e){status(e.message,true);load()}finally{busy(button,false)}
}
document.addEventListener("click",async e=>{
 const create=e.target.closest(".lc-create-draft");if(create){busy(create,true);try{const d=await request("create_draft",{id:create.dataset.id});status("✓ Draft created. Open Activities to review it.");await load()}catch(err){status(err.message,true)}finally{busy(create,false)}return}
 const dismiss=e.target.closest(".lc-dismiss");if(dismiss){busy(dismiss,true);try{await request("dismiss",{id:dismiss.dataset.id});await load()}catch(err){status(err.message,true)}finally{busy(dismiss,false)}}
});
$("#checkExisting").addEventListener("click",()=>run("existing",$("#checkExisting")));
$("#findNew").addEventListener("click",()=>run("new",$("#findNew")));
$("#refreshResults").addEventListener("click",load);
load();
})();