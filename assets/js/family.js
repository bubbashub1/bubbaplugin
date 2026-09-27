document.addEventListener("DOMContentLoaded",async()=>{
const list=document.getElementById("familyList"),message=document.getElementById("familyMessage"),esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));let csrf="";
const get=async()=>{const r=await fetch("api/my-hub.php?view=family",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const j=await r.json();if(!r.ok||!j.ok)throw Error(j.message||"Please sign in.");return j};
const age=d=>{if(!d)return "";const x=new Date(d+"T00:00:00"),n=new Date();let y=n.getFullYear()-x.getFullYear(),m=n.getMonth()-x.getMonth();if(n.getDate()<x.getDate())m--;if(m<0){y--;m+=12}return y<2?Math.max(0,y*12+m)+" months":y+" years"};
function modal(title,type,item={}){
 const o=document.createElement("div");
 o.className="hub-modal";
 const fields=type==="child"
  ? "<label>Name<input name='name' required maxlength='100' value='"+esc(item.name||"")+"'></label><label>Gender<input name='gender' maxlength='40' value='"+esc(item.gender||"")+"'></label><label>Date of birth<input name='date_of_birth' type='date' value='"+esc(item.date_of_birth||"")+"'></label>"
  : "<label>Nickname<input name='nickname' maxlength='80' value='"+esc(item.nickname||"")+"' placeholder='Optional'></label><label>Due date<input name='due_date' type='date' value='"+esc(item.due_date||"")+"'></label>";
 o.innerHTML="<form class='hub-modal-card'><div class='admin-panel-head'><div><span class='eyebrow'>Family profile</span><h2>"+title+"</h2></div><button type='button' class='button button-soft' data-close>Close</button></div><div class='hub-form-grid'>"+fields+"</div><div class='hero-actions'><button class='button button-primary' type='submit'>Save</button></div><p data-msg class='library-message'></p></form>";
 document.body.appendChild(o);
 o.querySelector("[data-close]").onclick=()=>o.remove();
 o.onclick=e=>{if(e.target===o)o.remove()};
 o.querySelector("form").onsubmit=async e=>{
  e.preventDefault();
  const p=Object.fromEntries(new FormData(e.target));
  p.action=type==="child"?"save_child":"save_bump";
  if(item.id)p.id=item.id;
  p.csrf=csrf;
  const m=e.target.querySelector("[data-msg]");
  try{
   const r=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)});
   const j=await r.json();
   if(!r.ok||!j.ok)throw Error(j.message||j.error||"Could not save.");
   o.remove();
   await load();
  }catch(err){m.textContent=err.message;m.classList.add("is-error")}
 };
}
function schoolTracker(dob){
 if(!dob)return null;
 const d=new Date(dob+"T00:00:00"),primaryYear=d.getFullYear()+5,secondaryYear=d.getFullYear()+11;
 const now=new Date(),fmt=x=>x.toLocaleDateString("en-GB",{day:"numeric",month:"long",year:"numeric"});
 const primaryOpen=new Date(primaryYear-1,10,1),primaryClose=new Date(primaryYear,0,15);
 const secondaryOpen=new Date(secondaryYear-1,8,1),secondaryClose=new Date(secondaryYear-1,9,31);
 const primaryDays=Math.ceil((primaryClose-now)/(1000*60*60*24)),secondaryDays=Math.ceil((secondaryClose-now)/(1000*60*60*24));
 return {primaryYear,secondaryYear,primaryOpen,primaryClose,secondaryOpen,secondaryClose,primaryDays,secondaryDays,primaryOpenText:fmt(primaryOpen),primaryCloseText:fmt(primaryClose),secondaryOpenText:fmt(secondaryOpen),secondaryCloseText:fmt(secondaryClose)};
}
function pregnancyTracker(due){
 if(!due)return null;
 const dueDate=new Date(due+"T00:00:00"),now=new Date();
 const msWeek=7*24*60*60*1000;
 const weeksLeft=Math.ceil((dueDate-now)/msWeek);
 const weeksPreg=Math.max(0,40-weeksLeft);
 const antenatalStart=new Date(dueDate.getTime()-12*msWeek);
 const antenatalEnd=new Date(dueDate.getTime()-8*msWeek);
 const birthReady=new Date(dueDate.getTime()-2*msWeek);
 const fmt=x=>x.toLocaleDateString("en-GB",{day:"numeric",month:"long",year:"numeric"});
 const month=x=>x.toLocaleDateString("en-GB",{month:"long"});
 return {dueDate,weeksLeft,weeksPreg,antenatalStart,antenatalEnd,birthReady,fmt,month};
}
function babyHere(id,nickname){
 const name=prompt("Baby's name",nickname||"Baby");
 if(name===null)return;
 const clean=name.trim();
 if(!clean){alert("Please enter the baby's name.");return}
 const gender=prompt("Gender (optional)","")||"";
 const dob=new Date().toISOString().slice(0,10);
 const p={action:"convert_bump_to_child",id,name:clean,gender,date_of_birth:dob,csrf};
 fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(p)})
 .then(async r=>{const j=await r.json();if(!r.ok||!j.ok)throw Error(j.message||j.error||"Could not convert profile.");await load()})
 .catch(e=>alert(e.message));
}
function render(d){
 const c=d.children||[],b=d.bumps||[];
 list.innerHTML=c.concat(b).length?c.map(x=>{
  const t=x.date_of_birth?schoolTracker(x.date_of_birth):null;
  let school="";
  if(t){
   const primaryOpenNow=new Date()>=t.primaryOpen&&new Date()<=t.primaryClose;
   const secondaryOpenNow=new Date()>=t.secondaryOpen&&new Date()<=t.secondaryClose;
   const primaryStatus=primaryOpenNow?(t.primaryDays>0?"OPEN · "+t.primaryDays+" days left":"CLOSES TODAY"):(t.primaryDays>0?"Opens "+t.primaryOpenText:"Closed");
   const secondaryStatus=secondaryOpenNow?(t.secondaryDays>0?"OPEN · "+t.secondaryDays+" days left":"CLOSES TODAY"):(t.secondaryDays>0?"Opens "+t.secondaryOpenText:"Closed");
   school="<div class='school-tracker'><span class='school-tracker-icon'>🎓</span><div><strong>School application tracker</strong><div class='school-tracker-row'><b>Primary · Sep "+t.primaryYear+"</b><span class='school-tracker-status'>"+esc(primaryStatus)+"</span></div><small>Opens "+esc(t.primaryOpenText)+" · closes "+esc(t.primaryCloseText)+"</small><div class='school-tracker-row'><b>Secondary · Sep "+t.secondaryYear+"</b><span class='school-tracker-status'>"+esc(secondaryStatus)+"</span></div><small>Opens "+esc(t.secondaryOpenText)+" · closes "+esc(t.secondaryCloseText)+"</small></div></div>";
  }
  return "<article class='hub-family-card'><span>👶</span><div class='hub-family-main'><strong>"+esc(x.name)+"</strong><small>"+(x.date_of_birth?age(x.date_of_birth)+" · "+x.date_of_birth:"Date of birth not set")+"</small>"+school+"</div><button class='button button-soft' data-edit-child='"+x.id+"'>Edit</button></article>";
 }).join("")+b.map(x=>{
  const t=pregnancyTracker(x.due_date);
  let tracker="";
  if(t){
   const now=new Date();
   const daysLeft=Math.max(0,Math.ceil((t.dueDate-now)/(24*60*60*1000)));
   const antenatalActive=now>=t.antenatalStart&&now<=t.antenatalEnd;
   const birthReady=now>=t.birthReady;
   const weeksText=t.weeksPreg>=40?"40+ weeks":t.weeksPreg+" weeks";
   const dueText=daysLeft===0?"Due today":daysLeft===1?"Due tomorrow":daysLeft>0?daysLeft+" days to go":"Due date passed";
   const antenatalText=antenatalActive?"Now is a good time for antenatal classes":t.weeksPreg<28?"Attend antenatal classes from "+t.month(t.antenatalStart)+" to "+t.month(t.antenatalEnd):"Recommended antenatal window: "+t.month(t.antenatalStart)+" to "+t.month(t.antenatalEnd);
   tracker="<div class='pregnancy-tracker'><span class='pregnancy-tracker-icon'>🤰</span><div><strong>Pregnancy tracker</strong><div class='pregnancy-due'><b>"+esc(weeksText)+"</b><span>"+esc(dueText)+"</span></div><small>Due "+esc(t.fmt(t.dueDate))+"</small><div class='pregnancy-antenatal'><b>💛 Antenatal classes</b><span>"+esc(antenatalText)+"</span><small>28–32 weeks · "+esc(t.fmt(t.antenatalStart))+" to "+esc(t.fmt(t.antenatalEnd))+"</small></div>"+(birthReady?"<button type='button' class='button button-primary baby-here-button' data-baby-here='"+x.id+"'>👶 Baby is here!</button>":"")+"</div></div>";
  }
  return "<article class='hub-family-card'><span>🤰</span><div class='hub-family-main'><strong>"+esc(x.nickname||"Baby")+"</strong><small>"+(x.due_date?"Due "+esc(x.due_date):"Due date not set")+"</small>"+tracker+"</div><button class='button button-soft' data-edit-bump='"+x.id+"'>Edit</button></article>";
 }).join(""):"<div class='hub-empty'><strong>Your family profile is empty</strong><p>Add a child or bump profile to get started.</p></div>";
 c.forEach(x=>list.querySelector("[data-edit-child='"+x.id+"']")?.addEventListener("click",()=>modal("Edit child","child",x)));
 b.forEach(x=>list.querySelector("[data-edit-bump='"+x.id+"']")?.addEventListener("click",()=>modal("Edit bump","bump",x)));
 b.forEach(x=>list.querySelector("[data-baby-here='"+x.id+"']")?.addEventListener("click",()=>babyHere(x.id,x.nickname)));
}
async function load(){try{const d=await get();csrf=d.csrf||"";render(d);message.textContent="";}catch(e){list.innerHTML="<div class='hub-empty'><strong>Sign in required</strong><p>"+esc(e.message)+"</p><a class='button button-primary' href='account.html'>Sign in</a></div>"}}
document.getElementById("addChild").onclick=()=>modal("Add a child","child");document.getElementById("addBump").onclick=()=>modal("Add a bump","bump");load();
});