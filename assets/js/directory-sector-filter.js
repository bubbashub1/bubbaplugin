/* Single-location sector selector for the six-section Bubba Hub Directory. */
(function(){
  const sectors={
    listings:{title:"Listings",icon:"🎨"},
    events:{title:"Events",icon:"📅"},
    businesses:{title:"Businesses",icon:"🏢"},
    childcare:{title:"Childcare & Schools",icon:"🧸"},
    places:{title:"Places & Days Out",icon:"🌳"},
    clubs:{title:"School Clubs",icon:"🎒"}
  };
  async function init(){
    const content=document.getElementById("directorySectorContent");
    const empty=document.getElementById("directorySectorComingSoon");
    const businesses=document.getElementById("directoryBusinessesView");
    const listingsColumn=content.querySelector(".directory-results-column");
    if(!content||!empty)return;
    try{await (window.bhDirectoryHeroReady||Promise.resolve());}catch(_){}
    const keyword=document.querySelector('[data-bh-hero-field="keyword"]');
    const keywordLabel=keyword?.closest("label");
    if(!keywordLabel)return;
    let select=document.getElementById("directorySectorSelect");
    if(!select){
      select=document.createElement("select");
      select.id="directorySectorSelect";
      select.name="sector";
      select.className="bh-directory-sector-select";
      select.setAttribute("aria-label","Choose a directory");
      select.title="Choose which directory to search";
      Object.entries(sectors).forEach(([key,sector])=>{
        const option=document.createElement("option");
        option.value=key;
        option.textContent=sector.title;
        select.appendChild(option);
      });
      keywordLabel.appendChild(select);
    }
    const emptyTitle=document.getElementById("directorySectorEmptyTitle");
    const emptyIcon=document.getElementById("directorySectorEmptyIcon");
    const emptyText=document.getElementById("directorySectorEmptyText");
    function activate(key,updateUrl,triggerView){
      if(!sectors[key])key="listings";
      select.value=key;
      const live=key==="listings"||key==="events"||key==="businesses";
      content.hidden=!live;
      empty.hidden=live;
      if(listingsColumn)listingsColumn.hidden=key==="businesses";
      if(businesses)businesses.hidden=key!=="businesses";
      if(!live){
        emptyTitle.textContent=sectors[key].title;
        emptyIcon.textContent=sectors[key].icon;
        emptyText.textContent="We are preparing this searchable section, including its own filters and listing cards. It will appear here when its data and directory tools are ready.";
      }else if(triggerView){
        if(key==="events")window.dispatchEvent(new CustomEvent("bh:directory-show-events"));
        else if(key==="listings")document.getElementById("directoryShowList")?.click();
      }
      if(updateUrl){
        const url=new URL(window.location.href);
        if(key==="listings")url.searchParams.delete("sector");
        else url.searchParams.set("sector",key);
        window.history.replaceState({}, "", url.pathname+url.search+url.hash);
      }
    }
    select.addEventListener("change",()=>activate(select.value,true,true));
    ["directoryShowList","directoryShowCalendar","directoryShowPlanner","directoryShowMap"].forEach(id=>{
      const control=document.getElementById(id);
      if(control)control.addEventListener("click",()=>{
        if(select.value!=="listings")activate("listings",true,false);
      });
    });
    const initial=new URLSearchParams(window.location.search).get("sector")||"listings";
    activate(initial,false,true);
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});
  else init();
})();