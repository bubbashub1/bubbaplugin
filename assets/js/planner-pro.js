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
    const wrap=document.createElement("div");
    wrap.className="pro-modal-backdrop";
    const fieldHtml=fields.map(f=>{
      const required=f.required?" required":"";
      if(f.type==="textarea"){
        return "<label>"+esc(f.label)+"<textarea name='"+esc(f.name)+"'"+required+"></textarea></label>";
      }
      if(f.type==="select"){
        const options=(f.options||[]).map(o=>"<option value='"+esc(o.value)+"'>"+esc(o.label)+"</option>").join("");
        return "<label>"+esc(f.label)+"<select name='"+esc(f.name)+"'>"+options+"</select></label>";
      }
      return "<label>"+esc(f.label)+"<input name='"+esc(f.name)+"' type='"+esc(f.type||"text")+"'"+required+"></label>";
    }).join("");
    wrap.innerHTML="<div class='pro-modal' role='dialog' aria-modal='true'><h2>"+esc(title)+"</h2><p>"+esc(intro)+"</p><form class='pro-form'>"+fieldHtml+"<div class='pro-form-actions'><button type='button' class='button button-soft' data-cancel>Cancel</button><button class='button button-primary' type='submit'>Save</button></div></form></div>";
    document.body.appendChild(wrap);
    const form=wrap.querySelector("form");
    const close=()=>wrap.remove();
    form.querySelector("[data-cancel]").onclick=close;
    form.onsubmit=e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(form));
      onSave(data);
      close();
    };
  }

  function renderPlanners(){
    const el=$("proPlannerList");if(!planners.length){el.innerHTML="<div class='pro-empty'><strong>No planners yet</strong>Create your first custom planner.</div>";return}
    el.innerHTML=planners.map((p,i)=>"<article class='pro-planner-card "+(i===0?"is-active":"")+"'><span class='pro-planner-swatch' style='background:"+esc(p.colour||colours[i%colours.length])+"'></span><div class='pro-planner-card-main'><h3>"+esc(p.name)+"</h3><p>"+esc(p.description||"Custom family planner")+"</p></div><div class='pro-card-actions'><button type='button' data-planner-edit='"+esc(p.id)+"'>Edit</button><button type='button' data-planner-delete='"+esc(p.id)+"'>Delete</button></div></article>").join("");
    el.querySelectorAll("[data-planner-edit]").forEach(b=>b.onclick=()=>editPlanner(b.dataset.plannerEdit));
    el.querySelectorAll("[data-planner-delete]").forEach(b=>b.onclick=()=>deletePlanner(b.dataset.plannerDelete));
  }
  /* New Planner modal component */
  function plannerColourPicker(selected){
    return "<div class='pro-colour-picker' role='radiogroup' aria-label='Planner colour'>"+
      colours.map((colour,i)=>"<button type='button' class='pro-colour-option "+(colour===selected?"is-selected":"")+"' style='--planner-colour:"+colour+"' data-colour='"+esc(colour)+"' role='radio' aria-checked='"+(colour===selected?"true":"false")+"' aria-label='Colour "+(i+1)+"'></button>").join("")+
      "</div>";
  }

  function openPlannerForm(options){
    const isEdit=Boolean(options&&options.planner);
    const p=options?.planner;
    const initialColour=p?.colour||colours[planners.length%colours.length];
    const wrap=document.createElement("div");
    wrap.className="pro-modal-backdrop pro-planner-modal";
    wrap.innerHTML=`
      <div class="pro-modal pro-planner-modal-card" role="dialog" aria-modal="true" aria-labelledby="plannerFormTitle">
        <button class="pro-modal-close" type="button" aria-label="Close">×</button>
        <div class="pro-planner-modal-icon">▦</div>
        <span class="eyebrow">Planner Pro</span>
        <h2 id="plannerFormTitle">${isEdit?"Edit planner":"Create a new planner"}</h2>
        <p>${isEdit?"Update the name, purpose or colour for this planning space.":"Set up a separate planning space for school, holidays, a child or anything else your family needs."}</p>
        <form class="pro-form">
          <label>Planner name
            <input name="name" type="text" placeholder="e.g. School & clubs" required maxlength="60" autocomplete="off" value="${esc(p?.name||"")}">
          </label>
          <label>What is it for?
            <textarea name="description" placeholder="e.g. School events, clubs and term dates." required maxlength="180">${esc(p?.description||"")}</textarea>
          </label>
          <fieldset class="pro-colour-field">
            <legend>Planner colour</legend>
            ${plannerColourPicker(initialColour)}
            <input type="hidden" name="colour" value="${esc(initialColour)}">
          </fieldset>
          <div class="pro-form-actions">
            <button type="button" class="button button-soft" data-cancel>Cancel</button>
            <button type="submit" class="button button-primary">${isEdit?"Save changes":"Create planner"}</button>
          </div>
        </form>
      </div>`;
    document.body.appendChild(wrap);
    const form=wrap.querySelector("form");
    const close=()=>wrap.remove();
    wrap.querySelector(".pro-modal-close").onclick=close;
    wrap.querySelector("[data-cancel]").onclick=close;
    wrap.addEventListener("click",e=>{if(e.target===wrap)close()});
    const colourInput=form.querySelector("[name=colour]");
    form.querySelectorAll("[data-colour]").forEach(button=>{
      button.onclick=()=>{
        colourInput.value=button.dataset.colour;
        form.querySelectorAll("[data-colour]").forEach(item=>{
          const active=item===button;
          item.classList.toggle("is-selected",active);
          item.setAttribute("aria-checked",String(active));
        });
      };
    });
    const onKey=e=>{if(e.key==="Escape"){close();document.removeEventListener("keydown",onKey)}};
    document.addEventListener("keydown",onKey);
    form.onsubmit=e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(form));
      const name=String(data.name||"").trim();
      const description=String(data.description||"").trim();
      if(!name||!description)return;
      if(isEdit){
        p.name=name;
        p.description=description;
        p.colour=data.colour||initialColour;
      }else{
        planners.push({id:uid("planner"),name,description,colour:data.colour||initialColour});
      }
      write(keys.planners,planners);
      renderPlanners();
      close();
      document.removeEventListener("keydown",onKey);
    };
    requestAnimationFrame(()=>form.querySelector("input")?.focus());
  }

  function openNewPlannerModal(){openPlannerForm({});}
  function addPlanner(){openNewPlannerModal();}

  function editPlanner(id){
    const p=planners.find(x=>x.id===id);
    if(!p)return;
    openPlannerForm({planner:p});
  }

  function deletePlanner(id){
    if(planners.length<=1){
      alert("Keep at least one planner. Create another planner before deleting this one.");
      return;
    }
    const p=planners.find(x=>x.id===id);
    if(!p)return;
    const confirmed=window.confirm('Delete "'+p.name+'"? This removes the planner from Planner Pro. Any shared links for this planner will also be revoked.');
    if(!confirmed)return;
    planners=planners.filter(x=>x.id!==id);
    shares=shares.filter(x=>x.plannerId!==id);
    write(keys.planners,planners);
    write(keys.shares,shares);
    renderPlanners();
    renderShares();
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
    const events=[];
    try{
      const planned=typeof bhGet==="function" && typeof BH_KEYS!=="undefined" ? bhGet(BH_KEYS.planner) : [];
      const items=Array.isArray(window.__bhActivities) ? window.__bhActivities : [];
      planned.forEach(id=>{
        const activity=items.find(item=>String(item.id)===String(id));
        if(!activity || typeof bhSessions!=="function") return;
        const sessions=bhSessions(activity)||[];
        sessions.forEach(session=>{
          const day=Number(session.day_of_week||0);
          if(day<1 || day>7) return;
          const first=new Date(monthDate.getFullYear(),monthDate.getMonth(),1);
          const firstDay=(first.getDay()||7);
          const date=new Date(first);
          date.setDate(1+((day-firstDay+7)%7));
          while(date.getMonth()===monthDate.getMonth()){
            const iso=date.toISOString().slice(0,10);
            if(session.start_date && iso<session.start_date){
              date.setDate(date.getDate()+7);
              continue;
            }
            if(session.end_date && iso>session.end_date) break;
            events.push({date:date.getDate(),title:activity.title});
            date.setDate(date.getDate()+7);
          }
        });
      });
    }catch(error){
      console.warn("Planner Pro monthly events could not be loaded.",error);
    }
    return events;
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
    const el=$("favouritesGrid"),ids=typeof bhGet==="function"&&typeof BH_KEYS!=="undefined"?bhGet(BH_KEYS.saved):[];if(!ids.length){el.innerHTML="<div class='pro-empty'><strong>No saved activities yet</strong>Save activities from the directory and they will appear here.</div>";return}
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