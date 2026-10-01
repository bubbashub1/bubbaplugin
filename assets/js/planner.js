document.addEventListener("DOMContentLoaded",async()=>{
  const $=id=>document.getElementById(id);
  const results=$("results"),count=$("count"),intro=$("plannerIntro"),controls=$("dayControls"),statusNote=$("plannerSyncNote"),exportBtn=$("exportCalendar"),printBtn=$("printCalendar");
  const days=[
    {name:"Monday",short:"Mon",num:1},{name:"Tuesday",short:"Tue",num:2},{name:"Wednesday",short:"Wed",num:3},
    {name:"Thursday",short:"Thu",num:4},{name:"Friday",short:"Fri",num:5},{name:"Saturday",short:"Sat",num:6},{name:"Sunday",short:"Sun",num:7}
  ];
  const HIDE_KEY="bhHiddenPlannerDays",VISITED_KEY="bhVisitedActivities";
  const getList=key=>{try{const v=JSON.parse(localStorage.getItem(key)||"[]");return Array.isArray(v)?v.map(String):[]}catch{return[]}};
  const setList=(key,v)=>localStorage.setItem(key,JSON.stringify(v.map(String)));
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  const formatTime=v=>window.bhFormatTime?window.bhFormatTime(v):String(v||"").slice(0,5);
  const today=new Date(),todayNum=((today.getDay()||7));
  let csrf="",signedIn=false,adminOnly=false,items=[],currentView="week";

  const auth=await fetch("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}}).then(r=>r.json()).catch(()=>({ok:false,authenticated:false}));
  signedIn=!!auth.authenticated;adminOnly=!!auth.is_admin&&!auth.user?.id;csrf=auth.csrf||"";

  async function plannerGet(){
    if(!signedIn||adminOnly)return null;
    try{const response=await fetch("api/planner.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});const payload=await response.json();if(!response.ok||!payload.ok)return null;csrf=payload.csrf||csrf;return payload}catch{return null}
  }
  async function plannerPost(body){
    if(!signedIn||adminOnly||!csrf)return false;
    try{const response=await fetch("api/planner.php",{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","Accept":"application/json"},body:JSON.stringify({...body,csrf})});const payload=await response.json();return !!(response.ok&&payload.ok)}catch{return false}
  }
  const localPlanned=()=>bhGet(BH_KEYS.planner),localVisited=()=>getList(VISITED_KEY),localHidden=()=>getList(HIDE_KEY);

  async function syncAccount(){
    if(!signedIn){statusNote.innerHTML='Your planner is saved on this device. <a href="account.html?next=planner.html">Sign in</a> to keep it across devices.';return}
    if(adminOnly){statusNote.textContent="Admin access — this planner is saved on this device.";return}
    const remote=await plannerGet();
    if(!remote){statusNote.textContent="Your account is signed in, but planner syncing is temporarily unavailable. Your local planner is still safe.";return}
    const localIds=localPlanned(),remoteIds=(remote.planned||[]).map(x=>String(x.id)),mergedIds=[...new Set([...remoteIds,...localIds])];
    const remoteVisited=new Map((remote.planned||[]).map(x=>[String(x.id),!!x.visited])),localVisitedIds=new Set(localVisited());
    const mergedPlanned=mergedIds.map(id=>({id,visited:!!remoteVisited.get(id)||localVisitedIds.has(id)}));
    const mergedHidden=[...new Set([...(remote.hidden_days||[]).map(String),...localHidden()])];
    const ok=await plannerPost({action:"sync",planned:mergedPlanned,hidden_days:mergedHidden});
    if(ok){setList(BH_KEYS.planner,mergedIds);setList(VISITED_KEY,mergedPlanned.filter(x=>x.visited).map(x=>x.id));setList(HIDE_KEY,mergedHidden);statusNote.innerHTML='✓ <strong>Planner synced</strong> to your Bubba Hub account.'}
    else statusNote.textContent="Your account is signed in, but the planner could not be saved right now. Your local planner is still safe.";
  }

  items=await bhActivities();const byId=new Map(items.map(item=>[String(item.id),item]));

  function mondayOf(d){const x=new Date(d.getFullYear(),d.getMonth(),d.getDate()),n=x.getDay();x.setDate(x.getDate()-(n===0?6:n-1));return x}
  function dateForDay(dayNum){const m=mondayOf(today),x=new Date(m);x.setDate(m.getDate()+dayNum-1);return x}
  const pad=n=>String(n).padStart(2,"0");
  const isoDate=d=>d.getFullYear()+"-"+pad(d.getMonth()+1)+"-"+pad(d.getDate());
  function timeMinutes(v){const m=String(v||"").match(/^(\d{1,2}):(\d{2})/);return m?Number(m[1])*60+Number(m[2]):null}
  function occurrenceAllowed(session,date){
    const iso=isoDate(date),from=String(session?.start_date||""),to=String(session?.end_date||"");
    return (!from||iso>=from)&&(!to||iso<=to);
  }
  function timeRange(session){
    const start=timeMinutes(session?.start_time),end=timeMinutes(session?.end_time);
    if(start===null)return null;
    return {start,end:end===null?start+60:end};
  }
  function getEntries(){
    const entries=[];
    localPlanned().forEach(id=>{
      const activity=byId.get(String(id));if(!activity)return;
      const sessions=typeof bhSessions==="function"?bhSessions(activity):[];
      if(sessions.length) sessions.forEach(session=>{
        const day=Number(session.day_of_week||0);
        if(day>=1&&day<=7&&occurrenceAllowed(session,dateForDay(day)))entries.push({activity,session,day,date:dateForDay(day)});
      });
      else entries.push({activity,session:null,day:0,date:null});
    });
    return entries;
  }
  function buildPrintCalendar(){
    const sheet=$("plannerPrintCalendar"),dateLabel=$("plannerPrintDate"),footer=$("plannerPrintFooter");
    if(!sheet)return;
    const now=new Date();
    dateLabel.textContent="Printed "+now.toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long",year:"numeric"});
    if(footer)footer.textContent="Printed "+now.toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long",year:"numeric"});
    const hidden=new Set(localHidden());
    const entries=getEntries().filter(e=>e.day>0&&!hidden.has(String(e.day))).sort((a,b)=>a.date-b.date||String(a.session?.start_time||"").localeCompare(String(b.session?.start_time||"")));
    const townOf=e=>e.session?.venue?.town||e.activity?.town||e.activity?.location||"";
    const entryHtml=e=>{
      const time=e.session?(formatTime(e.session.start_time)+(e.session.end_time?" – "+formatTime(e.session.end_time):"")):"Time TBC";
      return "<div class='planner-print-entry'><strong>"+esc(e.activity.title)+"</strong><span>"+esc(time)+(townOf(e)?" · "+esc(townOf(e)):"")+"</span></div>";
    };
    if(currentView==="list"){
      const grouped=[];
      entries.forEach(e=>{
        const key=isoDate(e.date);
        let group=grouped.find(g=>g.key===key);
        if(!group)group={key,date:e.date,entries:[]},grouped.push(group);
        group.entries.push(e);
      });
      sheet.innerHTML="<div class='planner-print-list'>"+(grouped.length?grouped.map(g=>"<section class='planner-print-list-day'><h2>"+esc(g.date.toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long"}))+"</h2>"+g.entries.map(entryHtml).join("")+"</section>").join(""):"<p class='planner-print-empty'>No scheduled activities for the selected days.</p>")+"</div>";
      return;
    }
    const monday=mondayOf(today),cells=[];
    for(let i=0;i<7;i++){
      const date=new Date(monday);date.setDate(monday.getDate()+i);
      const dayNum=date.getDay()||7;
      if(hidden.has(String(dayNum)))continue;
      const dayEntries=entries.filter(e=>isoDate(e.date)===isoDate(date));
      cells.push("<div class='planner-print-week-day'><div class='planner-print-week-heading'><strong>"+esc(date.toLocaleDateString("en-GB",{weekday:"short"}))+"</strong><span>"+esc(date.toLocaleDateString("en-GB",{day:"numeric",month:"short"}))+"</span></div>"+(dayEntries.length?dayEntries.map(entryHtml).join(""):"<div class='planner-print-empty-day'>Nothing planned</div>")+"</div>");
    }
    sheet.innerHTML="<div class='planner-print-week'>"+(cells.length?cells.join(""):"<p class='planner-print-empty'>No days selected.</p>")+"</div>";
  }

  function conflictsFor(entries){
    const conflicts=new Set();
    for(let i=0;i<entries.length;i++){
      const a=entries[i],ar=timeRange(a.session);if(!ar||!a.date)continue;
      for(let j=i+1;j<entries.length;j++){
        const b=entries[j],br=timeRange(b.session);if(!br||!b.date||isoDate(a.date)!==isoDate(b.date))continue;
        if(ar.start<br.end&&br.start<ar.end){conflicts.add(i);conflicts.add(j)}
      }
    }
    return conflicts;
  }

  const viewControls=$("plannerViewControls");
  if(viewControls)viewControls.innerHTML="<button type=\"button\" class=\"planner-view-button is-active\" data-view=\"week\">Weekly</button><button type=\"button\" class=\"planner-view-button\" data-view=\"list\">List</button>";

  controls.innerHTML=days.map(day=>"<label class='planner-day-toggle'><input type='checkbox' class='day-toggle' value='"+day.num+"'><span><b>"+day.short+"</b><small>"+day.name+"</small></span></label>").join("");

  function render(){
    const hidden=localHidden();
    controls.querySelectorAll(".day-toggle").forEach(input=>input.checked=!hidden.includes(input.value));
    const entries=getEntries(),shownEntries=entries.filter(entry=>entry.day===0||!hidden.includes(String(entry.day))),uniqueActivities=new Set(shownEntries.map(x=>String(x.activity.id)));
    const shownSessions=shownEntries.filter(x=>x.day>0).length,conflicts=conflictsFor(shownEntries);
    count.textContent=uniqueActivities.size+" activit"+(uniqueActivities.size===1?"y":"ies")+" · "+shownSessions+" session"+(shownSessions===1?"":"s")+" shown";
    intro.textContent=entries.length?"Your planned activities are grouped by their actual dates this week.":"Add activities from the directory and they will appear here as soon as they are planned.";
    if(!entries.length){results.innerHTML="<div class='planner-empty'><div class='planner-empty-icon'>＋</div><h3>Your planner is ready for its first activity</h3><p>Choose <strong>My Planner</strong> on any activity you like and it will appear here automatically.</p><a class='button button-primary' href='directory.html'>Find activities</a></div>";return}

    if(currentView==="list"){
      const listEntries=shownEntries.filter(e=>e.day>0).sort((a,b)=>a.date-b.date||String(a.session?.start_time||"").localeCompare(String(b.session?.start_time||"")));
      results.innerHTML="<div class='planner-list-view'>"+(listEntries.length?listEntries.map(entry=>{
        const a=entry.activity,s=entry.session,venue=s?.venue,town=venue?.town||a.town||a.location||"",time=s?(formatTime(s.start_time)+(s.end_time?" – "+formatTime(s.end_time):"")):"Time TBC",visit=localVisited().includes(String(a.id));
        return "<article class='planner-list-item'><div class='planner-list-date'><b>"+esc(entry.date.toLocaleDateString("en-GB",{weekday:"short"}))+"</b><span>"+esc(entry.date.toLocaleDateString("en-GB",{day:"numeric",month:"short"}))+"</span></div><div class='planner-list-main'><span class='planner-time'>"+esc(time)+"</span><h4>"+esc(a.title)+"</h4><p>"+esc(town)+"</p><div class='planner-card-links'><a href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View activity</a><button class='planner-visit planner-link-button' type='button' data-id='"+esc(a.id)+"' aria-pressed='"+visit+"'>✓ "+(visit?"Visited":"Mark visited")+"</button><button class='planner-remove planner-link-button' type='button' data-id='"+esc(a.id)+"'>Remove</button></div></div></article>";
      }).join(""):"<div class='planner-empty'><div class='planner-empty-icon'>＋</div><h3>Nothing planned on the selected days</h3><p>Choose an activity from the directory to add it to your planner.</p><a class='button button-primary' href='directory.html'>Find activities</a></div>")+"</div>";
      bindPlannerActions();
      return;
    }

    const sections=days.filter(day=>!hidden.includes(String(day.num))).map(day=>{
      const dayEntries=shownEntries.map((entry,index)=>({...entry,_index:index})).filter(entry=>entry.day===day.num).sort((a,b)=>String(a.session?.start_time||"").localeCompare(String(b.session?.start_time||""))||String(a.activity.title).localeCompare(String(b.activity.title)));
      const isToday=day.num===todayNum,date=dateForDay(day.num);
      return "<section class='planner-day-column"+(isToday?" is-today":"")+"' data-day='"+day.num+"'>"+
        "<div class='planner-day-heading'><div><span>"+esc(day.short)+"</span><h3>"+esc(day.name)+"</h3><small class='planner-date'>"+esc(date.toLocaleDateString("en-GB",{day:"numeric",month:"short"}))+"</small></div>"+(isToday?"<small>Today</small>":"")+"</div>"+
        (dayEntries.length?dayEntries.map(entry=>{
          const a=entry.activity,s=entry.session,venue=s?.venue,range=timeRange(s),clash=conflicts.has(entry._index);
          const time=s?(formatTime(s.start_time)+(s.end_time?" – "+formatTime(s.end_time):"")):"Time TBC",location=venue?.town||venue?.name||a.location||a.town||"",age=Array.isArray(a.age_range)?a.age_range.join(", "):String(a.age_range||""),visit=localVisited().includes(String(a.id));
          return "<article class='planner-card"+(clash?" is-conflict":"")+"'><div class='planner-card-top'><span class='planner-time'>"+esc(time)+"</span>"+(clash?"<span class='planner-conflict'>⚠ Clash</span>":(s?.term_time_only?"<span class='planner-mini-badge'>Term time</span>":""))+"</div>"+
            "<h4>"+esc(a.title)+"</h4><p class='planner-card-location'>"+esc(location)+(age?" · "+esc(age):"")+"</p>"+
            "<div class='planner-card-links'><a href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View activity</a>"+
            "<button class='planner-visit planner-link-button' type='button' data-id='"+esc(a.id)+"' aria-pressed='"+visit+"'>✓ "+(visit?"Visited":"Mark visited")+"</button>"+
            "<button class='planner-remove planner-link-button' type='button' data-id='"+esc(a.id)+"'>Remove</button></div></article>";
        }).join(""):"<div class='planner-day-empty'>Nothing planned</div>")+"</section>";
    }).join("");

    const unscheduled=shownEntries.filter(entry=>entry.day===0);
    results.innerHTML=sections+(unscheduled.length?"<section class='planner-unscheduled'><div class='planner-day-heading'><div><span>Other</span><h3>Schedule to confirm</h3></div></div>"+unscheduled.map(entry=>{
      const a=entry.activity;return "<article class='planner-card'><div class='planner-card-top'><span class='planner-time'>Time TBC</span></div><h4>"+esc(a.title)+"</h4><p class='planner-card-location'>"+esc(a.location||a.town||"")+"</p><div class='planner-card-actions'><a class='button button-soft' href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View</a><button class='planner-remove' type='button' data-id='"+esc(a.id)+"'>Remove</button></div></article>";
    }).join("")+"</section>":"");

    bindPlannerActions();
  }

  function bindPlannerActions(){
    results.querySelectorAll(".planner-remove").forEach(button=>button.addEventListener("click",async()=>{
      const id=button.dataset.id;bhTogglePlanned(id);render();if(signedIn)await plannerPost({action:"set_activity",activity_id:Number(id),planned:false,visited:false});
    }));
    results.querySelectorAll(".planner-visit").forEach(button=>button.addEventListener("click",async()=>{
      const id=button.dataset.id,visitedNow=!localVisited().includes(String(id)),list=localVisited(),index=list.indexOf(String(id));
      index>=0?list.splice(index,1):list.push(String(id));setList(VISITED_KEY,list);render();if(signedIn)await plannerPost({action:"set_visited",activity_id:Number(id),visited:visitedNow});
    }));
  }

  function icsEscape(v){return String(v||"").replace(/\\/g,"\\\\").replace(/;/g,"\\;").replace(/,/g,"\\,").replace(/\n/g,"\\n").replace(/\r/g,"");}
  function utcStamp(d){return d.toISOString().replace(/[-:]/g,"").replace(/\.\d{3}Z$/,"Z")}
  function makeICS(){
    const entries=getEntries().filter(x=>x.day>0&&x.session?.start_time),lines=[
      "BEGIN:VCALENDAR","VERSION:2.0","PRODID:-//Bubba Hub//Family Planner//EN","CALSCALE:GREGORIAN","METHOD:PUBLISH"
    ];
    entries.forEach((e,i)=>{
      const start=new Date(e.date.getFullYear(),e.date.getMonth(),e.date.getDate(),Number(String(e.session.start_time).slice(0,2)),Number(String(e.session.start_time).slice(3,5)),0);
      const endRange=timeRange(e.session),end=new Date(start.getTime()+(endRange?Math.max(30,endRange.end-endRange.start):60)*60000);
      const venue=e.session.venue?.name||e.session.venue?.town||e.activity.town||"";
      lines.push("BEGIN:VEVENT","UID:bubbahub-"+e.activity.id+"-"+e.day+"-"+i+"@bubbahub.co.uk","DTSTAMP:"+utcStamp(new Date()),"DTSTART:"+utcStamp(start),"DTEND:"+utcStamp(end),"SUMMARY:"+icsEscape(e.activity.title),"LOCATION:"+icsEscape(venue),"DESCRIPTION:"+icsEscape("Bubba Hub family planner · "+(e.activity.category||"Activity")),"URL:"+icsEscape(location.origin+"/beta/"+(typeof bhActivityUrl==="function"?bhActivityUrl(e.activity):"activity.html?id="+encodeURIComponent(e.activity.id))),"END:VEVENT");
    });
    lines.push("END:VCALENDAR");
    return lines.join("\r\n");
  }
  printBtn?.addEventListener("click",()=>{buildPrintCalendar();window.print()});
  exportBtn?.addEventListener("click",()=>{
    const entries=getEntries().filter(x=>x.day>0);
    if(!entries.length){statusNote.textContent="There are no scheduled sessions in your planner this week to add to a calendar.";return}
    const blob=new Blob([makeICS()],{type:"text/calendar;charset=utf-8"}),url=URL.createObjectURL(blob),a=document.createElement("a");
    a.href=url;a.download="bubba-hub-planner.ics";document.body.appendChild(a);a.click();a.remove();URL.revokeObjectURL(url);
    statusNote.innerHTML="✓ <strong>Calendar file created</strong> for this week's scheduled sessions.";
  });

  controls.querySelectorAll(".day-toggle").forEach(input=>input.addEventListener("change",async()=>{
    const hidden=localHidden(),value=input.value,index=hidden.indexOf(value);
    if(input.checked){if(index>=0)hidden.splice(index,1)}else if(index<0)hidden.push(value);
    setList(HIDE_KEY,hidden);render();buildPrintCalendar();if(signedIn)await plannerPost({action:"set_day",day:Number(value),visible:input.checked});
  }));
  $("showAll").addEventListener("click",async()=>{setList(HIDE_KEY,[]);render();buildPrintCalendar();if(signedIn)await plannerPost({action:"show_all_days"})});
  viewControls?.querySelectorAll(".planner-view-button").forEach(button=>button.addEventListener("click",()=>{
    currentView=button.dataset.view==="list"?"list":"week";
    viewControls.querySelectorAll(".planner-view-button").forEach(b=>b.classList.toggle("is-active",b===button));
    render();buildPrintCalendar();
  }));
  await syncAccount();render();buildPrintCalendar();
}).catch(error=>{
  const target=document.getElementById("results");
  target.innerHTML="<div class='admin-panel'><h3>Planner unavailable</h3><p>"+(window.bhEscape?bhEscape(error.message||"Unable to load planner."):String(error.message||"Unable to load planner."))+"</p></div>";
});