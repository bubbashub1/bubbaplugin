/* Directory-only composition: reuse existing working filter controls, never clone IDs. */
(function(){
  if(!document.body.classList.contains('bh-directory')||document.body.classList.contains('bh-calendar-page'))return;
  async function init(){
    await Promise.allSettled([window.bhDirectoryHeroReady||Promise.resolve(),window.bhAdvancedFiltersReady||Promise.resolve()]);
    const banner=document.querySelector('.bh-directory-banner');
    const hero=document.querySelector('[data-bh-directory-hero-placeholder] .directory-hero-fullwidth');
    const tabs=document.querySelector('.bh-directory-view-actions');
    const filters=document.getElementById('directoryFilters');
    if(!banner||!hero||!tabs||!filters)return;
    banner.insertAdjacentElement('afterend',hero);
    hero.classList.add('bh-approved-search');
    const bar=document.createElement('section');
    bar.className='bh-approved-filter-bar';
    bar.setAttribute('aria-label','Refine your search');
    const heading=document.createElement('h2');
    heading.textContent='⚙ Refine your search:';
    bar.appendChild(heading);
    const chips=document.createElement('div');
    chips.className='bh-approved-filter-chips';
    bar.appendChild(chips);
    const keys=['category','age','day','price','region','town','session_length','term_time','free'];
    const names=['Category','Age','Day','Price','Region','Town','Sessions','Term Time','Free Only'];
    keys.forEach((key,index)=>{
      const field=filters.querySelector('[data-filter-key="'+key+'"]');
      if(!field)return;
      field.classList.add('bh-approved-chip');
      field.dataset.quickLabel=names[index];
      const label=field.querySelector('.directory-filter-label');
      if(label)label.textContent=names[index];
      if(key==='free'){
        const input=field.querySelector('input');
        if(input)input.setAttribute('aria-label','Free only');
      }
      chips.appendChild(field);
    });
    const more=document.createElement('details');
    more.className='bh-approved-more';
    const summary=document.createElement('summary');
    summary.textContent='More options';
    more.appendChild(summary);
    more.appendChild(filters);
    const advancedFields=document.getElementById('advancedFields');
    if(advancedFields)advancedFields.hidden=false;
    bar.appendChild(more);
    tabs.insertAdjacentElement('afterend',bar);
    const search=hero.querySelector('.directory-hero-search');
    if(search){
      const fields=search.querySelector('.directory-hero-search-fields');
      if(fields){
        fields.querySelectorAll('[data-bh-hero-field="region"],[data-bh-hero-field="town"],[data-bh-hero-field="category"],[data-bh-hero-field="day"]').forEach(el=>{const label=el.closest('label');if(label)label.classList.add('bh-approved-duplicate');});
      }
    }
    chips.addEventListener('change',event=>{
      if(!event.target.matches('select,input'))return;
      const apply=document.getElementById('applyFilters');
      if(apply)apply.click();
    });
    document.body.classList.add('bh-approved-directory-ready');
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();