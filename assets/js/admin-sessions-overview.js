(() => {
'use strict';
const summary=document.getElementById('sessionSummary'),results=document.getElementById('sessionResults'),search=document.getElementById('sessionSearch'),filter=document.getElementById('sessionFilter');
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
let activities=[];
const render=()=>{
 const term=search.value.trim().toLowerCase(),mode=filter.value;
 const visible=activities.filter(item=>{const has=Boolean(String(item.session_summary||'').trim());if(mode==='has'&&!has)return false;if(mode==='none'&&has)return false;return !term||[item.title,item.organisation_name,item.town,item.region,item.category,item.session_summary].some(value=>String(value||'').toLowerCase().includes(term));});
 const withSessions=activities.filter(item=>String(item.session_summary||'').trim()).length;
 summary.textContent=visible.length+' of '+activities.length+' activities shown · '+withSessions+' with timetable entries · '+(activities.length-withSessions)+' without sessions listed.';
 results.replaceChildren();
 if(!visible.length){const empty=document.createElement('p');empty.textContent='No activities match this filter.';results.append(empty);return;}
 visible.forEach(item=>{const has=Boolean(String(item.session_summary||'').trim()),card=document.createElement('article');card.style.cssText='padding:16px;border:1px solid #d8e6db;border-radius:14px;background:#fff';
 card.innerHTML='<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start"><div style="min-width:220px;flex:1"><strong>'+escapeHtml(item.title||'Untitled activity')+'</strong><p style="margin:5px 0;color:#52665a">'+escapeHtml(item.organisation_name||'Organiser not set')+' · '+escapeHtml([item.town,item.region].filter(Boolean).join(', ')||'Location not set')+'</p><p style="margin:8px 0"><span style="display:inline-block;padding:4px 9px;border-radius:20px;background:'+(has?'#e5f2e8':'#f2f2ef')+'">'+(has?'Sessions listed':'No sessions listed')+'</span></p><p style="margin:0">'+escapeHtml(item.session_summary||'No recurring session details are currently attached to this activity.')+'</p></div><a class="button button-soft" href="/admin/admin-activities-add-listing.html?id='+encodeURIComponent(item.id)+'">Open timetable in listing editor →</a></div>';results.append(card);});
};
search.addEventListener('input',render);filter.addEventListener('change',render);
fetch('/api/admin-activities.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}}).then(async response=>{if(response.status===401){location.href='/admin/';throw new Error('Admin login required.');}const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Could not load activity schedules.');return data;}).then(data=>{activities=Array.isArray(data.data)?data.data:[];render();}).catch(error=>{summary.textContent=error.message||'Could not load timetable overview.';results.replaceChildren();});
})();