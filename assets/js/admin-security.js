(function(){
'use strict';
const automated=document.getElementById('bhSecurityAutomated');
const manual=document.getElementById('bhSecurityManual');
const errorBox=document.getElementById('bhSecurityError');
const refresh=document.getElementById('bhSecurityRefresh');
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const labels={pass:'Passed',fail:'Failed',warning:'Needs attention',review:'Needs review'};
function render(items,target){
 target.innerHTML=items.map(item=>'<article class="bh-security-check"><h3>'+escapeHtml(item.title)+'</h3><p>'+escapeHtml(item.detail)+'</p><span class="bh-security-status '+escapeHtml(item.status)+'">'+escapeHtml(labels[item.status]||'Unknown')+'</span></article>').join('');
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
  render(data.automated||[],automated);render(data.manual||[],manual);
  const checks=data.automated||[];
  const passed=checks.filter(x=>x.status==='pass').length;
  const failed=checks.filter(x=>x.status==='fail').length;
  const warnings=checks.filter(x=>x.status==='warning').length;
  document.getElementById('bhSecurityScore').textContent=passed+' / '+checks.length+' passed';
  document.getElementById('bhSecuritySummary').textContent=failed?failed+' failed · '+warnings+' need attention':warnings?warnings+' need attention · manual reviews remain open':'Automated checks passed · manual reviews remain open';
  document.getElementById('bhSecurityUpdated').textContent='Checked '+new Date(data.generated_at).toLocaleString('en-GB');
 }catch(err){
  automated.innerHTML='<p>Unable to load security checks.</p>';
  errorBox.textContent=err.message||'Security checks are temporarily unavailable.';errorBox.hidden=false;
  document.getElementById('bhSecurityScore').textContent='Unavailable';
  document.getElementById('bhSecuritySummary').textContent='Please retry after confirming admin access.';
 }finally{refresh.disabled=false;refresh.textContent='Run checks again';}
}
refresh.addEventListener('click',run);
document.addEventListener('click',async event=>{
 const button=event.target.closest('[data-admin-logout]');if(!button)return;
 button.disabled=true;
 try{const response=await fetch('/admin-auth.php?action=logout',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});if(!response.ok)throw new Error();location.href='/admin-login.html';}
 catch(e){button.disabled=false;window.alert('We could not sign you out. Please try again.');}
});
run();
})();