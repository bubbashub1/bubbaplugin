(()=>{'use strict';
const form=document.getElementById('leaderFilters'),rows=document.getElementById('leaderDirectoryRows'),status=document.getElementById('leaderDirectoryStatus'),prev=document.getElementById('leaderDirectoryPrev'),next=document.getElementById('leaderDirectoryNext');
if(!form||!rows)return;
let page=1,total=0,size=25;
async function load(){
 status.textContent='Loading leaders…';rows.replaceChildren();prev.disabled=true;next.disabled=true;
 const params=new URLSearchParams(new FormData(form));params.set('role','leader');params.set('page',String(page));
 try{
  const response=await fetch('/api/admin-user-directory.php?'+params.toString(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(response.status===401){location.href='/admin-login.html?next='+encodeURIComponent(location.pathname);return;}
  if(!(response.headers.get('content-type')||'').includes('application/json'))throw Error('Unexpected server response');
  const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Unable to load leaders');
  total=Number(data.total)||0;size=Number(data.page_size)||25;
  for(const user of data.users||[]){
   const tr=document.createElement('tr');
   for(const value of [user.id,user.email,user.status,user.email_verified?'Verified':'Not verified']){
    const td=document.createElement('td');td.textContent=String(value??'');td.style.padding='10px 8px';td.style.borderBottom='1px solid #d8e6db';tr.append(td);
   }
   const cell=document.createElement('td');cell.style.cssText='display:flex;gap:6px;flex-wrap:wrap;padding:8px';
   const view=document.createElement('button');view.type='button';view.className='button button-soft';view.textContent='View listings';view.dataset.leaderId=String(user.id);cell.append(view);
   for(const [action,label] of [['edit','Edit'],['deactivate','Deactivate'],['promote','Promote to Pro']]){
    const button=document.createElement('button');button.type='button';button.className='button button-soft';button.textContent=label;button.dataset.action=action;button.dataset.id=String(user.id);button.dataset.email=user.email;button.dataset.status=user.status;cell.append(button);
   }
   tr.append(cell);rows.append(tr);
  }
  status.textContent=total+' leader account(s) found · Page '+page+(data.users.length?'':' · No results');
  prev.disabled=page<=1;next.disabled=page*size>=total;
 }catch(error){status.textContent='Could not load leader accounts: '+error.message;}
}
form.addEventListener('submit',event=>{event.preventDefault();page=1;load()});
prev.addEventListener('click',()=>{if(page>1){page--;load()}});
next.addEventListener('click',()=>{if(page*size<total){page++;load()}});
const panel=document.getElementById('leaderDetailsPanel'),detailStatus=document.getElementById('leaderDetailsStatus'),detailContent=document.getElementById('leaderDetailsContent');
rows.closest('table')?.querySelector('thead tr')?.append(Object.assign(document.createElement('th'),{textContent:'Actions'}));
async function runAction(button){
 const actionStatus=document.getElementById('leaderActionStatus');
 const report=message=>{if(actionStatus){actionStatus.textContent=message;actionStatus.hidden=false;}else status.textContent=message;};
 const action=button.dataset.action,id=Number(button.dataset.id),email=button.dataset.email;
 let requestedStatus;
 if(action==='edit'){requestedStatus=prompt('Account status: active, inactive, or suspended',button.dataset.status);if(requestedStatus===null)return;requestedStatus=requestedStatus.trim().toLowerCase();if(!['active','inactive','suspended'].includes(requestedStatus)){alert('Invalid status');return;}}
 if(!confirm('Confirm '+button.textContent+' for '+email+'?'))return;
 report('Updating '+email+'…');
 button.disabled=true;
 try{
  const response=await fetch('/api/admin-leader-actions.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,action,status:requestedStatus})});
  const raw=await response.text();let data;try{data=JSON.parse(raw);}catch(_){throw Error('Server returned HTTP '+response.status+' instead of JSON. Check deployment and PHP logs.');}
  if(!response.ok||!data.ok)throw Error((data.error||'Action failed')+' (HTTP '+response.status+')');
  await load();
  report('✓ '+email+' updated successfully. '+(data.details?.new_status?'New status: '+data.details.new_status:'Action: '+action)+'.');
 }catch(error){report('Unable to update '+email+': '+error.message);button.disabled=false;}
}
rows.addEventListener('click',async event=>{
 const actionButton=event.target.closest('button[data-action]');if(actionButton){await runAction(actionButton);return;}
 const button=event.target.closest('[data-leader-id]');if(!button||!panel)return;
 panel.hidden=false;detailStatus.textContent='Loading leader details…';detailContent.replaceChildren();
 try{
  const response=await fetch('/api/admin-leader-details.php?id='+encodeURIComponent(button.dataset.leaderId),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(response.status===401){location.href='/admin-login.html?next='+encodeURIComponent(location.pathname);return;}
  const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Unable to load leader');
  const orgs=data.organisers||[];detailStatus.textContent=data.leader.email+' · '+orgs.length+' linked organiser(s)';
  const controls=document.createElement('div');controls.style.cssText='display:flex;flex-wrap:wrap;gap:8px;margin:12px 0';
  for(const [action,label] of [['edit','Edit status'],['deactivate','Delete / deactivate'],['restore','Reactivate'],['promote','Promote to Pro'],['revoke_promotion','Remove complimentary Pro']]){
   const btn=document.createElement('button');btn.type='button';btn.className='button button-soft';btn.textContent=label;
   btn.addEventListener('click',async()=>{
    let requestedStatus;
    if(action==='edit'){requestedStatus=prompt('Account status: active, inactive, or suspended',data.leader.status);if(requestedStatus===null)return;requestedStatus=requestedStatus.trim().toLowerCase();if(!['active','inactive','suspended'].includes(requestedStatus)){alert('Invalid account status');return;}}
    if(!confirm('Confirm '+label+' for '+data.leader.email+'?'))return;
    btn.disabled=true;
    try{
     const res=await fetch('/api/admin-leader-actions.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:data.leader.id,action,status:requestedStatus})});
     const result=await res.json();if(!res.ok||!result.ok)throw Error(result.error||'Action failed');
     detailStatus.textContent=label+' completed for '+data.leader.email;load();
    }catch(err){alert('Action failed: '+err.message);}finally{btn.disabled=false;}
   });controls.append(btn);
  }
  detailContent.append(controls);
  if(!orgs.length){const note=document.createElement('p');note.textContent='No linked organiser found. This account may need an organiser profile linked before it can use the leader portal.';detailContent.append(note);return;}
  for(const org of orgs){
   const section=document.createElement('section'),heading=document.createElement('h3');heading.textContent=org.name||'Organiser #'+org.id;section.append(heading);
   const link=document.createElement('a');link.href='/admin/admin-activities-listings.html';link.textContent='Manage activity listings →';section.append(link);
   const list=document.createElement('ul');for(const activity of org.activities||[]){const li=document.createElement('li');li.textContent=activity.title+' ('+activity.status+')';list.append(li);}
   if(!list.children.length){const note=document.createElement('p');note.textContent='No activities linked.';section.append(note);}else section.append(list);
   detailContent.append(section);
  }
 }catch(error){detailStatus.textContent='Could not load details: '+error.message;}
});
load();
})();