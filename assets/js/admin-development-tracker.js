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
  function render(){
    list.replaceChildren();
    const selected=filter.value;
    const visible=tasks.filter(t=>selected==='all'||(selected==='next'&&state(t.id)!=='done'&&state(t.id)!=='testing')||state(t.id)===selected);
    if(!visible.length){const p=document.createElement('p');p.textContent='No tasks in this view.';list.append(p);}
    visible.forEach(t=>{
      const card=document.createElement('article');card.style.cssText='border:1px solid #d6e3d8;border-radius:14px;padding:16px;background:#fff';
      const heading=document.createElement('h3');heading.textContent=t.title;heading.style.margin='0 0 4px';
      const area=document.createElement('small');area.textContent=t.area;
      const next=document.createElement('p');next.textContent='Next: '+t.next;
      const link=document.createElement('a');link.href=t.url;link.target='_blank';link.rel='noopener noreferrer';link.textContent='Open related GitHub work';
      const label=document.createElement('label');label.textContent=' Your progress: ';
      const select=document.createElement('select');select.setAttribute('aria-label','Progress for '+t.title);
      allowed.forEach(v=>{const option=document.createElement('option');option.value=v;option.textContent=labels[v];select.append(option);});
      select.value=state(t.id);select.addEventListener('change',()=>{saved[t.id]=select.value;persist();render();});
      label.append(select);card.append(heading,area,next,link,document.createTextNode('  '),label);list.append(card);
    });
  }
  filter.addEventListener('change',render);
  document.getElementById('developmentExport')?.addEventListener('click',()=>{
    const rows=['Bubba Hub development checklist','Exported: '+new Date().toLocaleDateString('en-GB'),''];
    tasks.forEach(t=>rows.push(t.title+' | '+labels[state(t.id)]+' | '+t.next+' | '+t.url));
    const blob=new Blob([rows.join('\n')],{type:'text/plain;charset=utf-8'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='bubba-hub-development-checklist.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  render();
})();
