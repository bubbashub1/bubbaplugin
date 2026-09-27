document.addEventListener("DOMContentLoaded",async()=>{
  const $=id=>document.getElementById(id),root=$("myHubApp"),message=$("hubMessage");
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  let data=null,csrf="",activities=[];
  const dateDisplay=value=>{if(!value)return "";const p=String(value).split("-");return p.length===3?p[2]+"/"+p[1]+"/"+p[0]:String(value)};
  const ageFromDob=value=>{if(!value)return "";const dob=new Date(value+"T00:00:00");if(Number.isNaN(dob.getTime()))return "";const now=new Date();let years=now.getFullYear()-dob.getFullYear(),months=now.getMonth()-dob.getMonth();if(now.getDate()<dob.getDate())months--;if(months<0){years--;months+=12}return years<2?Math.max(0,years*12+months)+" months":years+" years"};
  const bookingDate=raw=>{if(!raw)return "";const d=new Date(String(raw).replace(" ","T"));if(Number.isNaN(d.getTime()))return "";return d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short"})+" · "+d.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit"})};
  async function getJson(url,opts){const r=await fetch(url,opts);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Something went wrong.");return j}
  async function load(){
    const auth=await getJson("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}}).catch(()=>({authenticated:false}));
    if(!auth.authenticated){root.innerHTML="<section class='admin-panel my-hub-login'><div class='feature-icon'>👤</div><span class='eyebrow'>Your personal hub</span><h2>Sign in to make Bubba Hub yours</h2><p>Keep your family, saved activities, planner and bookings together across devices.</p><a class='button button-primary' href='account.html?next=my-hub.html'>Sign in or create an account</a></section>";return}
    csrf=auth.csrf||"";
    data=await getJson("api/my-hub.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
    const merged=[...new Set([...(data.saved||[]).map(String),...bhGet(BH_KEYS.saved)])];
    if(JSON.stringify(merged)!==JSON.stringify((data.saved||[]).map(String))){const sync=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"sync_saved",saved:merged,csrf})}).then(r=>r.json()).catch(()=>null);if(sync?.ok)data.saved=sync.saved||merged}
    bhSet(BH_KEYS.saved,(data.saved||[]).map(String));
    activities=await bhActivities();
    render();
  }
  function render(){
    const children=data.children||[],bumps=data.bumps||[],savedIds=new Set((data.saved||[]).map(String)),planned=data.planner||[],bookings=data.bookings||[];
    const upcoming=bookings.filter(b=>b.status!=="cancelled").filter(b=>!b.starts_at||new Date(String(b.starts_at).replace(" ","T"))>=new Date()).slice(0,4);
    const plannedActivities=planned.map(x=>activities.find(a=>String(a.id)===String(x.id))).filter(Boolean).slice(0,5);
    const savedActivities=activities.filter(a=>savedIds.has(String(a.id))).slice(0,6);
    $("hubEmail").textContent=data.user?.email||"";
    $("hubStats").innerHTML=[["♡",savedIds.size,"Saved"],["✓",planned.length,"Planned"],["📅",upcoming.length,"Upcoming bookings"],["👶",children.length+bumps.length,"Family profiles"]].map(x=>"<article class='hub-stat'><span>"+x[0]+"</span><strong>"+x[1]+"</strong><small>"+x[2]+"</small></article>").join("");
    $("hubFamily").innerHTML=(children.length||bumps.length)
      ? children.map(c=>"<article class='hub-family-card'><span>👶</span><div><strong>"+esc(c.name)+"</strong><small>"+(c.date_of_birth?dateDisplay(c.date_of_birth)+" · "+ageFromDob(c.date_of_birth):"Date of birth not set")+"</small></div><button class='button button-soft hub-edit-child' data-id='"+c.id+"' type='button'>Edit</button></article>").join("")
        +bumps.map(b=>"<article class='hub-family-card'><span>🤰</span><div><strong>"+esc(b.nickname||"Baby")+"</strong><small>"+(b.due_date?"Due "+dateDisplay(b.due_date):"Due date not set")+"</small></div><button class='button button-soft hub-edit-bump' data-id='"+b.id+"' type='button'>Edit</button></article>").join("")
      : "<div class='hub-empty'><strong>Build your family profile</strong><p>Add your children or a bump profile so Bubba Hub can personalise what you see.</p></div>";
    $("hubSaved").innerHTML=savedActivities.length?savedActivities.map(a=>"<a class='hub-list-card' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'><div><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+" · "+esc(Array.isArray(a.age_range)?a.age_range.join(", "):a.age_range||"")+"</small></div><b>→</b></a>").join(""):"<div class='hub-empty'><strong>No saved activities yet</strong><p>Save activities you like while browsing the directory.</p><a class='button button-soft' href='directory.html'>Find activities</a></div>";
    $("hubPlanner").innerHTML=plannedActivities.length?plannedActivities.map(a=>"<a class='hub-list-card' href='planner.html'><div><span>My Planner</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+"</small></div><b>→</b></a>").join(""):"<div class='hub-empty'><strong>Your planner is empty</strong><p>Add an activity to your weekly plan.</p><a class='button button-soft' href='planner.html'>Open planner</a></div>";
    $("hubBookings").innerHTML=upcoming.length?upcoming.map(b=>"<article class='hub-booking-card'><div><span class='status'>"+esc(b.status||"Reserved")+"</span><strong>"+esc(b.title||"Booking")+"</strong><small>"+esc(b.venue_name||"Venue")+" · "+esc(bookingDate(b.starts_at)||"Date to be confirmed")+"</small></div><span class='hub-booking-qty'>"+Number(b.quantity||1)+"×</span></article>").join(""):"<div class='hub-empty'><strong>No upcoming bookings</strong><p>Your confirmed or reserved bookings will appear here.</p></div>";
    message.textContent="Signed in as "+(data.user?.email||"your account");
    root.querySelectorAll(".hub-edit-child").forEach(btn=>btn.onclick=()=>{const child=children.find(c=>String(c.id)===String(btn.dataset.id));if(child)editChild(child)});
    root.querySelectorAll(".hub-edit-bump").forEach(btn=>btn.onclick=()=>{const bump=bumps.find(b=>String(b.id)===String(btn.dataset.id));if(bump)editBump(bump)});
  }
  function modal(title,body,action){
    const overlay=document.createElement("div");overlay.className="hub-modal";
    overlay.innerHTML="<form class='hub-modal-card'><div class='admin-panel-head'><div><span class='eyebrow'>Family profile</span><h2>"+esc(title)+"</h2></div><button type='button' class='button button-soft' data-close>Close</button></div><div class='hub-form-grid'>"+body+"</div><div class='hero-actions'><button class='button button-primary' type='submit'>Save</button></div><p class='library-message' data-modal-message></p></form>";
    document.body.appendChild(overlay);const form=overlay.querySelector("form"),close=()=>overlay.remove();overlay.querySelector("[data-close]").onclick=close;overlay.addEventListener("click",e=>{if(e.target===overlay)close()});
    form.onsubmit=async e=>{e.preventDefault();const status=form.querySelector("[data-modal-message]");try{const payload=Object.fromEntries(new FormData(form));payload.action=action;payload.csrf=csrf;const r=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify(payload)});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Could not save.");close();await load()}catch(err){status.textContent=err.message||"Could not save.";status.classList.add("is-error")}};
  }
  const editChild=c=>modal("Edit child","<input type='hidden' name='id' value='"+c.id+"'><label>Name<input name='name' required value='"+esc(c.name||"")+"'></label><label>Gender<input name='gender' value='"+esc(c.gender||"")+"'></label><label>Date of birth<input name='date_of_birth' type='date' value='"+esc(c.date_of_birth||"")+"'></label>","save_child");
  const editBump=b=>modal("Edit bump","<input type='hidden' name='id' value='"+b.id+"'><label>Nickname<input name='nickname' value='"+esc(b.nickname||"")+"'></label><label>Due date<input name='due_date' type='date' value='"+esc(b.due_date||"")+"'></label>","save_bump");
  $("addChild").onclick=()=>modal("Add a child","<label>Name<input name='name' required></label><label>Gender<input name='gender'></label><label>Date of birth<input name='date_of_birth' type='date'></label>","save_child");
  $("addBump").onclick=()=>modal("Add a bump","<label>Nickname<input name='nickname' placeholder='Optional'></label><label>Due date<input name='due_date' type='date'></label>","save_bump");
  try{await load()}catch(e){message.textContent=e.message||"My Hub is unavailable.";root.innerHTML="<section class='admin-panel'><h2>My Hub unavailable</h2><p>"+esc(e.message||"We could not load your family hub right now.")+"</p><a class='button button-primary' href='account.html'>Open account</a></section>"}
});