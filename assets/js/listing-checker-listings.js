(()=>{'use strict';
const api='/api/admin-listing-checker-listings.php';
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let page=1,pages=1,searchTimer=null;
function statusClass(s){return s.toLowerCase().replace(/[^a-z]+/g,'-');}
function showFindings(row){
 const items=row.findings||[];
 $('checkedListingDialogTitle').textContent=row.title||('Listing '+row.activity_id);
 $('checkedListingDialogMeta').textContent='Last checked: '+(row.last_checked_at||'Not checked yet')+' · Next check: '+(row.next_check_at||'Not scheduled');
 $('checkedListingDialogBody').innerHTML=items.length?items.map(f=>{
  const detail=f.details||{}, detailText=Object.entries(detail).filter(([k])=>k!=='checked_columns').map(([k,v])=>k.replace(/_/g,' ')+': '+(typeof v==='object'?JSON.stringify(v):String(v))).join(' · ');
  return '<article class="lcl-finding"><div><strong>'+esc(f.type.replace(/_/g,' '))+'</strong><span class="lcl-review '+statusClass(f.status)+'">'+esc(f.status.replace(/_/g,' '))+'</span></div><p>'+esc(detailText||'No additional details recorded.')+'</p><small>Found '+esc(f.checked_at||'')+(f.confidence!==null?' · Confidence '+esc(f.confidence)+'%':'')+'</small></article>';
 }).join(''):'<p class="muted">No outstanding findings recorded for this listing. It has been checked successfully.</p>';
 $('checkedListingDialog').showModal();
}
async function load(){
 const q=new URLSearchParams({page:String(page),per_page:'50',q:$('checkedListingSearch').value.trim(),status:$('checkedListingStatus').value});
 $('checkedListingRows').innerHTML='<p class="muted">Loading listings…</p>';
 try{
  const r=await fetch(api+'?'+q.toString(),{credentials:'same-origin',cache:'no-store'});
  const d=await r.json();
  if(r.status===401){location.href='/admin-login.html?next='+encodeURIComponent(location.pathname);return;}
  if(!r.ok||!d.ok)throw Error(d.error||'Could not load checked listings.');
  pages=d.pages;page=d.page;
  $('checkedListingCount').textContent=d.total+' listings';
  $('checkedListingRows').innerHTML=d.data.length?'<div class="lcl-table-wrap"><table class="lcl-table"><thead><tr><th>Listing</th><th>Status</th><th>Last checked</th><th>Next check</th><th>Issues</th><th></th></tr></thead><tbody>'+d.data.map(row=>'<tr><td><strong>'+esc(row.title||'Untitled listing')+'</strong><small>ID '+esc(row.activity_id)+'</small></td><td><span class="lcl-status '+statusClass(row.display_status)+'">'+esc(row.display_status)+'</span></td><td>'+esc(row.last_checked_at||'—')+'</td><td>'+esc(row.next_check_at||'—')+'</td><td>'+esc(row.issue_count)+'</td><td><button class="button button-soft lcl-details" type="button" data-row="'+esc(row.activity_id)+'">View details</button></td></tr>').join('')+'</tbody></table></div>':'<p class="muted">No listings match these filters yet. The worker adds listings to its queue when it runs.</p>';
 window.__bhListingRows=Object.fromEntries(d.data.map(x=>[String(x.activity_id),x]));
 $('checkedListingPage').textContent='Page '+page+' of '+pages;
 $('checkedListingPrev').disabled=page<=1;$('checkedListingNext').disabled=page>=pages;
 }catch(e){$('checkedListingRows').innerHTML='<p class="status error">'+esc(e.message)+'</p>';}
}
$('checkedListingSearch').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{page=1;load();},250);});
$('checkedListingStatus').addEventListener('change',()=>{page=1;load();});
$('checkedListingPrev').addEventListener('click',()=>{if(page>1){page--;load();}});
$('checkedListingNext').addEventListener('click',()=>{if(page<pages){page++;load();}});
$('checkedListingRefresh').addEventListener('click',load);
$('checkedListingRows').addEventListener('click',e=>{const b=e.target.closest('[data-row]');if(!b)return;const row=window.__bhListingRows?.[b.dataset.row];if(row)showFindings(row);});
$('checkedListingDialogClose').addEventListener('click',()=>$('checkedListingDialog').close());
$('checkedListingDialog').addEventListener('click',e=>{if(e.target===$('checkedListingDialog'))$('checkedListingDialog').close();});
load();
})();