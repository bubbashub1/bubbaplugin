(function(){
  "use strict";
  function init(){
    const button=document.getElementById("heroMoreFilters");
    const modal=document.getElementById("heroAdvancedModal");
    const close=document.getElementById("heroAdvancedClose");
    if(!button||!modal)return;
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
    modal.addEventListener("click",e=>{if(e.target===modal)setOpen(false);});
  }
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
})();