(async function(){
const rows=document.getElementById('listingRows'),search=document.getElementById('listingSearch'),filter=document.getElementById('listingStatus'),message=document.getElementById('listingMessage'),retry=document.getElementById('retryListings'),count=document.getElementById('listingCount');
const escape=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let all=[],csrf="";
function render(){const q=search.value.trim().toLowerCase(),status=filter.value;const list=all.filter(a=>(!status||(a.status==='publish'?'published':a.status)===status)&&[a.title,a.organisation_name,a.town,a.category,a.region].join(' ').toLowerCase().includes(q));count.textContent=list.length+' of '+all.length+' listings';rows.innerHTML=list.map(a=>'<tr><td><strong>'+escape(a.title)+'</strong><br><small>'+escape(a.organisation_name)+'</small></td><td>'+escape(a.town||'—')+'</td><td>'+escape(a.session_summary||'—')+'</td><td>'+escape(a.status)+'</td><td><a class="button button-soft" href="/admin/admin-activities-add-listing.html?id='+encodeURIComponent(a.id)+'">Edit</a> <button type="button" class="button button-soft" data-assign="'+escape(a.id)+'">Assign to leader</button> <button type="button" class="button button-soft" data-delete="'+escape(a.id)+'">Delete</button></td></tr>').join('')||'<tr><td colspan="5">No listings found.</td></tr>';}
async function load(){message.textContent='';retry.hidden=true;try{const r=await fetch('/api/admin-activities.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(r.status===401){location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname));return;}const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Could not load listings.');csrf=d.csrf||"";all=Array.isArray(d.data)?d.data:[];render();}catch(e){count.textContent='Listings unavailable';message.textContent=e.message;retry.hidden=false;}}
const dialog=document.createElement('dialog');
dialog.setAttribute('aria-labelledby','assignLeaderTitle');
dialog.style.cssText='width:min(480px,calc(100% - 32px));box-sizing:border-box;border:1px solid #d8e6db;border-radius:22px;padding:24px;color:#144400';
dialog.innerHTML='<form method="dialog"><h2 id="assignLeaderTitle">Assign to leader</h2><p id="assignListingTitle"></p><label for="assignLeaderSelect">Leader organisation</label><select id="assignLeaderSelect" required style="width:100%;min-height:46px;margin:12px 0"></select><p id="assignLeaderError" role="alert"></p><button type="button" class="button button-primary" id="confirmLeader">Assign listing</button> <button class="button button-soft" value="cancel">Cancel</button></form>';
document.body.append(dialog);
let assigningId=0;
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
   if(!confirm('Delete “'+listing.title+'” from the directory? It will be archived; its sessions and venue data will be kept.'))return;
   await action({action:'delete',id});await load();message.textContent='Listing deleted (archived).';
  }else{
   const r=await fetch('/api/admin-activities.php?action=leaders',{credentials:'same-origin',cache:'no-store'}),d=await r.json();
   if(!r.ok||!d.ok)throw Error(d.error||'Could not load leaders.');
   if(!d.data?.length)throw Error('No active registered leaders with an organisation are available.');
   csrf=d.csrf;assigningId=id;
   document.getElementById('assignListingTitle').textContent='Choose who will manage “'+listing.title+'”. Its organiser storefront will change.';
   document.getElementById('assignLeaderSelect').innerHTML='<option value="">Choose a leader</option>'+d.data.map(o=>'<option value="'+escape(o.id)+'">'+escape(o.organisation_name+' — '+o.email)+'</option>').join('');
   document.getElementById('assignLeaderError').textContent='';dialog.showModal();
  }
 }catch(err){message.textContent=err.message;}finally{button.disabled=false;}
});
document.getElementById('confirmLeader').addEventListener('click',async e=>{
 const select=document.getElementById('assignLeaderSelect');if(!select.reportValidity())return;
 const button=e.currentTarget;button.disabled=true;
 try{await action({action:'assign_leader',id:assigningId,organiser_id:Number(select.value)});dialog.close();await load();message.textContent='Listing assigned to leader.';}
 catch(err){document.getElementById('assignLeaderError').textContent=err.message;}finally{button.disabled=false;}
});
search.addEventListener('input',render);filter.addEventListener('change',render);retry.addEventListener('click',load);
document.querySelector('[data-admin-logout]').addEventListener('click',async()=>{await fetch('/admin-auth.php?action=logout',{method:'POST',credentials:'same-origin'});location.href='/admin-login.html';});
await load();
})();