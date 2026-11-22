(()=>{'use strict';
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const safeImage=value=>{const s=String(value||'').trim();return s.startsWith('/')&&!s.startsWith('//')||/^https:\/\//i.test(s)?s:'';};
const labels={activities:'Activities / Classes',businesses:'Businesses',childcare:'Childcare',events:'Events',places:'Places & Days Out',school_clubs:'School Clubs'};
function init(root){
 if(root.dataset.bhSearchReady==='1')return;root.dataset.bhSearchReady='1';
 let config={};try{config=JSON.parse(root.dataset.config||'{}');}catch(e){}
 const scope=labels[config.search_scope]?config.search_scope:'all';
 const searchMap=config.search_fields&&typeof config.search_fields==='object'?config.search_fields:{};
 const filterMap=config.filter_fields&&typeof config.filter_fields==='object'?config.filter_fields:{};
 const showFilters=config.show_filters!==false;
 const id='bh-search-'+Math.random().toString(36).slice(2,9);
 const filterFields=Object.entries(filterMap).filter(([key,fields])=>(scope==='all'||scope===key)&&Array.isArray(fields)&&fields.length);
 root.innerHTML='<form class="bhds-form" role="search" aria-label="Search Bubba Hub"><label class="bhds-label" for="'+id+'-q">'+esc(config.label||'Search Bubba Hub')+'</label><div class="bhds-main"><input id="'+id+'-q" name="q" type="search" maxlength="120" placeholder="'+esc(config.placeholder||'What are you looking for?')+'"><button type="submit">Search</button></div>'+(showFilters&&filterFields.length?'<button type="button" class="bhds-filter-toggle" aria-expanded="false">Show filters</button><div class="bhds-filters" hidden>'+filterFields.map(([directory,fields])=>'<div class="bhds-filter-group" data-directory="'+esc(directory)+'"><strong>'+esc(labels[directory]||directory)+'</strong>'+fields.map(field=>'<label>'+esc(field.replace(/_/g,' '))+'<input type="text" data-filter-directory="'+esc(directory)+'" data-filter-field="'+esc(field)+'" maxlength="120"></label>').join('')+'</div>').join('')+'</div>':'')+(scope==='events'||scope==='all'?'<label class="bhds-date-label">Event dates<select name="event_date"><option value="">Any upcoming event</option><option value="today">Today</option><option value="weekend">This weekend</option><option value="month">This month</option></select></label>':'')+'</form><div class="bhds-status" role="status" aria-live="polite"></div><div class="bhds-results" aria-live="polite"></div>';
 const form=root.querySelector('form'),status=root.querySelector('.bhds-status'),results=root.querySelector('.bhds-results');
 const filterToggle=form.querySelector('.bhds-filter-toggle'),filterPanel=form.querySelector('.bhds-filters');
 if(filterToggle&&filterPanel)filterToggle.addEventListener('click',()=>{const open=filterPanel.hidden;filterPanel.hidden=!open;filterToggle.setAttribute('aria-expanded',String(open));filterToggle.textContent=open?'Hide filters':'Show filters';});
 form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('button[type=submit]');button.disabled=true;status.textContent='Searching…';results.replaceChildren();
  const filters={};form.querySelectorAll('[data-filter-field]').forEach(input=>{if(input.value.trim()){const key=input.dataset.filterDirectory;filters[key]=filters[key]||{};filters[key][input.dataset.filterField]=input.value.trim();}});
  const payload={q:form.elements.q.value.trim(),scope,search_fields:searchMap,filter_fields:filterMap,filters,event_date:form.elements.event_date?.value||'',limit:12};
  try{
   const response=await fetch('/api/directory-search.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'});
   const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Search is temporarily unavailable.');
   if(!data.results.length){status.textContent='No matching published listings found. Try a different search.';return;}
   status.textContent=data.count+' result'+(data.count===1?'':'s')+' found'+(data.warnings?.length?' · Some directories could not be searched':'');
   results.innerHTML=data.results.map(item=>'<article class="bhds-result">'+(safeImage(item.image)?'<img loading="lazy" src="'+esc(safeImage(item.image))+'" alt="">':'')+'<div><small>'+esc(item.directory_label||labels[item.directory]||'Directory')+'</small><h3><a href="'+esc(item.href||'#')+'">'+esc(item.title)+'</a></h3>'+(item.summary?'<p>'+esc(item.summary)+'</p>':'')+'<p class="bhds-location">'+esc([item.town,item.region].filter(Boolean).join(', '))+'</p>'+(item.next_occurrence?'<p class="bhds-date">Next date: '+esc(item.next_occurrence)+'</p>':'')+'</div></article>').join('');
  }catch(error){status.textContent=error.message||'Search is temporarily unavailable.';}
  finally{button.disabled=false;}
 });
}
document.querySelectorAll('.bh-directory-search[data-config]').forEach(init);
})();