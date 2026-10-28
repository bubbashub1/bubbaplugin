(async function(){
const rows=document.getElementById('listingRows'),search=document.getElementById('listingSearch'),filter=document.getElementById('listingStatus'),message=document.getElementById('listingMessage'),retry=document.getElementById('retryListings'),count=document.getElementById('listingCount');
const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let all=[],csrf=""; const selected=new Set(); const selectedCount=document.getElementById("selectedCount"); const selectAll=document.getElementById("selectAllListings"); const bulkButtons=["bulkEdit","bulkAssign","bulkDelete","bulkPermanentDelete"].map(id=>document.getElementById(id)); function syncSelection(){selectedCount.textContent=selected.size+" selected";bulkButtons.forEach(b=>b.disabled=!selected.size);const visible=[...rows.querySelectorAll("[data-select-id]")];selectAll.checked=visible.length>0&&visible.every(x=>x.checked);selectAll.indeterminate=visible.some(x=>x.checked)&&!selectAll.checked;}
function render(){
 const q=search.value.trim().toLowerCase(),status=filter.value;
 const list=all.filter(a=>(!status||(a.status==='publish'?'published':a.status)===status)&&[a.title,a.organisation_name,a.town,a.category,a.region].join(' ').toLowerCase().includes(q));
 count.textContent=list.length+' of '+all.length+' listings';
 rows.innerHTML=list.map(a=>{
  const rawStatus=(a.status==='publish'?'published':a.status)||'unknown';
  const statusLabel=rawStatus.charAt(0).toUpperCase()+rawStatus.slice(1);
  const statusClass=['published','draft','pending'].includes(rawStatus)?rawStatus:'other';
  const summary=String(a.session_summary||'').trim();
  const sessions=summary?summary.split(',').map(x=>x.trim()).filter(Boolean):[];
  const sessionHtml=sessions.length>2?'<details class="listing-sessions"><summary>'+sessions.length+' sessions</summary><div>'+escape(summary)+'</div></details>':escape(summary||'—');
  return '<tr><td data-label="Select"><input type="checkbox" data-select-id="'+escape(a.id)+'" '+(selected.has(Number(a.id))?'checked':'')+' aria-label="Select '+escape(a.title)+'"></td><td data-label="Activity"><strong class="listing-title">'+escape(a.title)+'</strong>'+(a.organisation_name?'<small class="listing-organiser">'+escape(a.organisation_name)+'</small>':'')+'</td><td data-label="Town">'+escape(a.town||'—')+'</td><td data-label="Sessions">'+sessionHtml+'</td><td data-label="Status"><span class="listing-status listing-status--'+statusClass+'">'+escape(statusLabel)+'</span></td><td data-label="Actions"><div class="listing-actions"><a class="listing-action listing-action--edit" href="/admin/admin-activities-add-listing.html?id='+encodeURIComponent(a.id)+'">Edit</a><button type="button" class="listing-action" data-assign="'+escape(a.id)+'">Assign</button><details class="listing-more"><summary aria-label="More actions for '+escape(a.title)+'">⋯</summary><button type="button" data-delete="'+escape(a.id)+'">Close listing</button></details></div></td></tr>';
 }).join('')||'<tr><td colspan="6" class="listing-empty">No listings found. Try changing your search or status filter.</td></tr>';syncSelection();
}
async function load(){message.textContent='';retry.hidden=true;try{const r=await fetch('/api/admin-activities.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(r.status===401){location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname));return;}const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listings.');csrf=d.csrf||"";all=Array.isArray(d.data)?d.data:[];for(const id of [...selected])if(!all.some(a=>Number(a.id)===id))selected.delete(id);render();}catch(e){count.textContent='Listings unavailable';message.textContent=e.message;retry.hidden=false;}}
const dialog=document.createElement('dialog');
dialog.setAttribute('aria-labelledby','assignLeaderTitle');
dialog.style.cssText='width:min(480px,calc(100% - 32px));box-sizing:border-box;border:1px solid #d8e6db;border-radius:22px;padding:24px;color:#144400';
dialog.innerHTML='<form method="dialog"><h2 id="assignLeaderTitle">Assign to leader</h2><p id="assignListingTitle"></p><label for="assignLeaderSelect">Leader organisation</label><select id="assignLeaderSelect" required style="width:100%;min-height:46px;margin:12px 0"></select><p id="assignLeaderError" role="alert"></p><button type="button" class="button button-primary" id="confirmLeader">Assign listing</button> <button class="button button-soft" value="cancel">Cancel</button></form>';
document.body.append(dialog);
let assigningId=0;let assigningIds=[];
async function action(data){
 const r=await fetch('/api/admin-activities.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({...data,csrf})});
 const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not update listing.');
}
rows.addEventListener('click',async e=>{
 const button=e.target.closest('[data-delete],[data-assign]');if(!button)return;
 const id=Number(button.dataset.delete||button.dataset.assign),listing=all.find(a=>Number(a.id)===id);if(!listing)return;
 button.disabled=true;message.textContent='';
 try{
  if(button.hasAttribute('data-delete')){
   if(!confirm('Close “'+listing.title+'”? It will be hidden from the directory; sessions and venues will be kept.'))return;
   await action({action:'delete',id});await load();message.textContent='Listing closed.';
  }else{
   const r=await fetch('/api/admin-activities.php?action=leaders',{credentials:'same-origin',cache:'no-store'}),d=await r.json();
   if(!r.ok||!d.ok)throw Error(d.error||'Could not load leaders.');
   if(!d.data?.length)throw Error('No active registered leaders with an organisation are available.');
   csrf=d.csrf;assigningId=id;assigningIds=[id];
   document.getElementById('assignListingTitle').textContent='Choose who will manage “'+listing.title+'”. Its organiser storefront will change.';
   document.getElementById('assignLeaderSelect').innerHTML='<option value="">Choose a leader</option>'+d.data.map(o=>'<option value="'+escape(o.id)+'">'+escape(o.organisation_name+' — '+o.email)+'</option>').join('');
   document.getElementById('assignLeaderError').textContent='';dialog.showModal();
  }
 }catch(err){message.textContent=err.message;}finally{button.disabled=false;}
});
document.getElementById('confirmLeader').addEventListener('click',async e=>{
 const select=document.getElementById('assignLeaderSelect');if(!select.reportValidity())return;
 const button=e.currentTarget;button.disabled=true;
 try{const ids=assigningIds.length?assigningIds:[assigningId];let done=0;for(const id of ids){await action({action:'assign_leader',id,organiser_id:Number(select.value)});done++;}selected.clear();dialog.close();await load();message.textContent=done+' listing(s) assigned to leader.';}
 catch(err){document.getElementById('assignLeaderError').textContent=err.message;}finally{button.disabled=false;}
});
rows.addEventListener('change',e=>{const el=e.target.closest('[data-select-id]');if(!el)return;const id=Number(el.dataset.selectId);if(el.checked)selected.add(id);else selected.delete(id);syncSelection();});
selectAll.addEventListener('change',()=>{rows.querySelectorAll('[data-select-id]').forEach(el=>{el.checked=selectAll.checked;const id=Number(el.dataset.selectId);if(el.checked)selected.add(id);else selected.delete(id);});syncSelection();});
document.getElementById('bulkEdit').addEventListener('click',()=>{const ids=[...selected];if(ids.length>10){message.textContent='Please edit at most 10 listings at once to avoid opening too many tabs.';return;}ids.forEach(id=>window.open('/admin/admin-activities-add-listing.html?id='+encodeURIComponent(id),'_blank','noopener'));message.textContent='Opened '+ids.length+' editors in separate tabs. Allow pop-ups if your browser blocked them.';});
document.getElementById('bulkAssign').addEventListener('click',async()=>{try{const r=await fetch('/api/admin-activities.php?action=leaders',{credentials:'same-origin'}),d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load leaders');if(!d.data?.length)throw Error('No active leaders available');csrf=d.csrf;assigningIds=[...selected];document.getElementById('assignListingTitle').textContent='Assign '+assigningIds.length+' selected listings to one leader. Their organiser storefronts will change.';document.getElementById('assignLeaderSelect').innerHTML='<option value="">Choose a leader</option>'+d.data.map(o=>'<option value="'+escape(o.id)+'">'+escape(o.organisation_name+' — '+o.email)+'</option>').join('');document.getElementById('assignLeaderError').textContent='';dialog.showModal();}catch(err){message.textContent=err.message;}});
document.getElementById('bulkDelete').addEventListener('click',async e=>{const ids=[...selected];if(!confirm('Close '+ids.length+' selected listings? They will disappear from the directory; session and venue records will be retained.'))return;const btn=e.currentTarget;btn.disabled=true;let done=0;try{for(const id of ids){await action({action:'delete',id});done++;selected.delete(id);}await load();message.textContent=done+' listing(s) closed.';}catch(err){await load();message.textContent=done+' closed before error: '+err.message;}finally{btn.disabled=false;syncSelection();}});
document.getElementById('bulkPermanentDelete').addEventListener('click',async e=>{const ids=[...selected];if(!ids.length)return;const phrase='DELETE '+ids.length;const typed=prompt('PERMANENT DELETION of '+ids.length+' listings and their related records cannot be undone. Type '+phrase+' to confirm:');if(typed!==phrase)return;const btn=e.currentTarget;btn.disabled=true;let done=0;try{for(const id of ids){await action({action:'permanent_delete',id});done++;selected.delete(id);}await load();message.textContent=done+' listing(s) permanently deleted.';}catch(err){await load();message.textContent=done+' permanently deleted before error: '+err.message;}finally{btn.disabled=false;syncSelection();}});
search.addEventListener('input',render);filter.addEventListener('change',render);retry.addEventListener('click',load);
document.querySelector('[data-admin-logout]').addEventListener('click',async()=>{await fetch('/admin-auth.php?action=logout',{method:'POST',credentials:'same-origin'});location.href='/admin-login.html';});
await load();
})();