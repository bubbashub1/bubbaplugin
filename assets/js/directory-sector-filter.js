/* Single-location sector switcher for the six-section Bubba Hub Directory. */
(function(){
  const sectors={
    listings:{title:'Listings',icon:'🎨'},
    events:{title:'Events',icon:'📅'},
    businesses:{title:'Businesses',icon:'🏢'},
    childcare:{title:'Childcare & Schools',icon:'🧸'},
    places:{title:'Places & Days Out',icon:'🌳'},
    clubs:{title:'School Clubs',icon:'🎒'}
  };
  function init(){
    const buttons=[...document.querySelectorAll('[data-directory-sector]')];
    const content=document.getElementById('directorySectorContent');
    const empty=document.getElementById('directorySectorComingSoon');
    if(!buttons.length||!content||!empty)return;
    const emptyTitle=document.getElementById('directorySectorEmptyTitle');
    const emptyIcon=document.getElementById('directorySectorEmptyIcon');
    const emptyText=document.getElementById('directorySectorEmptyText');
    function activate(key,updateUrl,triggerView){
      if(!sectors[key])key='listings';
      buttons.forEach(button=>{
        const active=button.dataset.directorySector===key;
        button.classList.toggle('is-active',active);
        button.setAttribute('aria-pressed',active?'true':'false');
      });
      const live=key==='listings'||key==='events';
      content.hidden=!live;
      empty.hidden=live;
      if(!live){
        emptyTitle.textContent=sectors[key].title;
        emptyIcon.textContent=sectors[key].icon;
        emptyText.textContent='We are preparing this searchable section, including its own filters and listing cards. It will appear here when its data and directory tools are ready.';
      }else if(triggerView){
        if(key==='events')window.dispatchEvent(new CustomEvent('bh:directory-show-events'));
        else document.getElementById('directoryShowList')?.click();
      }
      if(updateUrl){
        const url=new URL(window.location.href);
        if(key==='listings')url.searchParams.delete('sector');
        else url.searchParams.set('sector',key);
        window.history.replaceState({},'',url.pathname+url.search+url.hash);
      }
    }
    buttons.forEach(button=>button.addEventListener('click',()=>activate(button.dataset.directorySector,true,true)));
    ['directoryShowList','directoryShowCalendar','directoryShowPlanner','directoryShowMap'].forEach(id=>{
      const control=document.getElementById(id);
      if(control)control.addEventListener('click',()=>activate('listings',true,false));
    });
    const initial=new URLSearchParams(window.location.search).get('sector')||'listings';
    activate(initial,false,true);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
