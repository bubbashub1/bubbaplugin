/* Public activity page: keep the final actions and last-updated date together. */
(()=>{const init=()=>{
 const root=document.getElementById('activityState');if(!root)return;
 let panel=null,attempts=0;
 const arrange=()=>{
  const back=root.querySelector('.listing-bottom');
  if(!back)return;
  if(!panel){
   panel=document.createElement('section');panel.className='bh-activity-bottom-panel';panel.setAttribute('aria-label','Listing actions and update information');
   const row=document.createElement('div');row.className='bh-activity-bottom-row';
   const left=document.createElement('div');left.className='bh-activity-bottom-left';
   const right=document.createElement('div');right.className='bh-activity-bottom-right';
   row.append(left,right);panel.append(row);
   const date=document.createElement('p');date.id='bh-activity-updated-near-actions';date.className='bh-activity-updated-note';date.hidden=true;panel.append(date);
   back.before(panel);
   const params=new URLSearchParams(location.search);
   const path=location.pathname.match(/^\/activity\/(?:\d+\/)?([^/]+)\/?$/);
   const id=params.get('id')||location.pathname.match(/^\/activity\/(\d+)\//)?.[1];
   const slug=params.get('slug')||path?.[1];
   if(id||slug){const url=new URL('/api/activities.php',location.origin);if(id)url.searchParams.set('id',id);else url.searchParams.set('pretty_slug',slug);
    url.searchParams.set('per_page','1');
    fetch(url,{credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(j=>{
     const raw=String(j?.data?.[0]?.updated_at||'');const m=raw.match(/^(\d{4})-(\d{2})-(\d{2})/);if(!m)return;
     const d=new Date(Date.UTC(+m[1],+m[2]-1,+m[3]));if(d.toISOString().slice(0,10)!==m[0])return;
     date.textContent='Listing updated: '+new Intl.DateTimeFormat('en-GB',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}).format(d);date.hidden=false;
    }).catch(()=>{});
   }
  }
  const left=panel.querySelector('.bh-activity-bottom-left'),right=panel.querySelector('.bh-activity-bottom-right');
  const claim=document.querySelector('.listing-claim-wrap');
  if(claim&&claim.parentElement!==left)left.append(claim);
  if(back.parentElement!==left)left.append(back);
  const feedback=document.getElementById('bh-feedback-actions');
  if(feedback&&feedback.parentElement!==right)right.append(feedback);
  if(++attempts<18&&(!feedback||!claim))setTimeout(arrange,250);
 };
 const observer=new MutationObserver(()=>{if(root.querySelector('.listing-bottom')){observer.disconnect();arrange();}});
 observer.observe(root,{childList:true,subtree:true});arrange();
};document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();})();
