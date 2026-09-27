document.addEventListener("DOMContentLoaded",async()=>{
const list=document.getElementById("familyList"),message=document.getElementById("familyMessage"),esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));let csrf="";
const get=async()=>{const r=await fetch("api/my-hub.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.message||"Please sign in.");return j};
const age=d=>{if(!d)return "";const x=new Date(d+"T00:00:00"),n=new Date();let y=n.getFullYear()-x.getFullYear(),m=n.getMonth()-x.getMonth();if(n.getDate()<x.getDate())m--;if(m<0){y--;m+=12}return y<2?Math.max(0,y*12+m)+" months":y+" years"};
function modal(title,type,item={}){const o=document.createElement("div");o.className="hub-modal";o.innerHTML="<form class='hub-modal-card'><div class='admin-panel-head'><div><span class='eyebrow'>Family profile</span><h2>"+title+"</h2></div><button type='button' class='button button-soft' data-close>Close</button></div><div class='hub-form-grid'>"+(type==="child"?"<label>Name<input name='name' required maxlength='100' value='"+esc(item.name||"")+"'></label><label>Gender<input name='gender' maxlength='40' value='"+esc(item.gender||"")+"'></label><label>Date of birth<input name='date_of_birth' type='date' value='"+esc(item.date_of_birth||"")+"'></label>":"<label>Nickname<input name='nickname' maxlength='80' value='"+esc(item.nickname||"")+"" placeholder='Optional'></label><label>Due date<input name='due_date' type='date' value='"+esc(item.due_date||"")+"'></label>")+"</div><div class='hero-actions'><button class='button button-primary' type='submit'>Save</button></div><p data-msg class='library-message'></p></form>";
document.body.appendChild(o);o.querySelector("[data-close]").onclick=()=>o.remove();o.onclick=e=>{if(e.target===o)o.remove()};o.querySelector("form").onsubmit=async e=>{e.preventDefault();const p=Object.fromEntries(new FormData(e.target));p.action=type==="child"?"save_child":"save_bump";if(item.id)p.id=item.id;p.csrf=csrf;const m=e.target.querySelector("[data-msg]");try{const r=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)}),j=await r.json();if(!r.ok||!j.ok)throw Error(j.message||j.error||"Could not save.");o.remove();await load()}catch(err){m.textContent=err.message;m.classList.add("is-error")}}}
function schoolTracker(dob){
 if(!dob)return null;
 const d=new Date(dob+"T00:00:00"),startYear=d.getFullYear()+5;
 const applicationDate=new Date(startYear,0,15),startDate=new Date(startYear,8,1),now=new Date();
 const days=Math.ceil((applicationDate-now)/(1000*60*60*24));
 const fmt=x=>x.toLocaleDateString("en-GB",{day:"numeric",month:"long",year:"numeric"});
 return {startYear,applicationDate,startDate,days,deadline:fmt(applicationDate),start:fmt(startDate)};
}
function render(d){
 const c=d.children||[],b=d.bumps||[];
 list.innerHTML=c.concat(b).length?c.map(x=>{
  const tracker=x.date_of_birth?schoolTracker(x.date_of_birth):null;
  let school="";
  if(tracker){
   const countdown=tracker.days>0?"Apply in "+tracker.days+" day"+(tracker.days===1?"":"s"):(tracker.days===0?"Apply today":"Application deadline has passed");
   school="<div class='school-tracker'><span class='school-tracker-icon'>🎓</span><div><strong>Primary school tracker</strong><small>"+esc(countdown)+"</small><p>Reception place for September "+tracker.startYear+". Typical England application deadline: "+esc(tracker.deadline)+"</p></div></div>";
  }
  return "<article class='hub-family-card'><span>👶</span><div class='hub-family-main'><strong>"+esc(x.name)+"</strong><small>"+(x.date_of_birth?age(x.date_of_birth)+" · "+x.date_of_birth:"Date of birth not set")+"</small>"+school+"</div><button class='button button-soft' data-edit-child='"+x.id+"'>Edit</button></article>";
 }).join("")+b.map(x=>"<article class='hub-family-card'><span>🤰</span><div><strong>"+esc(x.nickname||"Baby")+"</strong><small>"+(x.due_date?"Due "+x.due_date:"Due date not set")+"</small></div><button class='button button-soft' data-edit-bump='"+x.id+"'>Edit</button></article>").join(""):"<div class='hub-empty'><strong>Your family profile is empty</strong><p>Add a child or bump profile to get started.</p></div>";
 c.forEach(x=>list.querySelector("[data-edit-child='"+x.id+"']")?.addEventListener("click",()=>modal("Edit child","child",x)));
 b.forEach(x=>list.querySelector("[data-edit-bump='"+x.id+"']")?.addEventListener("click",()=>modal("Edit bump","bump",x)));
}
async function load(){try{const d=await get();csrf=d.csrf||"";render(d);message.textContent="";}catch(e){list.innerHTML="<div class='hub-empty'><strong>Sign in required</strong><p>"+esc(e.message)+"</p><a class='button button-primary' href='account.html'>Sign in</a></div>"}}
document.getElementById("addChild").onclick=()=>modal("Add a child","child");document.getElementById("addBump").onclick=()=>modal("Add a bump","bump");load();
});