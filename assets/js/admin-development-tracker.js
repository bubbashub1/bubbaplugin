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
,
    {id:"turnstile",title:"Cloudflare Turnstile registration protection",area:"Security",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review staging verification and test family and leader registration."},
    {id:"user-actions",title:"Admin edit, deactivate and delete actions",area:"Administration",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Test protected actions and account-type display."},
    {id:"approval",title:"Admin approval centre",area:"Administration",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review approval queues and permission checks."},
    {id:"claim",title:"Claim a listing",area:"Class leaders",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Test organiser claim requests and admin approval."},
    {id:"duplicates",title:"Duplicate listing detection",area:"Directory",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Test warnings without deleting legitimate listings."},
    {id:"listing-health",title:"Listing health and bulk timetable updates",area:"Directory",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review missing data and termly timetable update flows."},
    {id:"franchise",title:"Franchise and organiser linking",area:"Class leaders",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review leader requests and admin approvals."},
    {id:"venue-hub",title:"Venue management hub",area:"Directory",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review shared venues, facilities and multi-venue sessions."},
    {id:"planner",title:"My Hub and Planner Pro",area:"Families",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Check database-backed calendars, sharing and saved plans."},
    {id:"newsletter",title:"Personalised newsletter builder",area:"Communications",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Plan SMTP-based newsletter builder and family preferences."},
    {id:"business",title:"Business directory",area:"Future features",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review the business directory foundation."},
    {id:"app",title:"Hybrid mobile app",area:"Future features",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review Android app roadmap after core website stability."},
    {id:"guides",title:"Family guides and accessibility",area:"Future features",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review local guides, accessibility and family trails."},
    {id:"holidays",title:"School holidays and weekend finder",area:"Future features",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review school holiday hub, rainy day and low-cost finders."},
    {id:"community",title:"Reviews, rewards and community features",area:"Future features",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Review parent reviews, noticeboard and local offers."},
    {id:"expo",title:"Bubba & Me exhibitor registrations",area:"Events",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Test exhibitor form and early leader notifications."},
    {id:"analytics",title:"Website analytics and SEO",area:"Operations",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Check Google Analytics without duplicate tracking."},
    {id:"importer",title:"CSV import and master data tidy-up",area:"Data & imports",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Test import previews, errors and data mapping."},
    {id:"leader-portal",title:"Leader portal actions and messages",area:"Class leaders",url:'https://github.com/bubbashub1/bubbaplugin/pulls?q=is%3Apr',next:"Check profile, classes, messages, bookings and specialisms."},
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
  const prioritiesKey='bh-admin-development-priorities-v1';
  let priorities={};
  try {const p=JSON.parse(localStorage.getItem(prioritiesKey)||'{}');if(p&&typeof p==='object'&&!Array.isArray(p))priorities=p;}catch(_){}
  const priorityFilter=document.getElementById('developmentPriorityFilter');
  function priority(id){return ['high','medium','low'].includes(priorities[id])?priorities[id]:'medium';}
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
    const visible=allTasks().filter(t=>(selected==='all'||(selected==='next'&&state(t.id)!=='done'&&state(t.id)!=='testing')||state(t.id)===selected)&&(!priorityFilter||priorityFilter.value==='all'||priority(t.id)===priorityFilter.value));
    if(!visible.length){const tr=document.createElement('tr');const td=cell(tr,'No tasks in this view.');td.colSpan=6;list.append(tr);}
    visible.forEach(t=>{
      const tr=document.createElement('tr');
      const title=cell(tr,t.title);
      if(t.custom){const remove=document.createElement('button');remove.type='button';remove.textContent='Remove';remove.className='button button-soft';remove.style.marginLeft='8px';remove.addEventListener('click',()=>{if(!confirm('Remove this idea from your local checklist?'))return;ideas=ideas.filter(x=>x.id!==t.id);try{localStorage.setItem(ideasKey,JSON.stringify(ideas));}catch(_){}delete saved[t.id];persist();render();});title.append(remove);}
      cell(tr,t.area||'New idea');
      const prCell=cell(tr,'');const prSelect=document.createElement('select');prSelect.setAttribute('aria-label','Priority for '+t.title);
      ['high','medium','low'].forEach(v=>{const option=document.createElement('option');option.value=v;option.textContent=v[0].toUpperCase()+v.slice(1);prSelect.append(option);});
      prSelect.value=priority(t.id);prSelect.addEventListener('change',()=>{priorities[t.id]=prSelect.value;try{localStorage.setItem(prioritiesKey,JSON.stringify(priorities));status.textContent='Priority saved on this device.';}catch(_){status.textContent='Priority could not be saved.';}render();});prCell.append(prSelect);
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
  priorityFilter?.addEventListener('change',render);
  document.getElementById('developmentExport')?.addEventListener('click',()=>{
    const rows=['Bubba Hub development checklist','Exported: '+new Date().toLocaleDateString('en-GB'),''];
    allTasks().forEach(t=>rows.push(t.title+' | '+priority(t.id)+' priority | '+labels[state(t.id)]+' | '+t.next+' | '+t.url));
    const blob=new Blob([rows.join('\n')],{type:'text/plain;charset=utf-8'});
    const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='bubba-hub-development-checklist.txt';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  });
  render();
})();
