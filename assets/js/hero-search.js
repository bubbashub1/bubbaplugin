(function(){
  "use strict";

  const DEFAULTS={
    category:true,region:true,town:true,nearby:true,age:true,day:true,price:true,
    session_length:true,sen:true,term_time:true,booking:true,accessibility:true,free:true
  };

  const $=id=>document.getElementById(id);
  const setValue=(id,value)=>{const el=$(id);if(el)el.value=value??"";};
  const setChecked=(id,value)=>{const el=$(id);if(el)el.checked=!!value;};

  async function loadOptions(){
    try{
      const response=await fetch("api/activities.php?page=1&per_page=100",{cache:"no-store",headers:{Accept:"application/json"}});
      const payload=await response.json();
      const rows=Array.isArray(payload.data)?payload.data:[];
      const categories=[...new Set(rows.map(a=>a.category||"").filter(Boolean))].sort();
      const regions=[...new Set(rows.flatMap(a=>(Array.isArray(a.venues)?a.venues:[]).map(v=>v.region||"")).filter(Boolean))].sort();
      const towns=[...new Set(rows.flatMap(a=>(Array.isArray(a.venues)?a.venues:[]).map(v=>v.town||"")).filter(Boolean))].sort();
      const fill=(id,values,first)=>{
        const el=$(id); if(!el)return;
        const current=el.value;
        el.innerHTML='<option value="">'+first+'</option>'+values.map(v=>'<option value="'+escapeHtml(v)+'">'+escapeHtml(v)+'</option>').join("");
        if(values.includes(current))el.value=current;
      };
      fill("advancedCategory",categories,"All categories");
      fill("advancedRegion",regions,"Anywhere in Devon & Cornwall");
      fill("advancedTown",towns,"Any town");
    }catch(_){}
  }

  function escapeHtml(value){
    return String(value??"").replace(/[&<>"']/g,ch=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[ch]));
  }

  async function loadSettings(){
    try{
      const response=await fetch("api/search-settings.php",{cache:"no-store",headers:{Accept:"application/json"}});
      const payload=await response.json();
      return response.ok&&payload.ok&&payload.data?{...DEFAULTS,...payload.data}:DEFAULTS;
    }catch(_){return DEFAULTS;}
  }

  function applyVisibility(settings){
    document.querySelectorAll(".advanced-filter-field[data-filter-key]").forEach(el=>{
      const key=el.dataset.filterKey;
      el.hidden=settings[key]===false;
    });
  }

  function syncInitialValues(){
    const params=new URLSearchParams(location.search);
    ["category","region","town","day","max_price","sessionLength","sen","termTime","bookingRequired","accessibility"].forEach(key=>{
      const value=params.get(key);
      if(value!==null)setValue({
        category:"advancedCategory",region:"advancedRegion",town:"advancedTown",day:"advancedDay",
        max_price:"advancedPrice",sessionLength:"advancedSessionLength",sen:"advancedSen",
        termTime:"advancedTermTime",bookingRequired:"advancedBooking",accessibility:"advancedAccessibility"
      }[key],value);
    });
    setValue("advancedAgeMin",params.get("age_min")??"0");
    setValue("advancedAgeMax",params.get("age_max")??"9");
    setChecked("advancedFree",params.get("free")==="1");
  }

  function syncMapViewLink(){
    const link=document.querySelector(".directory-map-hero-button");
    if(!link)return;
    const onMap=/\/map\.html(?:$|[?#])/.test(window.location.pathname+window.location.search+window.location.hash);
    link.href=onMap ? "directory.html"+(window.location.search||"") : "map.html"+(window.location.search||"");
    link.textContent=onMap ? "View list of activities →" : "View activities on map →";
    link.setAttribute("aria-label",onMap ? "View list of activities" : "View activities on map");
  }

  function init(){
    syncMapViewLink();
    const button=$("heroMoreFilters"),modal=$("heroAdvancedModal"),close=$("heroAdvancedClose");
    if(!button||!modal)return;

    const form=$("heroAdvancedForm");
    const clear=$("heroAdvancedClear");

    const setOpen=open=>{
      modal.hidden=!open;
      modal.setAttribute("aria-hidden",String(!open));
      button.setAttribute("aria-expanded",String(open));
      document.body.classList.toggle("advanced-search-open",open);
      if(open)close?.focus();else button.focus();
    };

    button.addEventListener("click",e=>{e.preventDefault();e.stopPropagation();setOpen(true);});
    close?.addEventListener("click",()=>setOpen(false));
    modal.querySelector("[data-close-advanced]")?.addEventListener("click",()=>setOpen(false));
    document.addEventListener("keydown",e=>{if(e.key==="Escape"&&!modal.hidden)setOpen(false);});

    clear?.addEventListener("click",()=>{
      form?.reset();
      setValue("advancedAgeMin","0");setValue("advancedAgeMax","9");
    });

    form?.addEventListener("submit",e=>{
      e.preventDefault();
      const data=new FormData(form);
      const url=new URL("directory.html",document.baseURI);
      for(const [key,value] of data.entries()){
        if(value!=="" && value!=="0" && !(key==="age_min"&&value==="0") && !(key==="age_max"&&value==="9"))url.searchParams.set(key,value);
      }
      if(data.get("free")==="1")url.searchParams.set("free","1");
      if(data.get("nearby")==="1")url.searchParams.set("nearby","1");
      window.location.href=url.toString();
    });

    Promise.all([loadSettings(),loadOptions()]).then(([settings])=>{
      applyVisibility(settings);syncInitialValues();
    });
  }

  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
})();