(function(){
'use strict';
const automated=document.getElementById('bhSecurityAutomated');
const manual=document.getElementById('bhSecurityManual');
const errorBox=document.getElementById('bhSecurityError');
const refresh=document.getElementById('bhSecurityRefresh');
let csrfToken='';
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const statusLabels={pass:'Passed',fail:'Failed',warning:'Needs attention',review:'Needs review'};
const reviewLabels={open:'Open',in_progress:'In progress',verified:'Verified',accepted_risk:'Accepted risk'};
const severityLabels={critical:'Critical',high:'High',medium:'Medium',low:'Low'};
function renderAutomated(items){
 automated.innerHTML=items.map(item=>'<article class="bh-security-check"><h3>'+escapeHtml(item.title)+'</h3><p>'+escapeHtml(item.detail)+'</p><span class="bh-security-status '+escapeHtml(item.status)+'">'+escapeHtml(statusLabels[item.status]||'Unknown')+'</span></article>').join('');
}
function renderReviews(items){
 manual.innerHTML=items.map(item=>'<article class="bh-security-review" data-review-key="'+escapeHtml(item.check_key)+'">'+
 '<div class="bh-security-review-heading"><div><h3>'+escapeHtml(item.title)+'</h3><p>'+escapeHtml(item.description)+'</p></div>'+
 '<span class="bh-security-severity '+escapeHtml(item.severity)+'">'+escapeHtml(severityLabels[item.severity]||item.severity)+'</span></div>'+
 '<div class="bh-security-review-fields"><label>Review status<select data-review-status>'+
 Object.entries(reviewLabels).map(([value,label])=>'<option value="'+value+'"'+(item.status===value?' selected':'')+'>'+label+'</option>').join('')+
 '</select></label><label>Review notes<textarea data-review-notes rows="2" maxlength="4000" placeholder="What was checked? Evidence, findings or follow-up…">'+escapeHtml(item.notes||'')+'</textarea></label></div>'+
 '<div class="bh-security-review-footer"><span>'+ (item.reviewed_at?'Last verified '+escapeHtml(new Date(item.reviewed_at.replace(' ','T')+'Z').toLocaleString('en-GB')):item.updated_at?'Last updated '+escapeHtml(new Date(item.updated_at.replace(' ','T')+'Z').toLocaleString('en-GB')):'Not yet reviewed')+
 (item.reviewed_by?' · '+escapeHtml(item.reviewed_by):'')+'</span><button class="button button-soft" type="button" data-save-review>Save review</button></div></article>').join('');
}
async function loadReviews(){
 const response=await fetch('/api/admin-security-reviews.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
 const data=await response.json();
 if(response.status===401){location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname));return;}
 if(!response.ok||data.ok!==true)throw new Error(data.message||'The review register is unavailable. Apply migration 006, then retry.');
 csrfToken=data.csrf_token;renderReviews(data.reviews||[]);
}
async function run(){
 refresh.disabled=true;refresh.textContent='Checking…';errorBox.hidden=true;
 automated.innerHTML='<p>Running read-only checks…</p>';
 try{
  const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(auth.status===401){location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname));return;}
  if(!auth.ok)throw new Error('Could not verify admin access.');
  const response=await fetch('/api/admin-security-check.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(response.status===401){location.replace('/admin-login.html?next='+encodeURIComponent(location.pathname));return;}
  if(!response.ok||data.ok!==true)throw new Error('Security checks could not be completed. Please try again.');
  renderAutomated(data.automated||[]);
  await loadReviews();
  const checks=data.automated||[];
  const passed=checks.filter(x=>x.status==='pass').length;
  const failed=checks.filter(x=>x.status==='fail').length;
  const warnings=checks.filter(x=>x.status==='warning').length;
  document.getElementById('bhSecurityScore').textContent=passed+' / '+checks.length+' passed';
  document.getElementById('bhSecuritySummary').textContent=failed?failed+' failed · '+warnings+' need attention':'Automated checks passed · review register shown below';
  document.getElementById('bhSecurityUpdated').textContent='Checked '+new Date(data.generated_at).toLocaleString('en-GB');
 }catch(err){
  automated.innerHTML='<p>Unable to load security checks.</p>';
  errorBox.textContent=err.message||'Security checks are temporarily unavailable.';errorBox.hidden=false;
  document.getElementById('bhSecurityScore').textContent='Unavailable';
  document.getElementById('bhSecuritySummary').textContent='Please retry after confirming admin access and database setup.';
 }finally{refresh.disabled=false;refresh.textContent='Run checks again';}
}
manual.addEventListener('click',async event=>{
 const button=event.target.closest('[data-save-review]');if(!button)return;
 const card=button.closest('[data-review-key]');if(!card)return;
 button.disabled=true;button.textContent='Saving…';
 try{
  const response=await fetch('/api/admin-security-reviews.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken,Accept:'application/json'},body:JSON.stringify({check_key:card.dataset.reviewKey,status:card.querySelector('[data-review-status]').value,notes:card.querySelector('[data-review-notes]').value})});
  const data=await response.json();
  if(response.status===401){location.replace('/admin-login.html');return;}
  if(!response.ok||!data.ok)throw new Error(data.message||data.error||'Could not save review.');
  button.textContent='Saved';await loadReviews();
 }catch(error){button.textContent='Save failed';window.alert(error.message||'Could not save review.');}
 finally{button.disabled=false;if(button.textContent==='Saving…')button.textContent='Save review';}
});
refresh.addEventListener('click',run);
document.addEventListener('click',async event=>{
 const button=event.target.closest('[data-admin-logout]');if(!button)return;
 button.disabled=true;
 try{const response=await fetch('/admin-auth.php?action=logout',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!response.ok)throw new Error();location.href='/admin-login.html';}
 catch(e){button.disabled=false;window.alert('We could not sign you out. Please try again.');}
});
run();
})();