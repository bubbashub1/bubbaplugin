function bindPopularCategoryButtons(){
  bindPopularCategoryButtons();
    document.querySelector('[data-bh-popular-categories]')?.addEventListener('bh:popular-ready', bindPopularCategoryButtons);
    syncAdvancedControls();
    document.querySelectorAll(".map-accessibility-option").forEach(el=>el.addEventListener("change",()=>{
      const selected=[...document.querySelectorAll(".map-accessibility-option:checked")].map(x=>x.value);
      if(selected.length) params.set("accessibility",selected.join(",")); else params.delete("accessibility");
      syncUrl(); render();
    }));
    ["mapSearch","mapRegion","mapTown","mapCategory","mapAge","mapDay","mapPrice"].forEach(id=>{const el=$(id);if(!el)return;el.addEventListener("change",()=>{syncUrl();render()});if(id==="mapSearch")el.addEventListener("keydown",e=>{if(e.key==="Enter"){e.preventDefault();syncUrl();render()}})});
    $("mapClear")?.addEventListener("click",()=>{history.replaceState({}, "", "map.html");location.reload()});
    const back=document.querySelector(".map-back-link");if(back)back.href="directory.html"+(location.search||"");
    render();
  }catch(error){$("mapStatus").textContent=error.message||"Activities could not be loaded."}
};
const bhInitMapStart=()=>{const ready=window.bhDirectoryHeroReady;if(ready)ready.then(bhInitMap);else bhInitMap();};
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",bhInitMapStart,{once:true});else bhInitMapStart();
