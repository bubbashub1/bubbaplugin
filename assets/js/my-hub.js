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
  function activityAgeRanges(a){
    const source=Array.isArray(a.age_range)?a.age_range:[a.age_range];
    return source.map(v=>{
      const nums=[...String(v||"").replace(/–/g,"-").matchAll(/\d+(?:\.\d+)?/g)].map(m=>Number(m[0]));
      if(!nums.length)return null;
      const raw=String(v||"").toLowerCase();
      if(/\+|plus/.test(raw))return [nums[0],9];
      if(nums.length===1)return [nums[0],Math.min(9,nums[0]+1)];
      return [Math.min(nums[0],nums[1]),Math.max(nums[0],nums[1])];
    }).filter(Boolean);
  }
  function childAge(date){
    if(!date)return null;
    const p=String(date).split("-");if(p.length!==3)return null;
    const dob=new Date(Number(p[0]),Number(p[1])-1,Number(p[2])),now=new Date();
    if(Number.isNaN(dob.getTime()))return null;
    const months=(now.getFullYear()-dob.getFullYear())*12+(now.getMonth()-dob.getMonth())-(now.getDate()<dob.getDate()?1:0);
    return Math.max(0,months/12);
  }
  function activityFitsAge(a,ages){
    if(!ages.length)return true;
    const ranges=activityAgeRanges(a);if(!ranges.length)return true;
    return ages.some(age=>ranges.some(r=>age>=r[0]-.01&&age<=r[1]+.01));
  }
  function activityDays(a){
    const out=[];(Array.isArray(a.venues)?a.venues:[]).forEach(v=>(Array.isArray(v.sessions)?v.sessions:[]).forEach(s=>{if(s.day)out.push(String(s.day))}));
    return [...new Set(out)];
  }
  async function renderBrief(){
    const intro=$("hubBriefIntro"),wrap=$("hubBriefItems");if(!intro||!wrap)return;
    let pref={};try{const pr=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}});const pj=await pr.json();if(pj?.ok)pref=pj.preferences||{}}catch(e){}
    const children=data.children||[];
    const ages=children.map(c=>childAge(c.date_of_birth)).filter(v=>v!==null);
    const saved=new Set((data.saved||[]).map(String));
    const planned=new Set((data.planner||[]).map(x=>String(x.id)));
    const interests=new Set((Array.isArray(pref.categories)?pref.categories:[]).map(String).map(v=>v.toLowerCase()));
    const preferredDay=String(pref.day||"");
    const preferredRegion=String(pref.region||"");
    const preferredTown=String(pref.town||"");
    const maxPrice=pref.maxPrice===""||pref.maxPrice===null?null:Number(pref.maxPrice);
    const ranked=activities.map(a=>{
      let s=0;const price=Number(a.price_value),cat=String(a.category||"").toLowerCase(),days=activityDays(a);
      if(ages.length&&activityFitsAge(a,ages))s+=35;
      if(preferredDay)s+=days.includes(preferredDay)?22:-10;
      if(preferredTown)s+=a.town===preferredTown?20:-6;
      else if(preferredRegion)s+=a.region===preferredRegion?15:-5;
      if(interests.has(cat))s+=18;
      if(maxPrice!==null&&Number.isFinite(price))s+=price<=maxPrice?12:-15;
      if(pref.freeActivities&&Number.isFinite(price))s+=price===0?10:-2;
      if(saved.has(String(a.id)))s+=9;
      if(planned.has(String(a.id)))s+=7;
      return {a,s,days};
    }).filter(x=>x.s>5).sort((a,b)=>b.s-a.s).slice(0,3);
    intro.textContent=ranked.length
      ? (ages.length?"Based on your family profiles":"Based on your saved preferences")+" · updated just now."
      : "Add a family profile or a few preferences and we’ll make this more useful.";
    wrap.innerHTML=ranked.length?ranked.map(x=>{
      const a=x.a,url=typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id);
      const price=Number(a.price_value);
      const priceText=Number.isFinite(price)?(price===0?"Free":"From £"+price.toFixed(2)):(a.price||"Price on request");
      const reason=ages.length&&activityFitsAge(a,ages)?"Age match":preferredDay&&x.days.includes(preferredDay)?preferredDay:(interests.has(String(a.category||"").toLowerCase())?a.category:"Good fit");
      return "<a class='hub-brief-item' href='"+url+"'><div class='hub-brief-icon'>✦</div><div><span>"+esc(reason)+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+" · "+esc(priceText)+"</small></div><b>→</b></a>";
    }).join(""):"<div class='hub-empty'><strong>Your brief is waiting</strong><p>Set your usual area, preferred day or interests in Preferences.</p><a class='button button-soft' href='preferences.html'>Set preferences</a></div>";
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
    void renderBrief();
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