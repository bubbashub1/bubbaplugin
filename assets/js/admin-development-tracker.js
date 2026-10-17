(function () {
  'use strict';
  const tasks = [
    {id:'csv-email',title:'CSV class leader invitations',area:'Accounts & emails',url:'https://github.com/bubbashub1/bubbaplugin/pull/131',next:'Verify deployment, import one test account, check activation email and password setup.'},
    {id:'welcome',title:'Normal registration welcome emails',area:'Accounts & emails',url:'https://github.com/bubbashub1/bubbaplugin/pull/130',next:'Review draft PR and test both verification paths on staging.'},
    {id:'sheets',title:'Google Sheets synchronisation',area:'Data & imports',url:'https://github.com/bubbashub1/bubbaplugin/pull/129',next:'Review the read-only matching and conflict previews before enabling writes.'},
    {id:'security',title:'Admin account management security',area:'Security',url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr+admin+security',next:'Complete protected user-management testing before wider access.'},
    {id:'wizard',title:'Listing wizard staging and live parity',area:'Class leaders',url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr+listing+wizard',next:'Compare staging and live wizard forms and confirm saved sessions.'},
    {id:'events',title:'Events and expo ticketing',area:'Events',url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr+events',next:'Test free reservations, QR confirmation and exhibitor registration on staging.'},
    {id:'release',title:'Safe staging and live releases',area:'Deployment',url:'https://github.com/bubbashub1/bubbaplugin/actions',next:'Verify live .htaccess and GitHub deployment results before another release.'}
  ];
  const key='bh-admin-development-tracker-v1';
  const allowed=['not-started','in-progress','testing','done'];
  const labels={'not-started':'Not started','in-progress':'In progress',testing:'Ready to test',done:'Completed'};
  const list=document.getElementById('developmentTaskList');
  const filter=document.getElementById('developmentFilter');
  const status=document.getElementById('developmentTrackerStatus');
  if(!list || !filter) return;
  let saved={};
  try {const value=JSON.parse(localStorage.getItem(key)||'{}');if(value&&typeof value==='object'&&!Array.isArray(value))saved=value;}catch(_){}
  function state(id){return allowed.includes(saved[id])?saved[id]:'not-started';}
  function persist(){try{localStorage.setItem(key,JSON.stringify(saved));status.textContent='Checklist saved on this device.';}catch(_){status.textContent='Browser storage is unavailable; changes will not persist.';}}
  const ideasKey='bh-admin-development-ideas-v1';
  let ideas=[];
  try {const parsed=JSON.parse(localStorage.getItem(ideasKey)||'[]');if(Array.isArray(parsed))ideas=parsed.filter(t=>t&&typeof t.title==='string'&&typeof t.id==='string').slice(0,200);}catch(_){}
  function allTasks(){return tasks.concat(ideas);}
  function cell(row,value){const td=document.createElement('td');td.textContent=value;td.style.cssText='padding:12px 10px;border-bottom:1px solid #d6e3d8;vertical-align:top';row.append(td);return td;}
  function render(){
    list.replaceChildren();
    const selected=filter.value;
    const visible=allTasks().filter(t=>selected==='all'||(selected==='next'&&state(t.id)!=='done'&&state(t.id)!=='testing')||state(t.id)===selected);
    if(!visible.length){const tr=document.createElement('tr');const td=cell(tr,'No tasks in this view.');td.colSpan=5;list.append(tr);}
    visible.forEach(t=>{
      const tr=document.createElement('tr');
      const title=cell(tr,t.title);
      if(t.custom){const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.className='button button-soft';remove.style.marginLeft='8px';remove.addEventListener('click',()=>{if(!confirm('Remove this idea from your local checklist?'))return;ideas=ideas.filter(x=>x.id!==t.id);try{localStorage.setItem(ideasKey,JSON.stringify(ideas));}catch(_){}delete saved[t.id];persist();render();});title.append(remove);}
      cell(tr,t.area||'New idea');
      const progress=cell(tr,'');const select=document.createElement('select');select.setAttribute('aria-label','Progress for '+t.title);
      allowed.forEach(v=>{const option=document.createElement('option');option.value=v;option.textContent=labels[v];select.append(option);});
      select.value=state(t.id);select.addEventListener('change',()=>{saved[t.id]=select.value;persist();render();});progress.append(select);
      cell(tr,t.next||'Review idea');
      const action=cell(tr,'');if(t.url){const link=document.createElement('a');link.href=t.url;link.target='_blank';link.rel='noopener noreferrer';link.textContent='Open work';action.append(link);}else{action.textContent='Idea added';}
      list.append(tr);
    });
  }
  document.getElementById('developmentAddForm')?.addEventListener('submit',event=>{
    event.preventDefault();
    const title=document.getElementById('developmentNewTitle').value.trim();
    const next=document.getElementById('developmentNewAction').value.trim();
    if(!title)return;
    if(ideas.length>=200){status.textContent='Export your checklist before adding more ideas.';return;}
    ideas.push({id:'idea-'+Date.now()+'-'+ideas.length,title,area:'New idea',next:next||'Review idea',custom:true});
    try{localStorage.setItem(ideasKey,JSON.stringify(ideas));status.textContent='Idea saved on this device.';}catch(_){status.textContent='Unable to save idea on this device.';}
    event.target.reset();render();
  });
  filter.addEventListener('change',render);
  document.getElementById('developmentExport')?.addEventListener('click',()=>{
    const rows=['Bubba Hub development checklist','Exported: '+new Date().toLocaleDateString('en-GB'),''];
    allTasks().forEach(t=>rows.push(t.title+' | '+labels[state(t.id)]+' | '+t.next+' | '+t.url));
    const blob=new Blob([rows.join('\n')],{type:'text/plain;charset=utf-8'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='bubba-hub-development-checklist.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  render();
})();
