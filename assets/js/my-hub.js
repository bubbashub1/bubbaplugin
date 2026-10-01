document.addEventListener("DOMContentLoaded",async()=>{
  const $=id=>document.getElementById(id),root=$("myHubApp"),message=$("hubMessage");
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  let data=null,csrf="",activities=[],adminOnly=false;
  const dateDisplay=value=>{if(!value)return "";const p=String(value).split("-");return p.length===3?p[2]+"/"+p[1]+"/"+p[0]:String(value)};
  const ageFromDob=value=>{if(!value)return "";const dob=new Date(value+"T00:00:00");if(Number.isNaN(dob.getTime()))return "";const now=new Date();let years=now.getFullYear()-dob.getFullYear(),months=now.getMonth()-dob.getMonth();if(now.getDate()<dob.getDate())months--;if(months<0){years--;months+=12}return years<2?Math.max(0,years*12+months)+" months":years+" years"};
  const bookingDate=raw=>{if(!raw)return "";const d=new Date(String(raw).replace(" ","T"));if(Number.isNaN(d.getTime()))return "";return d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short"})+" · "+d.toLocaleTimeString("en-GB",{hour:"2-digit",minute:"2-digit"})};
  async function getJson(url,opts){const r=await fetch(url,opts);const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||j.error||"Something went wrong.");return j}
  async function load(){
    const auth=await getJson("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}}).catch(()=>({authenticated:false}));
    adminOnly=!!auth.is_admin&&!auth.user?.id;
    if(!auth.authenticated){root.innerHTML="<section class='admin-panel my-hub-login'><div class='feature-icon'>👤</div><span class='eyebrow'>Your personal hub</span><h2>Sign in to make Bubba Hub yours</h2><p>Keep your family, saved activities, planner and bookings together across devices.</p><a class='button button-primary' href='account.html?next=my-hub.html'>Sign in or create an account</a></section>";return}
    csrf=auth.csrf||"";
    data=await getJson("api/my-hub.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
    const merged=[...new Set([...(data.saved||[]).map(String),...bhGet(BH_KEYS.saved)])];
    if(!adminOnly&&JSON.stringify(merged)!==JSON.stringify((data.saved||[]).map(String))){const sync=await fetch("api/my-hub.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({action:"sync_saved",saved:merged,csrf})}).then(r=>r.json()).catch(()=>null);if(sync?.ok)data.saved=sync.saved||merged}
    data.saved=merged;
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


  function renderSuggestionCarousel(){
    const wrap=$("hubSuggestionsTrack"); if(!wrap)return;
    const children=data.children||[];
    const ages=children.map(c=>childAge(c.date_of_birth)).filter(v=>v!==null);
    const saved=new Set((data.saved||[]).map(String));
    const planner=new Set((data.planner||[]).map(x=>String(x.id)));
    activities.map(a=>{let score=0;const cat=String(a.category||"").toLowerCase();
      if(ages.length&&activityFitsAge(a,ages))score+=35;
      if(saved.has(String(a.id)))score+=20;
      if(planner.has(String(a.id)))score+=15;
      return {a,score};
    }).sort((x,y)=>y.score-x.score).slice(0,12).forEach(()=>{});
    const ranked=activities.map(a=>{
      let score=0;
      if(ages.length&&activityFitsAge(a,ages))score+=35;
      if(saved.has(String(a.id)))score+=18;
      if(planner.has(String(a.id)))score+=12;
      if(data.user?.region&&String(a.region||"").toLowerCase()===String(data.user.region).toLowerCase())score+=10;
      return {a,score};
    }).sort((x,y)=>y.score-x.score).slice(0,12);
    wrap.innerHTML=ranked.map(({a})=>{
      const url=typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id);
      return "<a class='hub-suggestion-card' href='"+url+"'><div class='hub-suggestion-icon'>✦</div><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+" · "+esc(a.age_range||"")+"</small><b>View activity →</b></a>";
    }).join("")||"<div class='hub-empty'>Set your preferences to get personalised suggestions.</div>";
    const track=wrap.parentElement;
    $("hubSuggestionsPrev").onclick=()=>track.scrollBy({left:-Math.max(260,track.clientWidth*.75),behavior:"smooth"});
    $("hubSuggestionsNext").onclick=()=>track.scrollBy({left:Math.max(260,track.clientWidth*.75),behavior:"smooth"});
  }
  function renderUpcomingTicker(){
    const wrap=$("hubTickerTrack"); if(!wrap)return;
    const upcoming=(data.planner||[]).map(p=>{const a=activities.find(x=>String(x.id)===String(p.id));return a?{a,p}:null}).filter(Boolean).slice(0,8);
    wrap.innerHTML=upcoming.length?upcoming.map(({a,p})=>"<a href='planner.html'><strong>"+esc(p.date||p.starts_at||"Upcoming")+"</strong><span>"+esc(a.title)+"</span></a>").join(""):"<span>Add activities to My Planner and your upcoming plans will appear here.</span>";
  }

  function setupWhatShallI(){
    const panel=$("whatShallI"),open=$("openWhatShallI"),close=$("closeWhatShallI");
    const steps=panel?.querySelectorAll(".what-shall-i-step"),startScreen=$("whatShallIStart"),begin=$("beginWhatShallI"),regions=$("whatRegionChoices"),towns=$("whatTownChoices"),days=$("whatDayChoices"),cats=$("whatCategoryChoices"),results=$("whatShallIResults"),resultsGrid=$("whatResultsGrid"),resultsTitle=$("whatResultsTitle"),again=$("whatStartAgain");
    if(!panel||!open||!regions||!towns||!days||!cats)return;
    let choice={region:"",town:"",day:"",category:""};
    const unique=values=>[...new Set(values.filter(Boolean).map(v=>String(v).trim()))].sort((a,b)=>a.localeCompare(b));
    const venuesFor=a=>Array.isArray(a.venues)?a.venues:[];
    const regionsFor=a=>unique([a.region,...venuesFor(a).map(v=>v.region)]);
    const townsFor=a=>unique([a.town,...venuesFor(a).map(v=>v.town)]);
    const daysFor=a=>activityDays(a);
    const categories=()=>unique(activities.map(a=>a.category));
    const regionMatch=a=>!choice.region||regionsFor(a).some(v=>v.toLowerCase()===choice.region.toLowerCase());
    const townMatch=a=>!choice.town||townsFor(a).some(v=>v.toLowerCase()===choice.town.toLowerCase());
    const dayMatch=a=>!choice.day||daysFor(a).some(v=>v.toLowerCase()===choice.day.toLowerCase());
    const categoryMatch=a=>!choice.category||String(a.category||"").toLowerCase()===choice.category.toLowerCase();
    const matching=()=>activities.filter(a=>regionMatch(a)&&townMatch(a)&&dayMatch(a)&&categoryMatch(a));
    const showStep=n=>steps.forEach(s=>{const active=Number(s.dataset.step)===n;s.hidden=!active;s.classList.toggle("is-active",active)});
    const renderChoices=(wrap,items,handler)=>{
      wrap.innerHTML=items.map(v=>"<button class='what-choice' type='button' data-choice='"+esc(v)+"'>"+esc(v)+"</button>").join("");
      wrap.querySelectorAll("[data-choice]").forEach(b=>b.onclick=()=>handler(b.dataset.choice));
    };
    const populateRegions=()=>renderChoices(regions,unique(activities.flatMap(regionsFor)),v=>{
      choice.region=v;choice.town="";populateTowns();showStep(2);
    });
    const populateTowns=()=>{
      const pool=activities.filter(regionMatch);
      renderChoices(towns,unique(pool.flatMap(townsFor)),v=>{
        choice.town=v;populateDays();showStep(3);
      });
    };
    const populateDays=()=>{
      const pool=activities.filter(a=>regionMatch(a)&&townMatch(a));
      renderChoices(days,unique(pool.flatMap(daysFor)),v=>{
        choice.day=v;populateCategories();showStep(4);
      });
    };
    const populateCategories=()=>{
      const pool=activities.filter(a=>regionMatch(a)&&townMatch(a)&&dayMatch(a));
      renderChoices(cats,categories().filter(v=>pool.some(a=>String(a.category||"").toLowerCase()===v.toLowerCase())),v=>{
        choice.category=v;showResults();
      });
    };
    const showResults=()=>{
      const list=matching().slice(0,6);
      const title=[choice.region,choice.town,choice.day].filter(Boolean).join(" · ");
      resultsTitle.textContent=list.length?(title||"Your suggestions"):"No exact matches";
      resultsGrid.innerHTML=list.length?list.map(a=>{
        const url=typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id);
        const where=a.town||((Array.isArray(a.venues)&&a.venues[0])?.town)||a.location||"";
        return "<a class='what-result' href='"+url+"'><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(where)+" · "+esc(choice.day)+"</small><b>View activity →</b></a>";
      }).join(""):"<div class='hub-empty'><strong>Nothing matched all four choices.</strong><p>Try another town, day or category and we’ll find more.</p></div>";
      results.hidden=false;steps.forEach(s=>s.hidden=true);
      results.scrollIntoView({behavior:"smooth",block:"nearest"});
    };
    const reset=()=>{
      choice={region:"",town:"",day:"",category:""};
      results.hidden=true;startScreen.hidden=false;steps.forEach(s=>s.hidden=true);populateRegions();
    };
    const beginSearch=()=>{startScreen.hidden=true;showStep(1)};
    open.onclick=()=>{panel.hidden=false;reset();document.body.classList.add("hub-modal-open")};
    close.onclick=()=>{panel.hidden=true;document.body.classList.remove("hub-modal-open")};
    panel.addEventListener("click",e=>{if(e.target===panel){panel.hidden=true;document.body.classList.remove("hub-modal-open")}});
    begin?.addEventListener("click",beginSearch);
    again.onclick=()=>{reset();beginSearch()};
    panel.querySelectorAll(".what-back").forEach(b=>b.onclick=()=>showStep(Number(b.dataset.back)));
    populateRegions();
  }

  function render(){
    const children=data.children||[],bumps=data.bumps||[],savedIds=new Set((data.saved||[]).map(String)),planned=data.planner||[],bookings=data.bookings||[],visitedIds=new Set((bhGet(BH_KEYS.visited)||[]).map(String)),recentIds=(bhGet(BH_KEYS.recent)||[]).map(String);
    const upcoming=bookings.filter(b=>b.status!=="cancelled").filter(b=>!b.starts_at||new Date(String(b.starts_at).replace(" ","T"))>=new Date()).slice(0,4);
    const plannedActivities=planned.map(x=>activities.find(a=>String(a.id)===String(x.id))).filter(Boolean).slice(0,5);
    const savedActivities=activities.filter(a=>savedIds.has(String(a.id))).slice(0,6);
    $("hubEmail").textContent=data.user?.email||"";
    const heroName=$("hubHeroName");
    if(heroName){
      const rawName=data.user?.name||data.user?.first_name||data.user?.display_name||"";
      const firstName=String(rawName).trim().split(/\\s+/)[0];
      heroName.textContent=firstName||"there";
    }
    $("hubStats").innerHTML=[["♡",savedIds.size,"Saved"],["✓",planned.length,"Planned"],["📅",upcoming.length,"Upcoming bookings"],["👶",children.length+bumps.length,"Family profiles"]].map(x=>"<article class='hub-stat'><span>"+x[0]+"</span><strong>"+x[1]+"</strong><small>"+x[2]+"</small></article>").join("");
    const schoolTracker=dob=>{if(!dob)return "";const d=new Date(dob+"T00:00:00"),now=new Date(),py=d.getFullYear()+5,sy=d.getFullYear()+11,fmt=x=>x.toLocaleDateString("en-GB",{day:"numeric",month:"long",year:"numeric"}),po=new Date(py-1,10,1),pc=new Date(py,0,15),so=new Date(sy-1,8,1),sc=new Date(sy-1,9,31),days=x=>Math.ceil((x-now)/86400000),status=(o,c)=>now>=o&&now<=c?(days(c)>0?"Open · "+days(c)+" days left":"Closes today"):(days(o)>0?"Opens "+fmt(o):"Closed");return "<div class='school-tracker'><div class='school-tracker-head'><span>🎓</span><strong>School application tracker</strong></div><div class='school-tracker-grid'><div><b>Primary · Sep "+py+"</b><small>"+esc(status(po,pc))+"</small><p>Opens "+esc(fmt(po))+" · closes "+esc(fmt(pc))+"</p></div><div><b>Secondary · Sep "+sy+"</b><small>"+esc(status(so,sc))+"</small><p>Opens "+esc(fmt(so))+" · closes "+esc(fmt(sc))+"</p></div></div><a class='button button-soft school-tracker-link' href='schools'>Find local schools →</a></div>"};
    $("hubFamily").innerHTML=(children.length||bumps.length)
      ? children.map(c=>"<article class='hub-family-card hub-family-expanded-card'>"+(c.photo_path?"<div class='family-avatar'><img src='"+esc(c.photo_path)+"' alt=''></div>":"<div class='family-avatar family-avatar-placeholder'>"+esc((c.name||"Child").charAt(0).toUpperCase())+"</div>")+"<div class='hub-family-main'><div class='hub-family-title'><div><span class='eyebrow'>Child</span><strong>"+esc(c.name)+"</strong><small>"+(c.date_of_birth?dateDisplay(c.date_of_birth)+" · "+ageFromDob(c.date_of_birth):"Date of birth not set")+"</small></div><button class='button button-soft hub-edit-child' data-id='"+c.id+"' type='button'>Edit</button></div>"+schoolTracker(c.date_of_birth)+"</div></article>").join("")
      +bumps.map(b=>"<article class='hub-family-card hub-family-expanded-card'> <div class='family-avatar family-avatar-placeholder'>🤰</div><div class='hub-family-main'><div class='hub-family-title'><div><span class='eyebrow'>Bump</span><strong>"+esc(b.nickname||"Baby")+"</strong><small>"+(b.due_date?"Due "+dateDisplay(b.due_date):"Due date not set")+"</small></div><button class='button button-soft hub-edit-bump' data-id='"+b.id+"' type='button'>Edit</button></div><a class='button button-soft school-tracker-link' href='family.html'>Pregnancy tracker →</a></div></article>").join("")
      : "<div class='hub-empty'><strong>Build your family profile</strong><p>Add your children or a bump profile so Bubba Hub can personalise activities and school planning.</p></div>";
    const visitedActivities=activities.filter(a=>visitedIds.has(String(a.id))).slice(0,6);
    const recentActivities=recentIds.map(id=>activities.find(a=>String(a.id)===id)).filter(Boolean).slice(0,6);
    const activityList=items=>items.length?items.map(a=>"<a class='hub-list-card' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'><div><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+" · "+esc(Array.isArray(a.age_range)?a.age_range.join(", "):a.age_range||"")+"</small></div><b>→</b></a>").join(""):"<div class='hub-empty'><strong>Nothing here yet</strong><p>Explore activities and they’ll appear here.</p><a class='button button-soft' href='directory.html'>Find activities</a></div>";
    $("hubVisited").innerHTML=activityList(visitedActivities);
    $("hubRecent").innerHTML=recentActivities.length?activityList(recentActivities):"<div class='hub-empty'><strong>Nothing viewed yet</strong><p>Open an activity to keep it here for later.</p><a class='button button-soft' href='directory.html'>Browse activities</a></div>";
    $("hubSaved").innerHTML=savedActivities.length?savedActivities.map(a=>"<a class='hub-list-card' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'><div><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(a.town||a.location||"")+" · "+esc(Array.isArray(a.age_range)?a.age_range.join(", "):a.age_range||"")+"</small></div><b>→</b></a>").join(""):"<div class='hub-empty'><strong>No saved activities yet</strong><p>Save activities you like while browsing the directory.</p><a class='button button-soft' href='directory.html'>Find activities</a></div>";

    $("hubBookings").innerHTML=upcoming.length?upcoming.map(b=>"<article class='hub-booking-card'><div><span class='status'>"+esc(b.status||"Reserved")+"</span><strong>"+esc(b.title||"Booking")+"</strong><small>"+esc(b.venue_name||"Venue")+" · "+esc(bookingDate(b.starts_at)||"Date to be confirmed")+"</small></div><span class='hub-booking-qty'>"+Number(b.quantity||1)+"×</span></article>").join(""):"<div class='hub-empty'><strong>No upcoming bookings</strong><p>Your confirmed or reserved bookings will appear here.</p></div>";
    void renderBrief();
    setupWhatShallI();
    renderSuggestionCarousel();
    renderUpcomingTicker();
    message.textContent=adminOnly?"Admin access — family data remains separate.":"Signed in as "+(data.user?.email||"your account");
    if(adminOnly){
      root.querySelectorAll(".hub-edit-child,.hub-edit-bump,#addChild,#addBump").forEach(btn=>btn.disabled=true);
      const family=root.querySelector("#hubFamily");
      if(family&&!children.length&&!bumps.length) family.innerHTML="<div class='hub-empty'><strong>Admin view</strong><p>Family profiles belong to a family account and are not changed by admin access.</p></div>";
    }
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