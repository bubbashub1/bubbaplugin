document.addEventListener("DOMContentLoaded",async()=>{
  const $=id=>document.getElementById(id);
  const keys={planners:"bhProPlanners",family:"bhProFamily",shares:"bhProShares",notes:"bhProNotes",dayPlan:"bhProDayPlan"};
  const read=(key,fallback=[])=>{try{const v=JSON.parse(localStorage.getItem(key)||"null");return Array.isArray(v)?v:fallback}catch{return fallback}};
  const write=(key,value)=>localStorage.setItem(key,JSON.stringify(value));
  const uid=prefix=>prefix+"-"+Date.now().toString(36)+"-"+Math.random().toString(36).slice(2,7);
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  const colours=["#416651","#3c8e96","#8068cf","#55a9c2","#ee6684","#9cb8a4"];
  let planners=read(keys.planners,[{id:"family",name:"Family",description:"Everyday family activities and plans.",colour:"#416651"}]);
  let family=read(keys.family,[{id:"everyone",name:"Everyone",role:"Family",colour:"#416651"}]);
  let shares=read(keys.shares,[]);
  let notes=read(keys.notes,[]);
  let dayPlan=read(keys.dayPlan,[{id:uid("day"),time:"09:30",title:"Morning activity",detail:"Add an activity from your planner."},{id:uid("day"),time:"12:30",title:"Lunch / travel",detail:"Leave space between plans."}]);
  let monthDate=new Date();

  function modal(title,intro,fields,onSave){
    const wrap=document.createElement("div");wrap.className="pro-modal-backdrop";
    wrap.innerHTML="<div class='pro-modal' role='dialog' aria-modal='true'><h2>"+esc(title)+"</h2><p>"+esc(intro)+"</p><form class='pro-form'>"+fields.map(f=>"<label>"+esc(f.label)+(f.type==="textarea"?"<textarea name='"+f.name+"' "+(f.required?"required":"")+"></textarea>":f.type==="select"?"<select name='"+f.name+"'>"+f.options.map(o=>"<option value='"+esc(o.value)+"'>"+esc(o.label)+"</option>").join("")+"</select>":"<input name='"+f.name+"' type='"+(f.type||"text")+"' "+(f.required?"required":"")+"></label>").join("")+"<div class='pro-form-actions'><button type='button' class='button button-soft' data-cancel>Cancel</button><button class='button button-primary' type='submit'>Save</button></div></form></div>";
    document.body.appendChild(wrap);const form=wrap.querySelector("form");form.querySelector("[data-cancel]").onclick=()=>wrap.remove();form.onsubmit=e=>{e.preventDefault();const data=Object.fromEntries(new FormData(form));onSave(data);wrap.remove()};
  }

  function renderPlanners(){
    const el=$("proPlannerList");if(!planners.length){el.innerHTML="<div class='pro-empty'><strong>No planners yet</strong>Create your first custom planner.</div>";return}
    el.innerHTML=planners.map((p,i)=>"<article class='pro-planner-card "+(i===0?"is-active":"")+"'><span class='pro-planner-swatch' style='background:"+esc(p.colour||colours[i%colours.length])+"'></span><div class='pro-planner-card-main'><h3>"+esc(p.name)+"</h3><p>"+esc(p.description||"Custom family planner")+"</p></div><div class='pro-card-actions'><button type='button' data-planner-edit='"+esc(p.id)+"'>Edit</button><button type='button' data-planner-delete='"+esc(p.id)+"'>Delete</button></div></article>").join("");
    el.querySelectorAll("[data-planner-edit]").forEach(b=>b.onclick=()=>editPlanner(b.dataset.plannerEdit));
    el.querySelectorAll("[data-planner-delete]").forEach(b=>b.onclick=()=>{if(planners.length===1)return;planners=planners.filter(p=>p.id!==b.dataset.plannerDelete);write(keys.planners,planners);renderPlanners();});
  }
  /* New Planner modal component */
  function openNewPlannerModal(){
    const wrap=document.createElement("div");
    wrap.className="pro-modal-backdrop pro-planner-modal";
    wrap.innerHTML=`
      <div class="pro-modal pro-planner-modal-card" role="dialog" aria-modal="true" aria-labelledby="newPlannerTitle">
        <button class="pro-modal-close" type="button" aria-label="Close">×</button>
        <div class="pro-planner-modal-icon">▦</div>
        <span class="eyebrow">Planner Pro</span>
        <h2 id="newPlannerTitle">Create a new planner</h2>
        <p>Set up a separate planning space for school, holidays, a child or anything else your family needs.</p>
        <form class="pro-form">
          <label>Planner name
            <input name="name" type="text" placeholder="e.g. School & clubs" required maxlength="60" autocomplete="off">
          </label>
          <label>What is it for?
            <textarea name="description" placeholder="e.g. School events, clubs and term dates." required maxlength="180"></textarea>
          </label>
          <div class="pro-form-actions">
            <button type="button" class="button button-soft" data-cancel>Cancel</button>
            <button type="submit" class="button button-primary">Create planner</button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(wrap);
    const form=wrap.querySelector("form");
    const close=()=>wrap.remove();
    wrap.querySelector(".pro-modal-close").onclick=close;
    wrap.querySelector("[data-cancel]").onclick=close;
    wrap.addEventListener("click",e=>{if(e.target===wrap)close()});
    document.addEventListener("keydown",function onKey(e){if(e.key==="Escape"){close();document.removeEventListener("keydown",onKey)}});
    form.onsubmit=e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(form));
      const name=String(data.name||"").trim();
      const description=String(data.description||"").trim();
      if(!name||!description)return;
      planners.push({id:uid("planner"),name,description,colour:colours[planners.length%colours.length]});
      write(keys.planners,planners);
      renderPlanners();
      close();
    };
    requestAnimationFrame(()=>form.querySelector("input")?.focus());
  }

  function addPlanner(){openNewPlannerModal();}
  function editPlanner(id){
    const p=planners.find(x=>x.id===id);if(!p)return;
    modal("Edit planner","Keep the purpose clear so you can recognise it quickly.",[
      {name:"name",label:"Planner name",required:true},{name:"description",label:"Description",required:true}
    ],d=>{p.name=d.name;p.description=d.description;write(keys.planners,planners);renderPlanners()});
  }

  function renderFamily(){
    const el=$("familyGrid");el.innerHTML=family.map(m=>"<article class='pro-family-card'><span class='pro-family-swatch' style='background:"+esc(m.colour)+"'></span><div class='pro-family-main'><h3>"+esc(m.name)+"</h3><p>"+esc(m.role||"Family member")+"</p></div><div class='pro-card-actions'><button type='button' data-family-delete='"+esc(m.id)+"'>Remove</button></div></article>").join("");
    el.querySelectorAll("[data-family-delete]").forEach(b=>b.onclick=()=>{if(family.length===1)return;family=family.filter(m=>m.id!==b.dataset.familyDelete);write(keys.family,family);renderFamily()});
  }
  function addFamily(){
    modal("Add family member","Use a name or nickname and choose how they fit into your family plan.",[
      {name:"name",label:"Name",required:true},{name:"role",label:"Role / relationship",required:true}
    ],d=>{family.push({id:uid("member"),name:d.name,role:d.role,colour:colours[family.length%colours.length]});write(keys.family,family);renderFamily()});
  }

  function renderShares(){
    const el=$("shareList");if(!shares.length){el.innerHTML="<div class='pro-empty'><strong>No shared planners</strong>Create a private view-only link when you are ready to share.</div>";return}
    el.innerHTML=shares.map(s=>{const p=planners.find(x=>x.id===s.plannerId);return "<article class='pro-share-card'><span class='pro-icon'>↗</span><div class='pro-share-main'><h3>"+esc(p?.name||"Planner")+"</h3><p>View-only link · "+esc(s.token)+"</p></div><div class='pro-card-actions'><button type='button' data-copy='"+esc(s.token)+"'>Copy</button><button type='button' data-share-delete='"+esc(s.id)+"'>Revoke</button></div></article>"}).join("");
    el.querySelectorAll("[data-copy]").forEach(b=>b.onclick=async()=>{try{await navigator.clipboard.writeText(location.origin+location.pathname+"?share="+b.dataset.copy);b.textContent="Copied"}catch{b.textContent="Copy failed"}});
    el.querySelectorAll("[data-share-delete]").forEach(b=>b.onclick=()=>{shares=shares.filter(s=>s.id!==b.dataset.shareDelete);write(keys.shares,shares);renderShares()});
  }
  function createShare(){
    modal("Share a planner","Create a private view-only link. This is a local prototype until shared-planner accounts are connected.",[
      {name:"plannerId",label:"Planner",type:"select",options:planners.map(p=>({value:p.id,label:p.name}))}
    ],d=>{shares.push({id:uid("share"),plannerId:d.plannerId,token:uid("share")});write(keys.shares,shares);renderShares()});
  }

  function monthEvents(){
    const planned=bhGet(BH_KEYS.planner);
    const items=window.__bhActivities||[];
    const events=[];
    planned.forEach(id=>{const a=items.find(x=>String(x.id)===String(id));if(!a)return;(typeof bhSessions==="function"?bhSessions(a):[]).forEach(s=>{const day=Number(s.day_of_week||0);if(day<1||day>7)return;const d=new Date(monthDate.getFullYear(),monthDate.getMonth(),1);const first=(d.getDay()||7);d.setDate(1+((day-first+7)%7));while(d.getMonth()===monthDate.getMonth()){if(s.start_date&&d.toISOString().slice(0,10)<s.start_date){d.setDate(d.getDate()+7);continue}if(s.end_date&&d.toISOString().slice(0,10)>s.end_date)break;events.push({date:d.getDate(),title:a.title});d.setDate(d.getDate()+7)}})});return events;
  }
  function renderMonth(){
    const grid=$("monthGrid"),year=monthDate.getFullYear(),month=monthDate.getMonth(),first=new Date(year,month,1),last=new Date(year,month+1,0),start=(first.getDay()||7)-1,events=monthEvents(),cells=[];
    $("monthLabel").textContent=first.toLocaleDateString("en-GB",{month:"long",year:"numeric"});
    ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"].forEach(d=>cells.push("<div class='pro-month-cell' style='min-height:auto;background:#edf5ef;font-weight:800;color:#416651'>"+d+"</div>"));
    for(let i=0;i<start;i++)cells.push("<div class='pro-month-cell is-outside'></div>");
    for(let d=1;d<=last.getDate();d++){const date=new Date(year,month,d),today=new Date();const ev=events.filter(e=>e.date===d);cells.push("<div class='pro-month-cell "+(date.toDateString()===today.toDateString()?"is-today":"")+"'><div class='pro-month-date'>"+d+"</div>"+ev.slice(0,3).map(e=>"<span class='pro-month-event'>"+esc(e.title)+"</span>").join("")+"</div>")}
    grid.innerHTML=cells.join("");
  }

  function renderDayPlan(){
    $("dayPlan").innerHTML=dayPlan.map(x=>"<div class='pro-day-row'><div class='pro-day-time'>"+esc(x.time)+"</div><div><strong>"+esc(x.title)+"</strong><span>"+esc(x.detail||"")+"</span></div><div class='pro-card-actions'><button type='button' data-day-delete='"+esc(x.id)+"'>Remove</button></div></div>").join("");
    $("dayPlan").querySelectorAll("[data-day-delete]").forEach(b=>b.onclick=()=>{dayPlan=dayPlan.filter(x=>x.id!==b.dataset.dayDelete);write(keys.dayPlan,dayPlan);renderDayPlan()});
  }
  function addDayPlan(){modal("Add to your day","Add an activity, travel block, appointment or preparation step.",[{name:"time",label:"Time",type:"time",required:true},{name:"title",label:"What is happening?",required:true},{name:"detail",label:"Notes"}],d=>{dayPlan.push({id:uid("day"),time:d.time,title:d.title,detail:d.detail});dayPlan.sort((a,b)=>a.time.localeCompare(b.time));write(keys.dayPlan,dayPlan);renderDayPlan()})}

  function renderNotes(){
    const el=$("notesGrid");if(!notes.length){el.innerHTML="<div class='pro-empty'><strong>No notes yet</strong>Add reminders such as packed bags, booking deadlines or things to remember.</div>";return}
    el.innerHTML=notes.map(n=>"<article class='pro-note-card'><header><div><h3>"+esc(n.title)+"</h3><small>"+esc(n.date||"No date")+"</small></div><span>📝</span></header><p>"+esc(n.body)+"</p><button class='pro-text-button' type='button' data-note-delete='"+esc(n.id)+"'>Remove</button></article>").join("");
    el.querySelectorAll("[data-note-delete]").forEach(b=>b.onclick=()=>{notes=notes.filter(n=>n.id!==b.dataset.noteDelete);write(keys.notes,notes);renderNotes()});
  }
  function addNote(){modal("Add note or reminder","Keep the small details with your family plan.",[{name:"title",label:"Title",required:true},{name:"date",label:"Date",type:"date"},{name:"body",label:"Note",type:"textarea",required:true}],d=>{notes.unshift({id:uid("note"),title:d.title,date:d.date,body:d.body});write(keys.notes,notes);renderNotes()})}

  async function renderFavourites(){
    const el=$("favouritesGrid"),ids=bhGet(BH_KEYS.saved);if(!ids.length){el.innerHTML="<div class='pro-empty'><strong>No saved activities yet</strong>Save activities from the directory and they will appear here.</div>";return}
    try{const items=await bhActivities();const saved=ids.map(id=>items.find(a=>String(a.id)===id)).filter(Boolean);el.innerHTML=saved.length?saved.map(a=>"<article class='pro-favourite-card'><span class='pro-fav-icon'>♡</span><div><h3>"+esc(a.title)+"</h3><p>"+esc(a.town||a.location||"Family activity")+"</p><a href='"+(typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id))+"'>View activity →</a></div></article>").join(""):"<div class='pro-empty'><strong>Your saved activities are not available yet</strong>Browse the directory to save some favourites.</div>"}catch{el.innerHTML="<div class='pro-empty'><strong>Could not load favourites</strong>Please try again.</div>"} 
  }

  function setupProDashboard(){
    const links=[...document.querySelectorAll("[data-pro-section]")];
    const sections=[...document.querySelectorAll(".pro-content .pro-section")];
    if(!links.length||!sections.length)return;
    function show(id,updateHash=true){
      const valid=sections.some(section=>section.id===id)?id:sections[0].id;
      sections.forEach(section=>{
        const active=section.id===valid;
        section.classList.toggle("is-active",active);
        section.hidden=!active;
      });
      links.forEach(link=>link.classList.toggle("is-active",link.dataset.proSection===valid));
      if(updateHash)history.replaceState(null,"","#"+valid);
    }
    links.forEach(link=>link.addEventListener("click",event=>{event.preventDefault();show(link.dataset.proSection)}));
    show(location.hash.replace("#","")||"planners",false);
    window.addEventListener("hashchange",()=>show(location.hash.replace("#",""),false));
  }

  /* Initialise navigation before rendering so an async section cannot leave the dashboard blank. */
  setupProDashboard();

  $("addPlanner").onclick=addPlanner;$("addPlannerTop").onclick=addPlanner;$("addFamily").onclick=addFamily;$("createShare").onclick=createShare;$("addDayPlan").onclick=addDayPlan;$("addNote").onclick=addNote;
  $("prevMonth").onclick=()=>{monthDate.setMonth(monthDate.getMonth()-1);renderMonth()};$("nextMonth").onclick=()=>{monthDate.setMonth(monthDate.getMonth()+1);renderMonth()};
  document.querySelectorAll("[data-calendar-action]").forEach(b=>b.onclick=()=>alert("Calendar setup will connect to your shared planner when calendar accounts are enabled."));
  renderPlanners();renderFamily();renderShares();renderMonth();renderDayPlan();renderNotes();await renderFavourites();
});