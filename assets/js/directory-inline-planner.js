/* Native Directory Planner — no iframe or duplicate navigation. */
(()=>{
  const week=document.getElementById("bhInlinePlannerWeek");
  const count=document.getElementById("bhInlinePlannerCount");
  const refresh=document.getElementById("bhInlinePlannerRefresh");
  if(!week)return;
  const days=["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
  const escapeHtml=value=>String(value??"").replace(/[&<>"']/g,char=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[char]));
  const plannedIds=()=>{
    try{
      if(typeof bhGet==="function"&&typeof BH_KEYS!=="undefined")return bhGet(BH_KEYS.planner).map(String);
      const data=JSON.parse(localStorage.getItem("bhPlanner")||"[]");
      return Array.isArray(data)?data.map(String):[];
    }catch{return [];}
  };
  let itemsCache=null,loading=false;
  async function render(){
    if(loading)return;
    loading=true;
    try{
      const ids=plannedIds();
      if(count)count.textContent=ids.length+" planned";
      if(!ids.length){
        week.innerHTML='<div class="bh-inline-planner-empty"><h4>Your planner is ready!</h4><p>Explore Activities and select “Add to planner” to build your family week.</p><a href="/directory.html" data-bh-planner-browse>Browse activities →</a></div>';
        return;
      }
      if(!itemsCache)itemsCache=await bhActivities();
      const selected=itemsCache.filter(item=>ids.includes(String(item.id)));
      const byDay=days.map(()=>[]);
      for(const activity of selected){
        const sessions=typeof bhSessions==="function"?bhSessions(activity):[];
        for(const session of sessions){
          const number=Number(session.day_of_week);
          if(number>=1&&number<=7){
            const time=session.start_time||session.time||"";
            byDay[number-1].push({activity,time});
          }
        }
      }
      week.innerHTML=days.map((day,index)=>{
        const events=byDay[index];
        return '<section class="bh-inline-planner-day"><h4>'+day+'</h4><div class="bh-inline-planner-events">'+
          (events.length?events.map(({activity,time})=>'<a class="bh-inline-planner-event" href="/activity/'+encodeURIComponent(activity.slug||activity.id)+'"><strong>'+escapeHtml(activity.title)+'</strong>'+(time?'<small>'+escapeHtml(String(time).slice(0,5))+'</small>':'')+'</a>').join(""):'<span class="bh-inline-planner-empty-day">No sessions planned</span>')+'</div></section>';
      }).join("");
      const missing=ids.length-selected.length;
      if(missing)week.insertAdjacentHTML("beforeend",'<p class="bh-inline-planner-note">'+missing+' saved activities could not be loaded. They remain in your device storage.</p>');
      if(selected.length&&!byDay.some(events=>events.length))week.insertAdjacentHTML("beforeend",'<p class="bh-inline-planner-note">Your saved activities have no weekly sessions available yet.</p>');
    }catch(error){
      console.error("Directory Planner failed",error);
      week.innerHTML='<p class="bh-inline-planner-note">Your plans could not be loaded. Please try Refresh plans.</p>';
    }finally{loading=false;}
  }
  document.addEventListener("bh:directory-planner-open",render);
  refresh?.addEventListener("click",()=>{itemsCache=null;void render();});
  week.addEventListener("click",event=>{
    const browse=event.target.closest("[data-bh-planner-browse]");
    if(browse){event.preventDefault();document.getElementById("directoryShowList")?.click();}
  });
  if(!document.getElementById("directoryPlannerView")?.hidden)void render();
})();