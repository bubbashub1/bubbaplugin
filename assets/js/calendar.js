document.addEventListener("DOMContentLoaded",()=>{(async()=>{
  await (window.bhAdvancedFiltersReady||Promise.resolve());
  const items=await bhActivities();
  const root=document.getElementById("calendar");
  const title=document.getElementById("calendarTitle");
  const count=document.getElementById("calendarCount");
  const params=new URLSearchParams(window.location.search);
  const state={
    view:"list",
    date:new Date(),
    filters:{keyword:"",region:"",town:"",category:"",day:"",age:"",maxPrice:"",sessionLength:"",termTime:"",bookingRequired:"",sen:"",free:"",accessibility:[]},
    plannerId:""
  };
  let planners=[];

  const start=d=>{const x=new Date(d);x.setHours(0,0,0,0);return x};
  const monday=d=>{const x=start(d),n=x.getDay();x.setDate(x.getDate()-(n===0?6:n-1));return x};
  const full=d=>d.toLocaleDateString("en-GB",{weekday:"long",day:"numeric",month:"long",year:"numeric"});
  const short=d=>d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short"});
  const norm=v=>String(v??"").trim().toLowerCase();
  const venues=a=>typeof bhVenues==="function"?bhVenues(a):[];
  const sessions=a=>typeof bhSessions==="function"?bhSessions(a):[];

  const readLocalPlanners=()=>{
    try{
      const value=JSON.parse(localStorage.getItem("bhProPlanners")||"[]");
      return Array.isArray(value)?value:[];
    }catch{return[]}
  };

  const selected=v=>Array.isArray(v)?v.map(norm).filter(Boolean):[norm(v)].filter(Boolean);
  const values=(a,s,key)=>{
    if(key==="category")return[a?.category,a?.type];
    if(key==="region")return[a?.region,...venues(a).map(v=>v.region)];
    if(key==="town")return[a?.town,...venues(a).map(v=>v.town)];
    if(key==="day")return[s?.day,s?.day_of_week];
    if(key==="age")return Array.isArray(a?.age_range)?a.age_range:[a?.age_range];
    if(key==="sen")return[a?.sen,a?.sen_friendly];
    if(key==="termTime")return[s?.term_time_only,a?.term_time_only,a?.termTime];
    if(key==="accessibility")return[a?.accessibility,...(Array.isArray(a?.accessibility_features)?a.accessibility_features:[])];
    if(key==="price")return[a?.price,s?.price];
    if(key==="sessionLength")return[s?.session_length,a?.session_length];
    return[];
  };

  const plannerMatches=(a,p)=>{
    if(!p?.filters)return true;
    const f=p.filters;

    for(const key of ["category","region","town","day","age","sen","termTime","accessibility"]){
      const wanted=selected(f[key]);
      if(!wanted.length)continue;
      const actual=values(a,null,key).map(norm).filter(Boolean);
      if(key==="day"){
        const ok=sessions(a).some(s=>wanted.some(w=>values(a,s,key).map(norm).includes(w)));
        if(!ok)return false;
      }else if(!wanted.some(w=>actual.some(v=>v===w||v.includes(w)||w.includes(v))))return false;
    }

    const max=selected(f.maxPrice)[0];
    if(max){
      const price=Number(String(values(a,null,"price")[0]??"").replace(/[^0-9.]/g,""));
      if(max==="0"&&Number.isFinite(price)&&price>0)return false;
      if(max!=="0"&&max!=="over30"){
        const limit=Number(max);
        if(Number.isFinite(limit)&&Number.isFinite(price)&&price>limit)return false;
      }
      if(max==="over30"&&Number.isFinite(price)&&price<=30)return false;
    }

    const length=selected(f.sessionLength)[0];
    if(length){
      const limit=Number(length);
      const activitySessions=sessions(a);
      if(activitySessions.length){
        const ok=activitySessions.some(s=>{
          const n=Number(String(s.session_length??a.session_length??"").replace(/[^0-9.]/g,""));
          return length==="181"?(Number.isFinite(n)&&n>180):(Number.isFinite(limit)&&Number.isFinite(n)&&n<=limit);
        });
        if(!ok)return false;
      }
    }

    if(f.free){
      const price=Number(String(a.price??"").replace(/[^0-9.]/g,""));
      if(String(a.price??"").toLowerCase().indexOf("free")<0&&Number.isFinite(price)&&price>0)return false;
    }

    if(f.bookingRequired){
      const b=a.booking_required??a.bookingRequired??a.booking_url??a.bookingUrl;
      if(!(b===true||b===1||["yes","true","1"].includes(norm(b))))return false;
    }
    return true;
  };

  const calendarMatches=a=>{
    const f=state.filters;
    const keyword=norm(f.keyword);
    const venueList=venues(a);
    const regions=[a.region,...venueList.map(v=>v.region)].map(norm);
    const towns=[a.town,...venueList.map(v=>v.town)].map(norm);
    const category=norm(a.category||a.type||"");

    if(keyword&&!norm(a.title).includes(keyword)&&!norm(a.content||a.description).includes(keyword))return false;
    if(f.region&&!regions.includes(norm(f.region)))return false;
    if(f.town&&!towns.includes(norm(f.town)))return false;
    if(f.category&&!category.includes(norm(f.category)))return false;
    if(f.day&&!sessions(a).some(s=>norm(s.day)===norm(f.day)))return false;

    const age=norm(f.age);
    if(age&&!values(a,null,"age").some(v=>norm(v)===age||norm(v).includes(age)))return false;

    const price=Number(String(a.price??"").replace(/[^0-9.]/g,""));
    if(f.maxPrice){
      const max=Number(f.maxPrice);
      if(Number.isFinite(max)&&(!String(a.price).toLowerCase().includes("free")&&(!Number.isFinite(price)||price>max)))return false;
    }
    if(f.sessionLength){
      const limit=Number(f.sessionLength);
      if(Number.isFinite(limit)&&sessions(a).length&&!sessions(a).some(s=>{
        const n=Number(String(s.session_length??a.session_length??"").replace(/[^0-9.]/g,""));
        return Number.isFinite(n)&&n<=limit;
      }))return false;
    }
    if(f.termTime){
      const wanted=norm(f.termTime);
      if(!sessions(a).some(s=>{
        const value=norm(s.term_time_only);
        return value===wanted;
      }))return false;
    }
    if(f.bookingRequired){
      const hasBooking=!!(a.booking_url||a.bookingUrl||a.bookable||a.booking_required===true||a.booking_required==="yes");
      if(f.bookingRequired==="yes"&&!hasBooking)return false;
      if(f.bookingRequired==="no"&&hasBooking)return false;
    }
    if(f.sen){
      const sen=norm(a.sen??a.sen_friendly);
      if(sen!==norm(f.sen))return false;
    }
    if(f.free){
      const rawPrice=String(a.price??"").toLowerCase();
      const numericPrice=Number(a.price_value);
      if(!(rawPrice.includes("free")||numericPrice===0))return false;
    }
    if(Array.isArray(f.accessibility)&&f.accessibility.length){
      const available=(Array.isArray(a.accessibility)?a.accessibility:[a.accessibility])
        .concat(Array.isArray(a.accessibility_features)?a.accessibility_features:[])
        .map(norm);
      if(f.accessibility.some(value=>!available.includes(norm(value))))return false;
    }
    return true;
  };

  const filteredItems=()=>{
    const planner=planners.find(p=>String(p.id)===String(state.plannerId));
    return items.filter(a=>calendarMatches(a)&&plannerMatches(a,planner));
  };

  const events=d=>filteredItems().flatMap(a=>sessions(a)
    .filter(s=>norm(s.day)===norm(d.toLocaleDateString("en-GB",{weekday:"long"})))
    .filter(s=>!state.filters.day||norm(s.day)===norm(state.filters.day))
    .map(s=>({...s,activity:a,venue:s.venue||{},date:d}))
  );

  const card=x=>'<article class="calendar-event"><div class="calendar-event-time">'+bhEscape(x.time||x.start_time||"")+'</div><div class="calendar-event-main"><span class="activity-meta">'+bhEscape(x.activity.category||"Activity")+'</span><h3>'+bhEscape(x.activity.title)+'</h3><p>'+bhEscape(x.venue.name||x.venue.address||x.venue.town||"")+(x.price!==undefined&&x.price!==""?" · "+bhEscape(x.price):"")+'</p><a class="button button-soft" href="activity.html?id='+encodeURIComponent(x.activity.id)+'">View activity</a></div></article>';
  const empty=m=>'<div class="calendar-empty"><h3>No activities</h3><p>'+bhEscape(m)+'</p><a class="button button-primary" href="directory.html">Find activities</a></div>';

  const list=()=>{
    const rows=[];
    for(let i=0;i<31;i++){
      const d=start(state.date);d.setDate(d.getDate()+i);
      const e=events(d);
      if(!e.length)continue;
      const shown=e.slice(0,5),remaining=e.length-shown.length;
      rows.push('<section class="calendar-list-day" data-calendar-day="'+d.toISOString()+'"><h3>'+bhEscape(full(d))+' <span class="calendar-day-count">('+e.length+')</span></h3>'+shown.map(card).join("")+(remaining?'<div class="calendar-day-more" aria-label="'+remaining+' more sessions">'+remaining+' more sessions will appear as you scroll</div>':"")+"</section>");
    }
    title.textContent="Upcoming activities";
    count.textContent=filteredItems().length+" activities";
    root.innerHTML=rows.join("")||empty("Nothing is showing in the next 31 days.");
    setupInfiniteScroll();
  };

  const day=()=>{
    const e=events(state.date);
    title.textContent=full(state.date);
    count.textContent=e.length+" sessions";
    root.innerHTML=e.length?'<div class="calendar-day-list">'+e.map(card).join("")+"</div>":empty("There are no activities scheduled for this day.");
  };

  const week=()=>{
    const s=monday(state.date),cols=[];
    for(let i=0;i<7;i++){
      const d=new Date(s);d.setDate(s.getDate()+i);
      const e=events(d);
      cols.push('<section class="calendar-week-day"><header><strong>'+d.toLocaleDateString("en-GB",{weekday:"short"})+'</strong><span>'+d.getDate()+"</span></header>"+(e.length?e.map(card).join(""):'<p class="calendar-no-events">No activities</p>')+"</section>");
    }
    title.textContent=short(s)+" – "+short(new Date(s.getTime()+6*86400000));
    count.textContent="Week view";
    root.innerHTML='<div class="calendar-week-grid">'+cols.join("")+"</div>";
  };

  const month=()=>{
    const y=state.date.getFullYear(),m=state.date.getMonth(),first=new Date(y,m,1),gridStart=new Date(first);
    gridStart.setDate(first.getDate()-(first.getDay()===0?6:first.getDay()-1));
    const cells=[];
    for(let i=0;i<42;i++){
      const d=new Date(gridStart);d.setDate(gridStart.getDate()+i);
      const e=events(d),muted=d.getMonth()!==m;
      cells.push('<button type="button" class="calendar-month-day'+(muted?" muted":"")+'" data-date="'+d.toISOString()+'"><span class="calendar-month-number">'+d.getDate()+'</span><span class="calendar-month-events">'+e.slice(0,3).map(x=>'<span>'+bhEscape((x.time||x.start_time||"")+" "+x.activity.title+" · "+(x.venue.name||""))+"</span>").join("")+(e.length>3?"<small>+"+(e.length-3)+" more</small>":"")+"</span></button>");
    }
    title.textContent=state.date.toLocaleDateString("en-GB",{month:"long",year:"numeric"});
    count.textContent="Month view";
    root.innerHTML='<div class="calendar-month-head">'+["Mon","Tue","Wed","Thu","Fri","Sat","Sun"].map(x=>"<span>"+x+"</span>").join("")+'</div><div class="calendar-month-grid">'+cells.join("")+"</div>";
    root.querySelectorAll(".calendar-month-day").forEach(b=>b.onclick=()=>{state.date=new Date(b.dataset.date);state.view="day";sync()});
  };

  const setupInfiniteScroll=()=>{
    if(window.__bhCalendarScrollBound)return;
    window.__bhCalendarScrollBound=true;
    window.addEventListener("scroll",()=>{
      if(state.view!=="list")return;
      if(window.innerHeight+window.scrollY<document.documentElement.scrollHeight-700)return;
      const sections=[...root.querySelectorAll(".calendar-list-day")];
      const last=sections[sections.length-1];
      if(!last)return;
      const nextDate=new Date(last.dataset.calendarDay);nextDate.setDate(nextDate.getDate()+1);
      let added=0;
      for(let i=0;i<14&&added<5;i++){
        const d=start(nextDate);d.setDate(nextDate.getDate()+i);
        const e=events(d);if(!e.length)continue;
        const shown=e.slice(0,5),remaining=e.length-shown.length,section=document.createElement("section");
        section.className="calendar-list-day";section.dataset.calendarDay=d.toISOString();
        section.innerHTML='<h3>'+bhEscape(full(d))+' <span class="calendar-day-count">('+e.length+')</span></h3>'+shown.map(card).join("")+(remaining?'<div class="calendar-day-more">'+remaining+' more sessions will appear as you scroll</div>':"");
        root.appendChild(section);added++;
      }
    },{passive:true});
  };

  const render=()=>{
    document.querySelectorAll(".calendar-view").forEach(b=>b.classList.toggle("active",b.dataset.view===state.view));
    if(state.view==="day")day();else if(state.view==="week")week();else if(state.view==="month")month();else list();
  };
  const sync=()=>render();

  document.querySelectorAll(".calendar-view").forEach(button=>button.addEventListener("click",()=>{
    const view=button.dataset.view;
    if(["list","day","week","month"].includes(view)){state.view=view;render()}
  }));

  const prev=document.getElementById("prev"),today=document.getElementById("today"),next=document.getElementById("next");
  prev?.addEventListener("click",()=>{if(state.view==="month")state.date.setMonth(state.date.getMonth()-1);else if(state.view==="week")state.date.setDate(state.date.getDate()-7);else state.date.setDate(state.date.getDate()-1);render()});
  today?.addEventListener("click",()=>{state.date=new Date();render()});
  next?.addEventListener("click",()=>{if(state.view==="month")state.date.setMonth(state.date.getMonth()+1);else if(state.view==="week")state.date.setDate(state.date.getDate()+7);else state.date.setDate(state.date.getDate()+1);render()});

  let plannerSelectBound=false;

  const loadPlannerPro=async({preserveSelection=true}={})=>{
    const previous=preserveSelection?state.plannerId:"";
    planners=readLocalPlanners();
    try{
      const response=await fetch("api/planner-pro.php",{cache:"no-store",credentials:"same-origin",headers:{Accept:"application/json"}});
      if(response.ok){
        const payload=await response.json();
        if(payload.ok&&payload.state&&Array.isArray(payload.state.planners)){
          const map=new Map(planners.map(p=>[String(p.id),p]));
          payload.state.planners.forEach(p=>map.set(String(p.id),p));
          planners=[...map.values()];
        }
      }
    }catch{}

    const select=document.getElementById("calendarPlannerSelect");
    if(!select)return;

    const requested=new URLSearchParams(window.location.search).get("proPlanner");
    const requestedExists=requested&&planners.some(p=>String(p.id)===String(requested));
    const selectedExists=previous&&planners.some(p=>String(p.id)===String(previous));
    const nextId=requestedExists?String(requested):(selectedExists?String(previous):"");

    select.innerHTML='<option value="">All activities</option>'+planners.map(p=>'<option value="'+bhEscape(String(p.id))+'">'+bhEscape(p.name||"Planner")+"</option>").join("");
    state.plannerId=nextId;
    select.value=nextId;

    if(!plannerSelectBound){
      plannerSelectBound=true;
      select.addEventListener("change",()=>{
        state.plannerId=select.value;
        const url=new URL(window.location.href);
        if(state.plannerId)url.searchParams.set("proPlanner",state.plannerId);
        else url.searchParams.delete("proPlanner");
        window.history.replaceState(null,"",url);
        render();
      });
    }
  };

  const refreshPlannerDropdown=async()=>{
    const current=state.plannerId;
    await loadPlannerPro({preserveSelection:true});
    if(current!==state.plannerId)render();
    else render();
  };

  window.addEventListener("storage",event=>{
    if(event.key==="bhProPlanners")refreshPlannerDropdown();
  });

  document.addEventListener("visibilitychange",()=>{
    if(document.visibilityState==="visible")refreshPlannerDropdown();
  });

  window.addEventListener("focus",refreshPlannerDropdown);

  const populateSharedFilterOptions=()=>{
    const categoryMap={"Baby classes":"Baby","Baby & toddler":"Toddler","Family activities":"Family"};
    const categoryValues=[...new Set(items.map(a=>categoryMap[a.category]||a.category).filter(Boolean))].sort();
    const regionValues=[...new Set(items.flatMap(a=>bhVenues(a).map(v=>v.region||a.region)).filter(Boolean))].sort();
    const townValues=[...new Set(items.flatMap(a=>bhVenues(a).map(v=>v.town||a.town)).filter(Boolean))].sort();
    const fill=(id,values,label,current)=>{
      const el=document.getElementById(id);
      if(!el)return;
      el.innerHTML='<option value="">'+label+'</option>'+values.map(v=>'<option value="'+bhEscape(v)+'">'+bhEscape(v)+'</option>').join("");
      if(current&&values.some(v=>norm(v)===norm(current)))el.value=current;
    };
    fill("category",categoryValues,"All categories",state.filters.category);
    fill("area",regionValues,"All regions",state.filters.region);
    fill("town",townValues,"All towns",state.filters.town);
  };

  const setupHero=async()=>{
    if(window.bhDirectoryHeroReady)await window.bhDirectoryHeroReady;
    const hero=document.querySelector("[data-bh-directory-hero]");
    if(!hero)return;
    const get=k=>hero.querySelector('[data-bh-hero-field="'+k+'"]');
    // Restore every filter from the URL, not only the five hero fields.
    ["keyword","region","town","category","day","age","maxPrice","sessionLength","termTime","bookingRequired","sen","free"].forEach(k=>{
      const value=params.get(k)||"";
      if(k==="bookingRequired")state.filters[k]=value;
      else state.filters[k]=value;
    });
    const accessibilityParam=params.get("accessibility");
    state.filters.accessibility=accessibilityParam?accessibilityParam.split(",").map(v=>v.trim()).filter(Boolean):[];
    ["keyword","region","town","category","day"].forEach(k=>{
      const value=params.get(k)||"";
      state.filters[k]=value;
      const el=get(k);if(el)el.value=value;
      el?.addEventListener("change",applyHero);
      el?.addEventListener("input",applyHero);
    });
    hero.querySelector("[data-bh-hero-form]")?.addEventListener("submit",e=>{e.preventDefault();applyHero()});
    populateSharedFilterOptions();
  };

  const applyHero=()=>{
    const hero=document.querySelector("[data-bh-directory-hero]");if(!hero)return;
    ["keyword","region","town","category","day"].forEach(k=>{
      const el=hero.querySelector('[data-bh-hero-field="'+k+'"]');state.filters[k]=el?.value||"";
    });
    const url=new URL(window.location.href);
    Object.entries(state.filters).forEach(([k,v])=>v?url.searchParams.set(k,v):url.searchParams.delete(k));
    window.history.replaceState(null,"",url);
    render();
  };

  const modal=document.getElementById("calendarAdvancedFilters");
  if(modal){
    const componentClear=document.getElementById("clear");
    if(componentClear)componentClear.hidden=true;
    const close=()=>{modal.hidden=true;document.body.classList.remove("calendar-filter-open")};
    modal.querySelectorAll("[data-calendar-filter-close]").forEach(b=>b.addEventListener("click",close));
    const setField=(id,value)=>{const el=document.getElementById(id);if(el)el.value=value||""};
    const setCheck=(id,value)=>{const el=document.getElementById(id);if(el)el.checked=!!value};
    const syncAdvancedFields=()=>{
      setField("category",state.filters.category);
      setField("area",state.filters.region);
      setField("town",state.filters.town);
      setField("day",state.filters.day);
      setField("ageRange",state.filters.age);
      setField("maxPrice",state.filters.maxPrice);
      setField("sessionLength",state.filters.sessionLength);
      setField("termTime",state.filters.termTime);
      setCheck("bookingRequired",state.filters.bookingRequired==="yes"||state.filters.bookingRequired==="1"||state.filters.bookingRequired===true);
      setField("sen",state.filters.sen);
      setCheck("free",state.filters.free==="1"||state.filters.free===true);
      const accessibility=selected(state.filters.accessibility);
      document.querySelectorAll(".accessibility-option").forEach(el=>el.checked=accessibility.includes(norm(el.value)));
    };
    syncAdvancedFields();
    const writeFilterUrl=()=>{
      const url=new URL(window.location.href);
      ["keyword","region","town","category","day","age","maxPrice","sessionLength","termTime","bookingRequired","sen","free"].forEach(k=>{
        const value=state.filters[k];
        if(value!==""&&value!==false&&value!=null)url.searchParams.set(k,String(value));
        else url.searchParams.delete(k);
      });
      if(state.filters.accessibility?.length)url.searchParams.set("accessibility",state.filters.accessibility.join(","));
      else url.searchParams.delete("accessibility");
      window.history.replaceState(null,"",url);
    };
    document.getElementById("calendarClearFilters")?.addEventListener("click",()=>{
      state.filters.category="";state.filters.region="";state.filters.town="";state.filters.day="";
      state.filters.age="";state.filters.maxPrice="";state.filters.sessionLength="";state.filters.termTime="";
      state.filters.bookingRequired="";state.filters.sen="";state.filters.free="";state.filters.accessibility=[];
      const hero=document.querySelector("[data-bh-directory-hero]");
      hero?.querySelectorAll("[data-bh-hero-field]").forEach(el=>el.value="");
      writeFilterUrl();syncAdvancedFields();close();render();
    });
    document.getElementById("calendarApplyFilters")?.addEventListener("click",()=>{
      state.filters.category=document.getElementById("category")?.value||"";
      state.filters.region=document.getElementById("area")?.value||"";
      state.filters.town=document.getElementById("town")?.value||"";
      state.filters.day=document.getElementById("day")?.value||"";
      state.filters.age=document.getElementById("ageRange")?.value||"";
      state.filters.maxPrice=document.getElementById("maxPrice")?.value||"";
      state.filters.sessionLength=document.getElementById("sessionLength")?.value||"";
      state.filters.termTime=document.getElementById("termTime")?.value||"";
      state.filters.bookingRequired=document.getElementById("bookingRequired")?.checked?"yes":"";
      state.filters.sen=document.getElementById("sen")?.value||"";
      state.filters.free=document.getElementById("free")?.checked?"1":"";
      state.filters.accessibility=[...document.querySelectorAll(".accessibility-option:checked")].map(el=>el.value);
      const hero=document.querySelector("[data-bh-directory-hero]");
      const heroField=k=>hero?.querySelector('[data-bh-hero-field="'+k+'"]');
      ["keyword","region","town","category","day"].forEach(k=>{const el=heroField(k);if(el)el.value=state.filters[k]||"";});
      writeFilterUrl();close();render();
    });
  }

  await loadPlannerPro();
  await setupHero();
  render();
})().catch(e=>{
  const root=document.getElementById("calendar");
  if(root)root.innerHTML='<div class="calendar-empty"><h3>Calendar unavailable</h3><p>'+bhEscape(e.message||"Unable to load calendar.")+"</p></div>";
})});