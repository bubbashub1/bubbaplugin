document.addEventListener("DOMContentLoaded",async()=>{
 const root=document.getElementById("savedResults"),count=document.getElementById("savedCount");
 const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
 try{
  const auth=await fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store"}).then(r=>r.json());
  if(!auth.authenticated){location.href="account.html?next="+encodeURIComponent("saved-activities.html");return}
  const data=await fetch("api/my-hub.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
  if(!data.ok)throw new Error(data.message||"Could not load saved activities.");
  const ids=new Set((data.saved||[]).map(String));
  const activities=await bhActivities();
  const saved=activities.filter(a=>ids.has(String(a.id)));
  count.textContent=saved.length+" saved";
  if(!saved.length){root.innerHTML="<div class='hub-empty saved-empty'><strong>Nothing saved yet</strong><p>When you find an activity you like, use its Save button and it will appear here.</p><a class='button button-primary' href='directory.html'>Browse activities</a></div>";return}
  root.innerHTML=saved.map(a=>{
   const url=typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id);
   const image=a.image||a.image_url||"";
   const price=Number(a.price_value);
   const priceText=Number.isFinite(price)?(price===0?"Free":"From £"+price.toFixed(2)):(a.price||"Price on request");
   const age=Array.isArray(a.age_range)?a.age_range.join(", "):(a.age_range||"All ages");
   return "<article class='saved-activity-card'>"+(image?"<a class='saved-activity-image' href='"+url+"'><img src='"+esc(image)+"' alt='' loading='lazy'></a>":"")+"<div class='saved-activity-body'><span class='eyebrow'>"+esc(a.category||"Activity")+"</span><h3><a href='"+url+"'>"+esc(a.title||"Activity")+"</a></h3><p>"+esc(a.town||a.location||"Devon & Cornwall")+"</p><div class='saved-activity-meta'><span>"+esc(age)+"</span><span>"+esc(priceText)+"</span></div><div class='saved-activity-actions'><a class='button button-soft' href='"+url+"'>View activity</a><button class='button button-primary saved-remove' type='button' data-id='"+esc(a.id)+"'>Remove</button></div></div></article>";
  }).join("");
  root.querySelectorAll(".saved-remove").forEach(btn=>btn.addEventListener("click",async()=>{
   const id=String(btn.dataset.id);const next=(data.saved||[]).map(String).filter(x=>x!==id);
   btn.disabled=true;
   try{
    const r=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"sync_saved",saved:next,csrf:auth.csrf||""})});
    const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not remove saved activity.");
    bhSet(BH_KEYS.saved,(j.saved||next).map(String));location.reload();
   }catch(e){btn.disabled=false;alert(e.message||"Could not remove saved activity.")}
  }));
 }catch(e){count.textContent="";root.innerHTML="<div class='hub-empty'><strong>Saved activities unavailable</strong><p>"+esc(e.message||"Please try again.")+"</p></div>"}
});