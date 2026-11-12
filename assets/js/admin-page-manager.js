(() => {
'use strict';
const $ = id => document.getElementById(id);
let pages = [], csrf = '', currentId = null;
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function request(method='GET', body=null, query='') {
 const options={method,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}};
 if(body){options.headers['Content-Type']='application/json';options.body=JSON.stringify(body);}
 const response=await fetch('/api/admin-pages.php'+query,options);
 const data=await response.json().catch(()=>({ok:false,error:'The server returned an invalid response.'}));
 if(!response.ok||!data.ok)throw new Error(data.error||('Request failed (HTTP '+response.status+').'));
 if(data.csrf)csrf=data.csrf;
 return data;
}
function message(text,error=false){$('pageMessage').textContent=text;$('pageMessage').classList.toggle('error',error);}
function filteredPages(){
 const q=$('pageSearch').value.trim().toLowerCase(), status=$('pageFilter').value;
 return pages.filter(p=>(status==='all'||p.status===status)&&(!q||(p.title+' '+p.slug).toLowerCase().includes(q)));
}
function render(){
 const rows=filteredPages();
 $('pageRows').innerHTML=rows.length?rows.map(p=>'<tr><td><strong>'+escapeHtml(p.title)+'</strong></td><td>/page.php?slug='+encodeURIComponent(p.slug)+'</td><td><span class="bh-page-status '+escapeHtml(p.status)+'">'+escapeHtml(p.status)+'</span></td><td>'+escapeHtml(p.updated_at||'—')+'</td><td><div class="bh-page-actions"><button type="button" data-edit="'+p.id+'">Edit</button>'+(p.status==='archived'?'<button type="button" data-restore="'+p.id+'">Restore</button>':'<button type="button" data-archive="'+p.id+'">Archive</button>')+'</div></td></tr>').join(''):'<tr><td colspan="5">No pages match this filter.</td></tr>';
}
async function load(){ $('pageRows').innerHTML='<tr><td colspan="5">Loading pages…</td></tr>';try{const d=await request();if(d.setup_required){$('pageSetup').hidden=false;$('pageRows').innerHTML='<tr><td colspan="5">Complete the one-time setup to start managing pages.</td></tr>';return;}$('pageSetup').hidden=true;pages=d.pages||[];render();}catch(e){$('pageRows').innerHTML='<tr><td colspan="5">'+escapeHtml(e.message)+'</td></tr>';}}
function openEditor(page=null){
 currentId=page?Number(page.id):null;$('pageForm').reset();delete $('pageSlug').dataset.edited;$('pageId').value=page?.id||'';$('pageTitle').value=page?.title||'';$('pageSlug').value=page?.slug||'';$('pageDescription').value=page?.description||'';$('pageContent').value=page?.content_html||'';$('pageMeta').value=page?.meta_description||'';$('editorHeading').textContent=page?'Edit page':'Add a page';$('saveDraft').textContent=page?'Save changes':'Save draft';$('pageEditor').hidden=false;message('');$('pageEditor').scrollIntoView({behavior:'smooth',block:'start'});
}
function slugify(value){return value.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,190);}
$('newPage').addEventListener('click',()=>openEditor());
$('setupPages')?.addEventListener('click',async()=>{const b=$('setupPages');b.disabled=true;b.textContent='Setting up…';try{await request('POST',{action:'setup',csrf});$('pageSetup').hidden=true;await load();}catch(e){alert(e.message);}finally{b.disabled=false;b.textContent='Set up Page Manager';}});
$('closeEditor').addEventListener('click',()=>$('pageEditor').hidden=true);
$('pageTitle').addEventListener('input',()=>{if(!$('pageId').value||!$('pageSlug').dataset.edited)$('pageSlug').value=slugify($('pageTitle').value);});
$('pageSlug').addEventListener('input',()=>{$('pageSlug').dataset.edited='1';$('pageSlug').value=slugify($('pageSlug').value);});
$('pageSearch').addEventListener('input',render);$('pageFilter').addEventListener('change',render);
$('pageRows').addEventListener('click',async e=>{
 const b=e.target.closest('button');if(!b)return;
 try{
  if(b.dataset.edit){const d=await request('GET',null,'?id='+encodeURIComponent(b.dataset.edit));openEditor(d.page);}
  if(b.dataset.archive){if(!confirm('Archive this page? It will no longer be shown as published.'))return;await request('POST',{action:'archive',id:Number(b.dataset.archive),csrf});await load();}
  if(b.dataset.restore){await request('POST',{action:'restore',id:Number(b.dataset.restore),csrf});await load();}
 }catch(err){alert(err.message);}
});
async function save(status){
 const body={action:'save',id:currentId,title:$('pageTitle').value.trim(),slug:$('pageSlug').value.trim(),description:$('pageDescription').value,content_html:$('pageContent').value,meta_description:$('pageMeta').value,status,csrf};
 message('Saving…');
 try{const d=await request('POST',body);currentId=d.page.id;$('pageId').value=currentId;$('editorHeading').textContent='Edit page';message(status==='published'?'Page published.':'Draft saved.');await load();}
 catch(e){message(e.message,true);}
}
$('pageForm').addEventListener('submit',e=>{e.preventDefault();save('draft');});
$('publishPage').addEventListener('click',()=>{if($('pageForm').reportValidity())save('published');});
$('previewPage').addEventListener('click',()=>{const slug=$('pageSlug').value.trim();if(!slug){message('Add a page URL first.',true);return;}window.open('/page.php?slug='+encodeURIComponent(slug)+'&preview=1','_blank','noopener');});
(async()=>{try{const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});if(!auth.ok){location.href='/admin-login.html?next='+encodeURIComponent(location.pathname);return;}await load();}catch(e){$('pageRows').innerHTML='<tr><td colspan="5">Admin connection unavailable. Please sign in again.</td></tr>';}})();
})();