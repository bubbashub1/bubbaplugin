document.addEventListener("DOMContentLoaded",async()=>{
  const $=id=>document.getElementById(id);
  const results=$("results"),count=$("count"),intro=$("plannerIntro"),controls=$("dayControls"),statusNote=$("plannerSyncNote");
  const days=[
    {name:"Monday",short:"Mon",num:1},{name:"Tuesday",short:"Tue",num:2},{name:"Wednesday",short:"Wed",num:3},
    {name:"Thursday",short:"Thu",num:4},{name:"Friday",short:"Fri",num:5},{name:"Saturday",short:"Sat",num:6},{name:"Sunday",short:"Sun",num:7}
  ];
  const HIDE_KEY="bhHiddenPlannerDays",VISITED_KEY="bhVisitedActivities";
  const getList=key=>{try{const v=JSON.parse(localStorage.getItem(key)||"[]");return Array.isArray(v)?v.map(String):[]}catch{return[]}};
  const setList=(key,v)=>localStorage.setItem(key,JSON.stringify(v.map(String)));
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  const formatTime=v=>window.bhFormatTime?window.bhFormatTime(v):String(v||"").slice(0,5);
  const todayNum=((new Date()).getDay()||7);
  let csrf="";
  let signedIn=false;
  let items=[];

  const auth=await fetch("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}}).then(r=>r.json()).catch(()=>({ok:false,authenticated:false}));
  signedIn=!!auth.authenticated;
  csrf=auth.csrf||"";

  async function plannerGet(){
    if(!signedIn)return null;
    try{
      const response=await fetch("api/planner.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const payload=await response.json();
      if(!response.ok||!payload.ok)return null;
      csrf=payload.csrf||csrf;
      return payload;
    }catch{return null}
  }

  async function plannerPost(body){
    if(!signedIn||!csrf)return false;
    try{
      const response=await fetch("api/planner.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({...body,csrf})});
      const payload=await response.json();
      if(!response.ok||!payload.ok)return false;
      return true;
    }catch{return false}
  }

  const localPlanned=()=>bhGet(BH_KEYS.planner);
  const localVisited=()=>getList(VISITED_KEY);
  const localHidden=()=>getList(HIDE_KEY);

  async function syncAccount(){
    if(!signedIn){
      statusNote.innerHTML='Your planner is saved on this device. <a href="account.html?next=planner.html">Sign in</a> to keep it across devices.';
      return;
    }

    const remote=await plannerGet();
    if(!remote){
      statusNote.textContent="Your account is signed in, but planner syncing is temporarily unavailable. Your local planner is still safe.";
      return;
    }

    const localIds=localPlanned();
    const remoteIds=(remote.planned||[]).map(x=>String(x.id));
    const mergedIds=[...new Set([...remoteIds,...localIds])];
    const remoteVisited=new Map((remote.planned||[]).map(x=>[String(x.id),!!x.visited]));
    const localVisitedIds=new Set(localVisited());
    const mergedPlanned=mergedIds.map(id=>({id,visited:!!remoteVisited.get(id)||localVisitedIds.has(id)}));
    const mergedHidden=[...new Set([...(remote.hidden_days||[]).map(String),...localHidden()])];

    const ok=await plannerPost({action:"sync",planned:mergedPlanned,hidden_days:mergedHidden});
    if(ok){
      setList(BH_KEYS.planner,mergedIds);
      setList(VISITED_KEY,mergedPlanned.filter(x=>x.visited).map(x=>x.id));
      setList(HIDE_KEY,mergedHidden);
      statusNote.innerHTML='✓ <strong>Planner synced</strong> to your Bubba Hub account.';
    }else{
      statusNote.textContent="Your account is signed in, but the planner could not be saved right now. Your local planner is still safe.";
    }
  }

  items=await bhActivities();
  const byId=new Map(items.map(item=>[String(item.id),item]));

  function getEntries(){
    const entries=[];
    localPlanned().forEach(id=>{
      const activity=byId.get(String(id));
      if(!activity)return;
      const sessions=typeof bhSessions==="function"?bhSessions(activity):[];
      if(sessions.length){
        sessions.forEach(session=>{
          const day=Number(session.day_of_week||0);
          if(day>=1&&day<=7)entries.push({activity,session,day});
        });
      }else{
        entries.push({activity,session:null,day:0});
      }
    });
    return entries;
  }

  controls.innerHTML=days.map(day=>"<label class='planner-day-toggle'><input type='checkbox' class='day-toggle' value='"+day.num+"'><span><b>"+day.short+"</b><small>"+day.name+"</small></span></label>").join("");

  const render=()=>{
    const hidden=localHidden();
    controls.querySelectorAll(".day-toggle").forEach(input=>input.checked=!hidden.includes(input.value));
    const entries=getEntries();
    const shownEntries=entries.filter(entry=>entry.day===0||!hidden.includes(String(entry.day)));
    const uniqueActivities=new Set(shownEntries.map(x=>String(x.activity.id)));
    const shownSessions=shownEntries.filter(x=>x.day>0).length;
    count.textContent=uniqueActivities.size+" activit"+(uniqueActivities.size===1?"y":"ies")+" · "+shownSessions+" session"+(shownSessions===1?"":"s")+" shown";
    intro.textContent=entries.length?"Your planned activities are grouped by the day they run.":"Add activities from the directory and they will appear here as soon as they are planned.";

    if(!entries.length){
      results.innerHTML="<div class='planner-empty'><div class='planner-empty-icon'>＋</div><h3>Your planner is ready for its first activity</h3><p>Choose <strong>My Planner</strong> on any activity you like and it will appear here automatically.</p><a class='button button-primary' href='directory.html'>Find activities</a></div>";
      return;
    }

    const sections=days.filter(day=>!hidden.includes(String(day.num))).map(day=>{
      const dayEntries=shownEntries.filter(entry=>entry.day===day.num).sort((a,b)=>{
        return String(a.session?.start_time||"").localeCompare(String(b.session?.start_time||""))||String(a.activity.title).localeCompare(String(b.activity.title));
      });
      const isToday=day.num===todayNum;
      return "<section class='planner-day-column"+(isToday?" is-today":"")+"' data-day='"+day.num+"'>"+
        "<div class='planner-day-heading'><div><span>"+esc(day.short)+"</span><h3>"+esc(day.name)+"</h3></div>"+(isToday?"<small>Today</small>":"")+"</div>"+
        (dayEntries.length?dayEntries.map(entry=>{
          const a=entry.activity,s=entry.session,venue=s?.venue;
          const time=s?(formatTime(s.start_time)+(s.end_time?" – "+formatTime(s.end_time):"")):"Time TBC";
          const location=venue?.town||venue?.name||a.location||a.town||"";
          const age=Array.isArray(a.age_range)?a.age_range.join(", "):String(a.age_range||"");
          const visit=localVisited().includes(String(a.id));
          return "<article class='planner-card'><div class='planner-card-top'><span class='planner-time'>"+esc(time)+"</span>"+(s?.term_time_only?"<span class='planner-mini-badge'>Term time</span>":"")+"</div>"+
            "<h4>"+esc(a.title)+"</h4><p class='planner-card-location'>"+esc(location)+(age?" · "+esc(age):"")+"</p>"+
            "<div class='planner-card-actions'><a class='button button-soft' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View</a>"+
            "<button class='button button-soft planner-visit' type='button' data-id='"+esc(a.id)+"' aria-pressed='"+visit+"'>✓ "+(visit?"Visited":"Mark visited")+"</button>"+
            "<button class='planner-remove' type='button' data-id='"+esc(a.id)+"' aria-label='Remove "+esc(a.title)+" from planner'>Remove</button></div></article>";
        }).join(""):"<div class='planner-day-empty'>Nothing planned</div>")+"</section>";
    }).join("");

    const unscheduled=shownEntries.filter(entry=>entry.day===0);
    results.innerHTML=sections+(unscheduled.length?"<section class='planner-unscheduled'><div class='planner-day-heading'><div><span>Other</span><h3>Schedule to confirm</h3></div></div>"+unscheduled.map(entry=>{
      const a=entry.activity;
      return "<article class='planner-card'><div class='planner-card-top'><span class='planner-time'>Time TBC</span></div><h4>"+esc(a.title)+"</h4><p class='planner-card-location'>"+esc(a.location||a.town||"")+"</p><div class='planner-card-actions'><a class='button button-soft' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View</a><button class='planner-remove' type='button' data-id='"+esc(a.id)+"'>Remove</button></div></article>";
    }).join("")+"</section>":"");

    results.querySelectorAll(".planner-remove").forEach(button=>button.addEventListener("click",async()=>{
      const id=button.dataset.id;
      bhTogglePlanned(id);
      render();
      if(signedIn)await plannerPost({action:"set_activity",activity_id:Number(id),planned:false,visited:false});
    }));
    results.querySelectorAll(".planner-visit").forEach(button=>button.addEventListener("click",async()=>{
      const id=button.dataset.id,visitedNow=!localVisited().includes(String(id));
      const list=localVisited(),index=list.indexOf(String(id));
      index>=0?list.splice(index,1):list.push(String(id));
      setList(VISITED_KEY,list);
      render();
      if(signedIn)await plannerPost({action:"set_visited",activity_id:Number(id),visited:visitedNow});
    }));
  };

  controls.querySelectorAll(".day-toggle").forEach(input=>input.addEventListener("change",async()=>{
    const hidden=localHidden(),value=input.value,index=hidden.indexOf(value);
    if(input.checked){if(index>=0)hidden.splice(index,1)}else if(index<0)hidden.push(value);
    setList(HIDE_KEY,hidden);
    render();
    if(signedIn)await plannerPost({action:"set_day",day:Number(value),visible:input.checked});
  }));

  $("showAll").addEventListener("click",async()=>{
    setList(HIDE_KEY,[]);
    render();
    if(signedIn)await plannerPost({action:"show_all_days"});
  });

  await syncAccount();
  render();
}).catch(error=>{
  const target=document.getElementById("results");
  target.innerHTML="<div class='admin-panel'><h3>Planner unavailable</h3><p>"+(window.bhEscape?bhEscape(error.message||"Unable to load planner."):String(error.message||"Unable to load planner."))+"</p></div>";
});