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
    el.innerHTML=planners.map((p,i)=>{
      const filters=p.filters||{};
      const summary=plannerFilterSummary(filters);
      const query=plannerFilterQuery(filters);
      const filterText=summary.length?summary.slice(0,4).join(" · ")+(summary.length>4?" · +"+(summary.length-4)+" more":""):"No activity filters yet";
      const browse=query?"<a class='pro-planner-browse' href='directory.html?"+esc(query)+"'>Find matching activities →</a>":"<a class='pro-planner-browse' href='directory.html'>Find activities →</a>";
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

  const optionHtml=(options,selected="")=>{
    return options.map(option=>{
      const value=Array.isArray(option)?option[0]:option;
      const label=Array.isArray(option)?option[1]:option;
      return "<option value='"+esc(value)+"'"+(String(value)===String(selected)?" selected":"")+">"+esc(label)+"</option>";
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
    if(filters.category) params.set("category",filters.category);
    if(filters.region) params.set("region",filters.region);
    if(filters.town) params.set("town",filters.town);
    if(filters.day) params.set("day",filters.day);
    if(filters.age) params.set("age_preset",filters.age);
    if(filters.maxPrice) params.set("max_price",filters.maxPrice);
    if(filters.sessionLength) params.set("sessionLength",filters.sessionLength);
    if(filters.sen) params.set("sen",filters.sen);
    if(filters.termTime) params.set("termTime",filters.termTime);
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
              <label>Category<select name="category"><option value="">All categories</option>${optionHtml(filterOptions.category,filters.category)}</select></label>
              <label>Region<select name="region"><option value="">All regions</option>${optionHtml(filterOptions.region,filters.region)}</select></label>
              <label>Town<select name="town"><option value="">All towns</option>${optionHtml(filterOptions.town,filters.town)}</select></label>
              <label>Day<select name="day"><option value="">Any day</option>${optionHtml(filterOptions.day,filters.day)}</select></label>
              <label>Age<select name="age"><option value="">Any age</option>${optionHtml(filterOptions.age.slice(1),filters.age)}</select></label>
              <label>Price<select name="maxPrice">${optionHtml(filterOptions.maxPrice,filters.maxPrice)}</select></label>
              <label>Session length<select name="sessionLength">${optionHtml(filterOptions.sessionLength,filters.sessionLength)}</select></label>
              <label>SEN friendly<select name="sen">${optionHtml(filterOptions.sen,filters.sen)}</select></label>
              <label>Term time<select name="termTime">${optionHtml(filterOptions.termTime,filters.termTime)}</select></label>
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
      const name=String(data.name||"").trim();
      const description=String(data.description||"").trim();
      if(!name||!description)return;
      const newFilters={
        category:String(data.category||""),region:String(data.region||""),town:String(data.town||""),
        day:String(data.day||""),age:String(data.age||""),maxPrice:String(data.maxPrice||""),
        sessionLength:String(data.sessionLength||""),sen:String(data.sen||""),
        termTime:String(data.termTime||""),bookingRequired:data.bookingRequired==="on",free:data.free==="on",
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
    if(filters.category&&(categoryMap[activity.category]||activity.category)!==filters.category)return false;
    const venues=typeof bhVenues==="function"?bhVenues(activity):[];
    if(filters.region&&!venues.some(v=>String(v.region||activity.region||"")===String(filters.region)))return false;
    if(filters.town&&!venues.some(v=>String(v.town||activity.town||"")===String(filters.town)))return false;
    const sessions=typeof bhSessions==="function"?bhSessions(activity):[];
    if(filters.day&&!sessions.some(s=>String(s.day||"")===String(filters.day)))return false;
    if(filters.sen&&String(activity.sen||activity.sen_friendly||"").toLowerCase()!==String(filters.sen).toLowerCase())return false;
    if(filters.termTime){
      const term=String(activity.term_time||activity.term_time_only||"").toLowerCase();
      if(filters.termTime==="yes"&&!["yes","true","1"].includes(term))return false;
      if(filters.termTime==="no"&&["yes","true","1"].includes(term))return false;
    }
    if(filters.bookingRequired&&!(activity.booking_url||activity.bookingUrl||activity.bookable||activity.booking_required))return false;
    if(filters.free){
      const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
      if(!String(activity.price||"").toLowerCase().includes("free")&&price!==0)return false;
    }
    if(filters.maxPrice){
      if(filters.maxPrice==="over30"){
        const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
        if(!Number.isFinite(price)||price<=30)return false;
      }else{
        const price=Number(String(activity.price||"").replace(/[^0-9.]/g,""));
        if(!Number.isFinite(price)||price>Number(filters.maxPrice))return false;
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
        if(filters.day&&String(session.day||"")!==String(filters.day))return;
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
    const venue=e.session?.venue?.name||e.session?.venue?.town||e.activity.town||e.activity.location||"";
    return "<article class='pro-calendar-event'><span>"+esc(time||"Time TBC")+"</span><div><strong>"+esc(e.activity.title)+"</strong><small>"+esc(venue)+"</small></div></article>";
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
  renderPlannerCalendarPicker();
  const calendarPlanner=$("proCalendarPlanner");
  calendarPlanner?.addEventListener("change",()=>{selectedPlannerId=calendarPlanner.value;renderProCalendar()});
  document.querySelectorAll("[data-calendar-view]").forEach(button=>button.addEventListener("click",()=>{
    proCalendarView=button.dataset.calendarView;
    document.querySelectorAll("[data-calendar-view]").forEach(b=>b.classList.toggle("is-active",b===button));
    renderProCalendar();
  }));
  $("calendarToday")?.addEventListener("click",()=>{proCalendarDate=new Date();renderProCalendar()});
  $("prevMonth")?.addEventListener("click",()=>{
    if(proCalendarView==="month")proCalendarDate.setMonth(proCalendarDate.getMonth()-1);
    else if(proCalendarView==="week")proCalendarDate.setDate(proCalendarDate.getDate()-7);
    else proCalendarDate.setDate(proCalendarDate.getDate()-1);
    renderProCalendar();
  });
  $("nextMonth")?.addEventListener("click",()=>{
    if(proCalendarView==="month")proCalendarDate.setMonth(proCalendarDate.getMonth()+1);
    else if(proCalendarView==="week")proCalendarDate.setDate(proCalendarDate.getDate()+7);
    else proCalendarDate.setDate(proCalendarDate.getDate()+1);
    renderProCalendar();
  });
  renderProCalendar();
  const printMonth=$("printMonth");
  if(printMonth) printMonth.onclick=()=>window.print();
  document.querySelectorAll("[data-calendar-action]").forEach(b=>b.onclick=()=>alert("Calendar setup will connect to your shared planner when calendar accounts are enabled."));
  renderPlanners();renderFamily();renderShares();renderMonth();renderDayPlan();renderNotes();await renderFavourites();
});