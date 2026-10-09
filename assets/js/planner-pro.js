document.addEventListener("DOMContentLoaded",async()=>{
  try{
    const subResponse=await fetch("api/subscription.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
    const sub=await subResponse.json();
    const demo=window.BH_DEMO_MODE===true || localStorage.getItem("BH_PRO_DEMO")==="1";
    if(!sub.pro && !demo){
      document.querySelector(".pro-content")?.insertAdjacentHTML("afterbegin","<div class='admin-panel' style='margin-bottom:18px'><strong>Family Pro required</strong><p>Planner Pro is available with an active Family Pro account.</p><a class='button button-primary' href='/pro.html'>Upgrade to Family Pro →</a></div>");
      document.querySelectorAll(".pro-content button").forEach(b=>b.disabled=true);
      return;
    }
  }catch(e){
    if(!(window.BH_DEMO_MODE===true || localStorage.getItem("BH_PRO_DEMO")==="1")) return;
  }

  const $=id=>document.getElementById(id);
  const keys={planners:"bhProPlanners",family:"bhProFamily",shares:"bhProShares",notes:"bhProNotes",dayPlan:"bhProDayPlan",lists:"bhProActivityLists"};
  const read=(key,fallback=[])=>{try{const v=JSON.parse(localStorage.getItem(key)||"null");return Array.isArray(v)?v:fallback}catch{return fallback}};
  const hasLocal=(key)=>localStorage.getItem(key)!==null;
  let proSignedIn=false,proCsrf="",proHydrating=false,proSyncTimer=null,proShow=()=>{};

  async function proAuth(){
    try{
      const response=await fetch("api/auth.php?action=me",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const payload=await response.json();
      proSignedIn=!!payload.authenticated&&!payload.is_admin;
      proCsrf=payload.csrf||"";
    }catch{
      proSignedIn=false;
      proCsrf="";
    }
  }

  function proState(){
    return {
      version:1,
      planners,
      family,
      shares,
      notes,
      dayPlan,
      activityLists,
      selectedPlannerId,
      calendarView:proCalendarView,
      calendarDate:proCalendarDate instanceof Date?proCalendarDate.toISOString():null
    };
  }

  async function saveProState(){
    if(!proSignedIn||!proCsrf||proHydrating)return false;
    try{
      const response=await fetch("api/planner-pro.php",{
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json","Accept":"application/json"},
        body:JSON.stringify({csrf:proCsrf,state:proState()})
      });
      const payload=await response.json();
      if(payload.csrf)proCsrf=payload.csrf;
      return !!(response.ok&&payload.ok);
    }catch{return false}
  }

  function showProSaveStatus(message){
    let status=document.getElementById("proSaveStatus");
    if(!status){
      status=document.createElement("div");
      status.id="proSaveStatus";
      status.setAttribute("role","status");
      status.style.cssText="position:fixed;right:18px;bottom:18px;z-index:10001;padding:10px 14px;border-radius:12px;background:#416651;color:#fff;font:700 13px/1.2 system-ui,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.14);";
      document.body.appendChild(status);
    }
    status.textContent=message;
    clearTimeout(status._timer);
    status._timer=setTimeout(()=>status.remove(),2600);
  }

  function queueProSync(){
    if(proHydrating||!proSignedIn)return;
    clearTimeout(proSyncTimer);
    proSyncTimer=setTimeout(async()=>{
      const ok=await saveProState();
      if(!ok)showProSaveStatus("Saved on this device");
    },350);
  }

  const write=(key,value)=>{
    localStorage.setItem(key,JSON.stringify(value));
    queueProSync();
  };
  const uid=prefix=>prefix+"-"+Date.now().toString(36)+"-"+Math.random().toString(36).slice(2,7);
  const norm=v=>String(v??"").trim().toLowerCase();
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  const colours=["#416651","#3c8e96","#8068cf","#55a9c2","#ee6684","#9cb8a4"];
  let planners=read(keys.planners,[{id:"family",name:"Family",description:"Everyday family activities and plans.",colour:"#416651"}]);
  let family=read(keys.family,[{id:"everyone",name:"Everyone",role:"Family",colour:"#416651"}]);
  let shares=read(keys.shares,[]);
  let notes=read(keys.notes,[]);
  let dayPlan=read(keys.dayPlan,[{id:uid("day"),time:"09:30",title:"Morning activity",detail:"Add an activity from your planner."},{id:uid("day"),time:"12:30",title:"Lunch / travel",detail:"Leave space between plans."}]);
  let activityLists=read(keys.lists,[]);
  let monthDate=new Date();

  function mergeProArray(remoteValue,localValue,localExists){
    const remote=Array.isArray(remoteValue)?remoteValue:[];
    const local=Array.isArray(localValue)?localValue:[];
    if(!localExists)return remote.length?remote:local;
    const map=new Map(remote.map(item=>[String(item?.id??""),item]));
    local.forEach(item=>{
      const id=String(item?.id??"");
      if(id)map.set(id,item);
    });
    return [...map.values()];
  }

  async function hydrateProState(){
    await proAuth();
    if(!proSignedIn)return;

    try{
      const response=await fetch("api/planner-pro.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      const payload=await response.json();
      if(!response.ok||!payload.ok)return;
      proCsrf=payload.csrf||proCsrf;
      const remote=payload.state&&typeof payload.state==="object"?payload.state:{};
      proHydrating=true;

      planners=mergeProArray(remote.planners,planners,hasLocal(keys.planners));
      family=mergeProArray(remote.family,family,hasLocal(keys.family));
      shares=mergeProArray(remote.shares,shares,hasLocal(keys.shares));
      notes=mergeProArray(remote.notes,notes,hasLocal(keys.notes));
      dayPlan=mergeProArray(remote.dayPlan,dayPlan,hasLocal(keys.dayPlan));
      activityLists=mergeProArray(remote.activityLists,activityLists,hasLocal(keys.lists));

      if(remote.selectedPlannerId)selectedPlannerId=String(remote.selectedPlannerId);
      if(remote.calendarView)proCalendarView=String(remote.calendarView);
      if(remote.calendarDate){
        const parsed=new Date(remote.calendarDate);
        if(!Number.isNaN(parsed.getTime()))proCalendarDate=parsed;
      }

      Object.entries({
        [keys.planners]:planners,
        [keys.family]:family,
        [keys.shares]:shares,
        [keys.notes]:notes,
        [keys.dayPlan]:dayPlan,
        [keys.lists]:activityLists
      }).forEach(([key,value])=>localStorage.setItem(key,JSON.stringify(value)));
      proHydrating=false;

      const hadLocal=Object.values(keys).some(hasLocal);
      if(hadLocal)await saveProState();
    }catch{
      proHydrating=false;
    }
  }

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
        return "<label>"+esc(f.label)+"<select name='"+esc(f.name)+"'"+(f.className?" class='"+esc(f.className)+"'":"")+">"+options+"</select></label>";
      }
      return "<label>"+esc(f.label)+"<input name='"+esc(f.name)+"' type='"+esc(f.type||"text")+"'"+required+"></label>";
    }).join("");
    wrap.innerHTML="<div class='pro-modal' role='dialog' aria-modal='true'><h2>"+esc(title)+"</h2><p>"+esc(intro)+"</p><form class='pro-form'>"+fieldHtml+"<div class='pro-form-actions'><button type='button' class='button button-soft' data-cancel>Cancel</button><button class='button button-primary' type='submit'>Save</button></div></form></div>";
    document.body.appendChild(wrap);
    const form=wrap.querySelector("form");
    const close=()=>{
      wrap.remove();
      document.removeEventListener("keydown",onKey);
    };
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
    el.innerHTML=planners.map((p,i)=>{
      const filters=p.filters||{};
      const summary=plannerFilterSummary(filters);
      const query=plannerFilterQuery(filters);
      const filterText=summary.length?summary.slice(0,4).join(" · ")+(summary.length>4?" · +"+(summary.length-4)+" more":""):"No activity filters yet";
      const browse=query?"<div class='pro-planner-links'><a class='pro-planner-browse' href='planner.html?proPlanner="+encodeURIComponent(p.id)+"'>View Planner →</a><a class='pro-planner-browse' href='directory.html?"+esc(query)+"'>Find matching activities →</a></div>":"<div class='pro-planner-links'><a class='pro-planner-browse' href='planner.html?proPlanner="+encodeURIComponent(p.id)+"'>View Planner →</a><a class='pro-planner-browse' href='directory.html'>Find activities →</a></div>";
      return "<article class='pro-planner-card "+(i===0?"is-active":"")+"'><span class='pro-planner-swatch' style='background:"+esc(p.colour||colours[i%colours.length])+"'></span><div class='pro-planner-card-main'><h3>"+esc(p.name)+"</h3><p>"+esc(p.description||"Custom family planner")+"</p><div class='pro-planner-filters' aria-label='Planner activity preferences'>"+esc(filterText)+"</div>"+browse+"</div><div class='pro-card-actions'><button type='button' data-planner-edit='"+esc(p.id)+"'>Customise</button><button type='button' data-planner-delete='"+esc(p.id)+"'>Delete</button></div></article>";
    }).join("");
    el.querySelectorAll("[data-planner-edit]").forEach(b=>b.onclick=()=>editPlanner(b.dataset.plannerEdit));
    el.querySelectorAll("[data-planner-delete]").forEach(b=>b.onclick=()=>deletePlanner(b.dataset.plannerDelete));
  }
  /* New Planner modal component */
  const plannerFilterLabels={
    category:"Category",region:"Region",town:"Town",day:"Day",age:"Age",maxPrice:"Price",
    sessionLength:"Session length",sen:"SEN friendly",termTime:"Term time",
    bookingRequired:"Booking required",free:"Free only",accessibility:"Accessibility"
  };
  const defaultPlannerFilters=()=>({
    category:"",region:"",town:"",day:"",age:"",maxPrice:"",sessionLength:"",
    sen:"",termTime:"",bookingRequired:false,free:false,accessibility:[]
  });

  async function getPlannerFilterOptions(){
    const fallback={
      category:[],region:[],town:[],
      day:["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"],
      age:[["","Any age"],["baby","Baby · 0–1"],["toddler","Toddler · 1–3"],["preschool","Preschool · 3–5"],["school","School age · 5–9"]],
      maxPrice:[["","Any price"],["0","Free"],["5","Up to £5"],["10","Up to £10"],["15","Up to £15"],["20","Up to £20"],["30","Up to £30"],["over30","Over £30"]],
      sessionLength:[["","Any length"],["60","Up to 1 hour"],["120","1–2 hours"],["180","2–3 hours"],["181","3+ hours"]],
      sen:[["","Any"],["yes","Yes"],["no","No"]],
      termTime:[["","Any"],["yes","Term time only"],["no","Not term time only"]]
    };
    try{
      if(typeof bhActivities!=="function") return fallback;
      const activities=await bhActivities();
      const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
      const venues=activities.flatMap(a=>typeof bhVenues==="function"?bhVenues(a):[]);
      const unique=(values)=>[...new Set(values.map(v=>String(v||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
      return {
        ...fallback,
        category:unique(activities.map(a=>categoryMap[a.category]||a.category)),
        region:unique(venues.map(v=>v.region)),
        town:unique(venues.map(v=>v.town))
      };
    }catch(error){
      console.warn("Planner filter options could not be loaded.",error);
      return fallback;
    }
  }

  const optionHtml=(options,selected=[])=>{
    const selectedValues=Array.isArray(selected)?selected.map(String):(selected?[String(selected)]:[]);
    return options.map(option=>{
      const value=Array.isArray(option)?option[0]:option;
      const label=Array.isArray(option)?option[1]:option;
      return "<option value='"+esc(value)+"'"+(selectedValues.includes(String(value))?" selected":"")+">"+esc(label)+"</option>";
    }).join("");
  };

  const plannerFilterSummary=filters=>{
    const labels=[];
    Object.entries(plannerFilterLabels).forEach(([key,label])=>{
      const value=filters?.[key];
      if(Array.isArray(value)&&value.length) labels.push(label+": "+value.join(", "));
      else if(value===true) labels.push(label);
      else if(value!==""&&value!==null&&value!==undefined){
        const displayMap={maxPrice:"£"+value,sessionLength:value+" mins"};
        labels.push(label+": "+(displayMap[key]||value));
      }
    });
    return labels;
  };

  const plannerFilterQuery=filters=>{
    const params=new URLSearchParams();
    if(filters.category?.length) params.set("category",filters.category.join(","));
    if(filters.region?.length) params.set("region",filters.region.join(","));
    if(filters.town?.length) params.set("town",filters.town.join(","));
    if(filters.day?.length) params.set("day",filters.day.join(","));
    if(filters.age?.length) params.set("age_preset",filters.age.join(","));
    if(filters.maxPrice?.length) params.set("max_price",filters.maxPrice.join(","));
    if(filters.sessionLength?.length) params.set("sessionLength",filters.sessionLength.join(","));
    if(filters.sen?.length) params.set("sen",filters.sen.join(","));
    if(filters.termTime?.length) params.set("termTime",filters.termTime.join(","));
    if(filters.bookingRequired) params.set("bookingRequired","1");
    if(filters.free) params.set("free","1");
    if(filters.accessibility?.length) params.set("accessibility",filters.accessibility.join(","));
    return params.toString();
  };

  function plannerColourPicker(selected){
    return "<div class='pro-colour-picker' role='radiogroup' aria-label='Planner colour'>"+
      colours.map((colour,i)=>"<button type='button' class='pro-colour-option "+(colour===selected?"is-selected":"")+"' style='--planner-colour:"+colour+"' data-colour='"+esc(colour)+"' role='radio' aria-checked='"+(colour===selected?"true":"false")+"' aria-label='Colour "+(i+1)+"'></button>").join("")+
      "</div>";
  }

  async function openPlannerForm(options){
    const isEdit=Boolean(options&&options.planner);
    const p=options?.planner;
    const filters={...defaultPlannerFilters(),...(p?.filters||{})};
    filters.accessibility=Array.isArray(filters.accessibility)?filters.accessibility:[];
    ["category","region","town","day","age","maxPrice","sessionLength","sen","termTime"].forEach(key=>{filters[key]=Array.isArray(filters[key])?filters[key]:(filters[key]?[filters[key]]:[])});
    const initialColour=p?.colour||colours[planners.length%colours.length];
    const filterOptions=await getPlannerFilterOptions();
    const accessibilityOptions=[
      ["step-free","Step-free access"],["accessible-toilet","Accessible toilet"],["baby-changing","Baby changing"],
      ["parking","Accessible parking"],["wheelchair","Wheelchair friendly"],["sensory-friendly","Sensory-friendly"],
      ["hearing-support","Hearing support"],["visual-support","Visual support"]
    ];
    const wrap=document.createElement("div");
    wrap.className="pro-modal-backdrop pro-planner-modal";
    wrap.innerHTML=`
      <div class="pro-modal pro-planner-modal-card pro-planner-customise-modal" role="dialog" aria-modal="true" aria-labelledby="plannerFormTitle">
        <button class="pro-modal-close" type="button" aria-label="Close">×</button>
        <div class="pro-planner-modal-icon">▦</div>
        <span class="eyebrow">Planner Pro</span>
        <h2 id="plannerFormTitle">${isEdit?"Customise planner":"Create a new planner"}</h2>
        <p>${isEdit?"Choose the activities, locations and preferences this planner is designed around.":"Create a planning space and choose the activities you want it to focus on."}</p>
        <form class="pro-form">
          <label>Planner name
            <input name="name" type="text" placeholder="e.g. School & clubs" required maxlength="60" autocomplete="off" value="${esc(p?.name||"")}">
          </label>
          <label>What is it for?
            <textarea name="description" placeholder="e.g. School events, clubs and term dates." required maxlength="180">${esc(p?.description||"")}</textarea>
          </label>
          <fieldset class="pro-planner-filter-group">
            <legend>What should this planner focus on?</legend>
            <p class="pro-filter-help">These are saved preferences from Bubba Hub's Advanced search. You can change them whenever you like.</p>
            <div class="pro-planner-filter-grid">
              <label>Category<select name="category" multiple size="4" aria-label="Select categories">${optionHtml(filterOptions.category,filters.category)}</select></label>
              <label>Region<select name="region" multiple size="4" aria-label="Select regions">${optionHtml(filterOptions.region,filters.region)}</select></label>
              <label>Town<select name="town" multiple size="4" aria-label="Select towns">${optionHtml(filterOptions.town,filters.town)}</select></label>
              <label>Day<select name="day" multiple size="4" aria-label="Select days">${optionHtml(filterOptions.day,filters.day)}</select></label>
              <label>Age<select name="age" multiple size="4" aria-label="Select ages">${optionHtml(filterOptions.age.slice(1),filters.age)}</select></label>
              <label>Price<select name="maxPrice" multiple size="4" aria-label="Select price ranges">${optionHtml(filterOptions.maxPrice,filters.maxPrice)}</select></label>
              <label>Session length<select name="sessionLength" multiple size="4" aria-label="Select session lengths">${optionHtml(filterOptions.sessionLength,filters.sessionLength)}</select></label>
              <label>SEN friendly<select name="sen" multiple size="3" aria-label="Select SEN options">${optionHtml(filterOptions.sen,filters.sen)}</select></label>
              <label>Term time<select name="termTime" multiple size="3" aria-label="Select term time options">${optionHtml(filterOptions.termTime,filters.termTime)}</select></label>
            </div>
            <div class="pro-planner-checks">
              <label><input type="checkbox" name="free" ${filters.free?"checked":""}> Free only</label>
              <label><input type="checkbox" name="bookingRequired" ${filters.bookingRequired?"checked":""}> Bookable / booking required</label>
            </div>
            <details class="pro-planner-accessibility">
              <summary>Accessibility preferences</summary>
              <div class="pro-planner-accessibility-grid">
                ${accessibilityOptions.map(([value,label])=>"<label><input type='checkbox' name='accessibility' value='"+esc(value)+"' "+(filters.accessibility.includes(value)?"checked":"")+"> "+esc(label)+"</label>").join("")}
              </div>
            </details>
          </fieldset>
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
      const selectedValues=name=>[...form.querySelectorAll("select[name=\""+name+"\"] option:checked")].map(o=>o.value).filter(Boolean);
      const name=String(data.name||"").trim();
      const description=String(data.description||"").trim();
      if(!name||!description)return;
      const newFilters={
        category:selectedValues("category"),region:selectedValues("region"),town:selectedValues("town"),
        day:selectedValues("day"),age:selectedValues("age"),maxPrice:selectedValues("maxPrice"),
        sessionLength:selectedValues("sessionLength"),sen:selectedValues("sen"),
        termTime:selectedValues("termTime"),bookingRequired:data.bookingRequired==="on",free:data.free==="on",
        accessibility:[...form.querySelectorAll("input[name=accessibility]:checked")].map(input=>input.value)
      };
      if(isEdit){
        p.name=name;
        p.description=description;
        p.colour=data.colour||initialColour;
        p.filters=newFilters;
      }else{
        planners.push({id:uid("planner"),name,description,colour:data.colour||initialColour,filters:newFilters});
      }
      write(keys.planners,planners);
      renderPlanners();
      close();
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

  function renderActivityLists(){
    const el=$("activityListsGrid"); if(!el)return;
    if(!activityLists.length){el.innerHTML="<div class='pro-empty'><strong>No family activity lists yet</strong>Create a list such as Rainy Days, Holly’s Picks or Weekend Ideas.</div>";return;}
    el.innerHTML=activityLists.map(list=>{
      const items=(Array.isArray(window.__bhActivities)?window.__bhActivities:[]).filter(a=>(list.activityIds||[]).map(String).includes(String(a.id)));
      const names=items.length?items.slice(0,5).map(a=>"<li>"+esc(a.title)+"</li>").join(""):"<li>No activities added yet</li>";
      return "<article class='pro-planner-card'><span class='pro-planner-swatch' style='background:"+esc(list.colour||colours[0])+"'></span><div class='pro-planner-card-main'><h3>"+esc(list.name)+"</h3><p>"+esc(list.description||"Family activity list")+"</p><ul class='pro-list-items'>"+names+"</ul><small>"+items.length+" saved activit"+(items.length===1?"y":"ies")+"</small></div><div class='pro-card-actions'><button type='button' data-list-edit='"+esc(list.id)+"'>Edit</button><button type='button' data-list-delete='"+esc(list.id)+"'>Delete</button></div></article>";
    }).join("");
    el.querySelectorAll("[data-list-edit]").forEach(b=>b.onclick=()=>editActivityList(b.dataset.listEdit));
    el.querySelectorAll("[data-list-delete]").forEach(b=>b.onclick=()=>{activityLists=activityLists.filter(x=>x.id!==b.dataset.listDelete);write(keys.lists,activityLists);renderActivityLists()});
  }
  function activityListForm(existing){
    const activities=Array.isArray(window.__bhActivities)?window.__bhActivities:[]; const current=new Set((existing?.activityIds||[]).map(String));
    const wrap=document.createElement("div"); wrap.className="pro-modal-backdrop pro-planner-modal";
    wrap.innerHTML="<div class='pro-modal pro-planner-modal-card' role='dialog' aria-modal='true'><button class='pro-modal-close' type='button' aria-label='Close'>×</button><span class='eyebrow'>Family Activity Lists</span><h2>"+(existing?"Edit activity list":"Create activity list")+"</h2><p>Save a hand-picked collection of activities for a child, weekend, holiday or quick idea list.</p><form class='pro-form'><label>List name<input name='name' required maxlength='60' value='"+esc(existing?.name||"")+"'></label><label>Description<textarea name='description' maxlength='180'>"+esc(existing?.description||"")+"</textarea></label><fieldset><legend>Choose activities</legend><div class='pro-list-picker'>"+(activities.length?activities.map(a=>"<label><input type='checkbox' name='activityId' value='"+esc(a.id)+"' "+(current.has(String(a.id))?"checked":"")+"> "+esc(a.title)+" <small>"+esc(a.town||a.region||"")+"</small></label>").join(""):"<p>No activities are loaded yet. Browse the directory first.</p>")+"</div></fieldset><div class='pro-form-actions'><button type='button' class='button button-soft' data-cancel>Cancel</button><button type='submit' class='button button-primary'>Save list</button></div></form></div>";
    document.body.appendChild(wrap); const close=()=>wrap.remove(); wrap.querySelector(".pro-modal-close").onclick=close; wrap.querySelector("[data-cancel]").onclick=close; wrap.addEventListener("click",e=>{if(e.target===wrap)close()});
    wrap.querySelector("form").onsubmit=e=>{e.preventDefault();const form=e.currentTarget;const data=Object.fromEntries(new FormData(form));const ids=[...form.querySelectorAll("input[name='activityId']:checked")].map(x=>x.value);const item={id:existing?.id||uid("list"),name:data.name,description:data.description,activityIds:ids,updatedAt:new Date().toISOString()};if(existing)activityLists=activityLists.map(x=>x.id===existing.id?item:x);else activityLists.unshift(item);write(keys.lists,activityLists);renderActivityLists();close();showProSaveStatus("✓ Activity list saved");};
  }
  function addActivityList(){activityListForm(null)}
  function editActivityList(id){activityListForm(activityLists.find(x=>x.id===id))}

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

  let proCalendarView="list";
  let selectedPlannerId="";
  let proCalendarDate=new Date();

  function renderPlannerCalendarPicker(){
    const select=$("proCalendarPlanner");
    if(!select)return;
    if(!planners.length){
      select.innerHTML="<option value=''>Create a planner first</option>";
      selectedPlannerId="";
      return;
    }
    if(!selectedPlannerId||!planners.some(p=>String(p.id)===String(selectedPlannerId))) selectedPlannerId=String(planners[0].id);
    select.innerHTML=planners.map(p=>"<option value='"+esc(p.id)+"'>"+esc(p.name)+"</option>").join("");
    select.value=selectedPlannerId;
  }

  const plannerMatchesActivity=(activity,filters)=>{
    if(!filters)return true;
    const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
    if(filters.category?.length&&!filters.category.includes(categoryMap[activity.category]||activity.category))return false;
    const venues=typeof bhVenues==="function"?bhVenues(activity):[];
    if(filters.region?.length&&!venues.some(v=>filters.region.includes(String(v.region||activity.region||""))))return false;
    if(filters.town?.length&&!venues.some(v=>filters.town.includes(String(v.town||activity.town||""))))return false;
    const sessions=typeof bhSessions==="function"?bhSessions(activity):[];
    if(filters.day?.length&&!sessions.some(s=>filters.day.includes(String(s.day||""))))return false;
    if(filters.sen?.length&&!filters.sen.includes(String(activity.sen||activity.sen_friendly||"")))return false;
    if(filters.termTime?.length){
      const term=String(activity.term_time||activity.term_time_only||"").toLowerCase();
      if(filters.termTime.length&&!filters.termTime.some(v=>v==="yes"&&["yes","true","1"].includes(term)||v==="no"&&!["yes","true","1"].includes(term)))return false;
    }
    if(filters.bookingRequired&&!(activity.booking_url||activity.bookingUrl||activity.bookable||activity.booking_required))return false;
    if(filters.free){
      const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
      if(!String(activity.price||"").toLowerCase().includes("free")&&price!==0)return false;
    }
    if(filters.maxPrice?.length){
      if(filters.maxPrice.includes("over30")){
        const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
        if(!Number.isFinite(price)||price<=30)return false;
      }else if(filters.maxPrice.some(v=>Number.isFinite(Number(v)))){
        const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
        const limits=filters.maxPrice.filter(v=>Number.isFinite(Number(v))).map(Number);
        if(!Number.isFinite(price)||!limits.some(limit=>price<=limit))return false;
      }
    }
    if(filters.accessibility?.length){
      const source=JSON.stringify(activity).toLowerCase();
      if(!filters.accessibility.every(x=>source.includes(String(x).toLowerCase())))return false;
    }
    return true;
  };

  function calendarEvents(){
    const planner=planners.find(p=>String(p.id)===String(selectedPlannerId));
    if(!planner)return [];
    const filters=planner.filters||{};
    const items=Array.isArray(window.__bhActivities)?window.__bhActivities:[];
    const output=[];
    items.filter(a=>plannerMatchesActivity(a,filters)).forEach(activity=>{
      (typeof bhSessions==="function"?bhSessions(activity):[]).forEach(session=>{
        const day=Number(session.day_of_week||0);
        if(day<1||day>7)return;
        const date=proDateForDay(proCalendarDate,day);
        if(filters.day?.length&&!filters.day.includes(String(session.day||"")))return;
        const iso=date.toISOString().slice(0,10);
        if(session.start_date&&iso<session.start_date)return;
        if(session.end_date&&iso>session.end_date)return;
        output.push({activity,session,date,day});
      });
    });
    return output;
  }

  function proMondayOf(date){
    const d=new Date(date.getFullYear(),date.getMonth(),date.getDate());
    const n=d.getDay();d.setDate(d.getDate()-(n===0?6:n-1));return d;
  }
  function proDateForDay(date,day){
    const monday=proMondayOf(date),d=new Date(monday);d.setDate(monday.getDate()+day-1);return d;
  }
  function proAllEventsForRange(startDate,endDate){
    const planner=planners.find(p=>String(p.id)===String(selectedPlannerId));
    if(!planner)return [];
    const filters=planner.filters||{};
    const items=Array.isArray(window.__bhActivities)?window.__bhActivities:[];
    const result=[];
    items.filter(a=>plannerMatchesActivity(a,filters)).forEach(activity=>{
      (typeof bhSessions==="function"?bhSessions(activity):[]).forEach(session=>{
        const day=Number(session.day_of_week||0);if(day<1||day>7)return;
        for(let d=new Date(startDate);d<=endDate;d.setDate(d.getDate()+1)){
          if((d.getDay()||7)!==day)continue;
          const iso=d.toISOString().slice(0,10);
          if(session.start_date&&iso<session.start_date)continue;
          if(session.end_date&&iso>session.end_date)continue;
          if(filters.day&&String(session.day||"")!==String(filters.day))continue;
          result.push({activity,session,date:new Date(d),day});
        }
      });
    });
    return result;
  }

  function proEventCard(e){
    const time=e.session?(e.session.start_time||e.session.start||"").slice(0,5):"";
    const endTime=e.session?(e.session.end_time||e.session.end||"").slice(0,5):"";
    const venue=e.session?.venue?.name||e.session?.venue?.town||e.activity.town||e.activity.location||"";
    const conflicts=dayPlanConflicts(e.session);
    const warning=conflicts.length
      ?"<span class='pro-calendar-conflict' title='This activity overlaps a saved Plan a day time block'>⚠ "+esc(conflicts[0].title)+(conflicts.length>1?" +"+(conflicts.length-1):"")+"</span>"
      :"";
    return "<article class='pro-calendar-event "+(conflicts.length?"has-conflict":"")+"'><span>"+esc(time||"Time TBC")+(endTime?" – "+esc(endTime):"")+"</span><div><strong>"+esc(e.activity.title)+"</strong><small>"+esc(venue)+"</small>"+warning+"</div></article>";
  }

  function renderProCalendar(){
    const root=$("proCalendar"),label=$("monthLabel");
    if(!root)return;
    const planner=planners.find(p=>String(p.id)===String(selectedPlannerId));
    if(!planner){
      root.innerHTML="<div class='pro-empty'><strong>Select a planner</strong>Create a planner above before viewing its calendar.</div>";
      if(label)label.textContent="";
      return;
    }
    const dayStart=proCalendarView==="day"?new Date(proCalendarDate):proMondayOf(proCalendarDate);
    const dayEnd=proCalendarView==="day"?new Date(dayStart):new Date(dayStart);
    if(proCalendarView==="day")dayEnd.setDate(dayEnd.getDate());
    else if(proCalendarView==="week")dayEnd.setDate(dayEnd.getDate()+6);
    else if(proCalendarView==="list")dayEnd.setDate(dayEnd.getDate()+30);
    else {dayStart.setDate(1);dayEnd.setMonth(dayStart.getMonth()+1,0);}
    const events=proAllEventsForRange(dayStart,dayEnd);
    if(label){
      label.textContent=proCalendarView==="month"?proCalendarDate.toLocaleDateString("en-GB",{month:"long",year:"numeric"}):
        proCalendarView==="day"?proCalendarDate.toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long"}):
        proCalendarView==="week"?proMondayOf(proCalendarDate).toLocaleDateString("en-GB",{day:"numeric",month:"short"})+" – "+new Date(proMondayOf(proCalendarDate).getTime()+6*86400000).toLocaleDateString("en-GB",{day:"numeric",month:"short"}):
        "Next 31 days";
    }
    if(proCalendarView==="list"){
      const groups={};
      events.sort((a,b)=>a.date-b.date).forEach(e=>{const key=e.date.toISOString().slice(0,10);(groups[key]??=[]).push(e)});
      root.innerHTML="<div class='pro-calendar-list'>"+(Object.keys(groups).length?Object.entries(groups).map(([key,list])=>"<section><h3>"+esc(new Date(key+'T12:00:00').toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long"}))+"</h3>"+list.map(proEventCard).join("")+"</section>").join(""):"<div class='pro-empty'><strong>No matching activities</strong>Try changing this planner's filters.</div>")+"</div>";
      return;
    }
    if(proCalendarView==="day"){
      root.innerHTML="<div class='pro-calendar-day'>"+(events.length?events.map(proEventCard).join(""):"<div class='pro-empty'><strong>No activities</strong>Nothing matches this planner today.</div>")+"</div>";
      return;
    }
    if(proCalendarView==="week"){
      const startDay=proMondayOf(proCalendarDate),cols=[];
      for(let i=0;i<7;i++){const d=new Date(startDay);d.setDate(startDay.getDate()+i);const dayEvents=events.filter(e=>e.date.toDateString()===d.toDateString());cols.push("<section class='pro-calendar-week-day'><header><strong>"+esc(d.toLocaleDateString("en-GB",{weekday:"short"}))+"</strong><span>"+d.getDate()+"</span></header>"+(dayEvents.length?dayEvents.map(proEventCard).join(""):"<p>No activities</p>")+"</section>")}
      root.innerHTML="<div class='pro-calendar-week'>"+cols.join("")+"</div>";
      return;
    }
    const y=proCalendarDate.getFullYear(),m=proCalendarDate.getMonth(),first=new Date(y,m,1),start=new Date(first);start.setDate(1-(first.getDay()===0?6:first.getDay()-1));
    const cells=[];
    for(let i=0;i<42;i++){const d=new Date(start);d.setDate(start.getDate()+i);const dayEvents=events.filter(e=>e.date.toDateString()===d.toDateString());cells.push("<div class='pro-calendar-month-day "+(d.getMonth()!==m?"is-outside":"")+"'><span>"+d.getDate()+"</span>"+dayEvents.slice(0,3).map(proEventCard).join("")+"</div>")}
    root.innerHTML="<div class='pro-calendar-month-head'>"+["Mon","Tue","Wed","Thu","Fri","Sat","Sun"].map(x=>"<span>"+x+"</span>").join("")+"</div><div class='pro-calendar-month'>"+cells.join("")+"</div>";
  }

  function monthEvents(){ return calendarEvents(); }
  function renderMonth(){ renderProCalendar(); }


  function timeMinutes(value){
    const match=String(value||"").match(/^(\d{1,2}):(\d{2})$/);
    if(!match)return null;
    const hours=Number(match[1]),minutes=Number(match[2]);
    if(hours<0||hours>23||minutes<0||minutes>59)return null;
    return hours*60+minutes;
  }

  function dayPlanRange(item){
    const start=timeMinutes(item?.time);
    if(start===null)return null;
    const end=timeMinutes(item?.endTime);
    return {start,end:end!==null&&end>start?end:start+60};
  }
  function dayPlanConflicts(session){
    const range=session?{start:timeMinutes(session.start_time),end:timeMinutes(session.end_time)}:null;
    if(!range||range.start===null)return [];
    if(range.end===null||range.end<=range.start)range.end=range.start+60;
    return dayPlan.filter(item=>{
      const planned=dayPlanRange(item);
      return planned&&range.start<planned.end&&planned.start<range.end;
    });
  }
  function renderDayPlan(){
    const el=$("dayPlan");
    if(!el)return;
    const sorted=[...dayPlan].sort((a,b)=>String(a.time||"").localeCompare(String(b.time||"")));
    el.innerHTML=sorted.map(x=>{
      const timing=x.time+(x.endTime?" – "+x.endTime:" · 1 hour");
      const type=x.type?"<small class='pro-day-type'>"+esc(x.type)+"</small>":"";
      return "<div class='pro-day-row'><div class='pro-day-time'>"+esc(timing)+"</div><div>"+type+"<strong>"+esc(x.title)+"</strong><span>"+esc(x.detail||"")+"</span></div><div class='pro-card-actions'><button type='button' data-day-delete='"+esc(x.id)+"'>Remove</button></div></div>";
    }).join("");
    el.querySelectorAll("[data-day-delete]").forEach(b=>b.onclick=()=>{
      dayPlan=dayPlan.filter(x=>x.id!==b.dataset.dayDelete);
      write(keys.dayPlan,dayPlan);renderDayPlan();renderProCalendar();
    });
  }
  const planTimeOptions=Array.from({length:65},(_,i)=>{
    const minutes=360+i*15;
    const hours=Math.floor(minutes/60);
    const mins=minutes%60;
    const value=String(hours).padStart(2,"0")+":"+String(mins).padStart(2,"0");
    const labelHour=hours>12?hours-12:hours;
    const suffix=hours>=12?"pm":"am";
    return {value,label:labelHour+":"+String(mins).padStart(2,"0")+" "+suffix};
  });

  function addDayPlan(){
    modal("Add to your day","Create a reusable daily time block — perfect for naps, meals, nursery, travel or appointments.",[
      {name:"time",label:"Start time",type:"select",required:true,className:"pro-time-select",options:planTimeOptions},
      {name:"endTime",label:"End time",type:"select",required:true,className:"pro-time-select",options:planTimeOptions},
      {name:"type",label:"Type",type:"select",options:[
        {value:"Nap",label:"Nap / sleep"},{value:"Meal",label:"Meal"},{value:"Nursery",label:"Nursery / school"},
        {value:"Travel",label:"Travel"},{value:"Appointment",label:"Appointment"},{value:"Routine",label:"Routine"},{value:"Other",label:"Other"}
      ]},
      {name:"title",label:"What is happening?"},
      {name:"detail",label:"Notes"}
    ],d=>{
      const start=timeMinutes(d.time),end=timeMinutes(d.endTime);
      if(start===null||end===null||end<=start){alert("Please choose an end time after the start time.");return}
      const item={id:uid("day"),time:d.time,endTime:d.endTime,type:d.type,title:d.title,detail:d.detail};
      dayPlan.push(item);
      dayPlan.sort((a,b)=>String(a.time||"").localeCompare(String(b.time||"")));
      write(keys.dayPlan,dayPlan);
      renderDayPlan();
      renderProCalendar();
      showProSaveStatus("✓ Added to your day");
      void saveProState().then(ok=>showProSaveStatus(ok?"✓ Saved to your Bubba Hub account":"✓ Saved on this device"));
    })
  }

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
    proShow=show;
    links.forEach(link=>link.addEventListener("click",event=>{event.preventDefault();show(link.dataset.proSection)}));
    show(location.hash.replace("#","")||"planners",false);
    window.addEventListener("hashchange",()=>show(location.hash.replace("#",""),false));
  }

  /* Initialise navigation before rendering so an async section cannot leave the dashboard blank. */
  setupProDashboard();
  await hydrateProState();

  $("addPlanner").onclick=addPlanner;$("addPlannerTop").onclick=addPlanner;$("addFamily").onclick=addFamily;$("createShare").onclick=createShare;$("addDayPlan").onclick=addDayPlan;$("addNote").onclick=addNote;$("addActivityList").onclick=addActivityList;
  if(typeof bhActivities==="function"){try{window.__bhActivities=await bhActivities()}catch(error){window.__bhActivities=[];console.warn("Planner Pro calendar activities could not be loaded.",error)}}
  renderPlannerCalendarPicker();
  const calendarPlanner=$("proCalendarPlanner");
  calendarPlanner?.addEventListener("change",()=>{selectedPlannerId=calendarPlanner.value;queueProSync();renderProCalendar();renderPlanners()});
  document.querySelectorAll("[data-calendar-view]").forEach(button=>button.addEventListener("click",()=>{
    proCalendarView=button.dataset.calendarView;
    queueProSync();
    document.querySelectorAll("[data-calendar-view]").forEach(b=>b.classList.toggle("is-active",b===button));
    renderProCalendar();
  }));
  $("calendarToday")?.addEventListener("click",()=>{proCalendarDate=new Date();queueProSync();renderProCalendar()});
  $("prevMonth")?.addEventListener("click",()=>{
    if(proCalendarView==="month")proCalendarDate.setMonth(proCalendarDate.getMonth()-1);
    else if(proCalendarView==="week")proCalendarDate.setDate(proCalendarDate.getDate()-7);
    else proCalendarDate.setDate(proCalendarDate.getDate()-1);
    queueProSync();renderProCalendar();
  });
  $("nextMonth")?.addEventListener("click",()=>{
    if(proCalendarView==="month")proCalendarDate.setMonth(proCalendarDate.getMonth()+1);
    else if(proCalendarView==="week")proCalendarDate.setDate(proCalendarDate.getDate()+7);
    else proCalendarDate.setDate(proCalendarDate.getDate()+1);
    queueProSync();renderProCalendar();
  });
  renderProCalendar();
  const printMonth=$("printMonth");
  if(printMonth) printMonth.onclick=()=>window.print();
  document.querySelectorAll("[data-calendar-action]").forEach(b=>b.onclick=()=>alert("Calendar setup will connect to your shared planner when calendar accounts are enabled."));
  renderPlanners();renderFamily();renderShares();renderMonth();renderDayPlan();renderNotes();renderActivityLists();await renderFavourites();
  const jump=document.getElementById("proJumpTo");
  if(jump){
    jump.addEventListener("change",()=>{
      if(jump.value){
        proShow(jump.value);
        jump.blur();
      }
    });
  }
});