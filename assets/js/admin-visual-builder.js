(()=>{'use strict';
const $=id=>document.getElementById(id);
const frame=$('pageCanvas');
let pages=[],page=null,device='desktop',layerNodes=[],draft=null,csrf='',revisionRows=[],currentBlock=null,currentSection=null,previewMode='published',draftHasValidationError=false;
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function status(message){$('builderStatus').textContent=message;}
function draftStatus(message){$('draftStatus').textContent=message;}
function readableName(node){const tag=node.tagName.toLowerCase();const text=(node.getAttribute('alt')||node.innerText||node.textContent||'').trim().replace(/\s+/g,' ').slice(0,54);return (tag==='a'?'Button / link':tag==='img'?'Image':tag==='button'?'Button':tag==='section'?'Section':tag==='article'?'Content block':tag.toUpperCase())+(text?' · '+text:'');}
function updateSelectionDetails(node){
 draftHasValidationError=false;$('saveDraft').disabled=!draft||!page;
 $('selectedName').textContent=node.getAttribute('aria-label')||readableName(node);
 $('selectedDescription').textContent=previewMode==='draft'?'Edit the selected supported block below. Changes remain in this private draft.':'This is the published page preview. Prepare a structured draft before editing.';
 $('selectedDetails').hidden=false;$('selectedTag').textContent=node.tagName.toLowerCase();
 $('selectedText').textContent=String(node.getAttribute('alt')||node.innerText||node.textContent||'').trim().replace(/\s+/g,' ').slice(0,240)||'No text content';
 const editable=previewMode==='draft'&&currentBlock;
 $('editControls').hidden=!editable;
 const sectionEditable=previewMode==='draft'&&currentSection;
 $('sectionControls').hidden=!sectionEditable;
 if(sectionEditable){const props=currentSection.props||{};$('sectionBackground').value=/^#[0-9a-f]{6}$/i.test(props.background||'')?props.background:'#ffffff';$('sectionPadding').value=Number.isInteger(props.padding)?props.padding:24;$('sectionGap').value=Number.isInteger(props.gap)?props.gap:16;$('sectionPaddingValue').textContent=$('sectionPadding').value+'px';$('sectionGapValue').textContent=$('sectionGap').value+'px';}
 if(editable){
  const props=currentBlock.props||{};
  $('editText').value=props.text||'';
  $('editText').maxLength=currentBlock.type==='heading'?500:(currentBlock.type==='button'?120:10000);
  const isButton=currentBlock.type==='button';
  $('editHref').hidden=!isButton;$('editHrefLabel').hidden=!isButton;
  $('editHref').value=isButton?(props.href||''):'';
 }
}
function selectNode(node){
 if(!node)return;
 try{
  const doc=frame.contentDocument;
  doc?.querySelectorAll('.bhvb-selected-preview').forEach(n=>n.classList.remove('bhvb-selected-preview'));
  node.classList.add('bhvb-selected-preview');
  currentBlock=draft&&node.dataset.bhBlockId?findBlock(node.dataset.bhBlockId):null;
  currentSection=draft&&node.dataset.bhSectionId?findSection(node.dataset.bhSectionId):null;
  updateSelectionDetails(node);
  const index=layerNodes.indexOf(node);
  document.querySelectorAll('.bhvb-layer-button').forEach((button,i)=>button.classList.toggle('active',i===index));
 }catch(error){$('selectedName').textContent='Preview unavailable';$('selectedDescription').textContent='The page could not be inspected safely.';}
}
function findBlock(id){
 for(const section of draft?.sections||[])for(const block of section.children||[])if(block.id===id)return block;
 return null;
}
function findSection(id){return (draft?.sections||[]).find(section=>section.id===id)||null;}
function buildLayers(doc){
 const list=$('builderLayers');list.replaceChildren();layerNodes=[];
 const nodes=[...doc.querySelectorAll('main h1,main h2,main h3,main h4,main p,main a,main button,main img,main section,main article')].slice(0,120);
 layerNodes=nodes;
 if(!nodes.length){const empty=document.createElement('p');empty.className='bhvb-layers-empty';empty.textContent='No supported content elements were found on this page.';list.append(empty);return;}
 nodes.forEach(node=>{
  const button=document.createElement('button');button.type='button';button.className='bhvb-layer-button';button.textContent=readableName(node);button.setAttribute('aria-label','Select '+readableName(node));button.addEventListener('click',()=>selectNode(node));list.append(button);
 });
}
function attachCanvas(){
 try{
  const doc=frame.contentDocument;if(!doc||!doc.body)throw new Error('Page not ready');
  const style=doc.createElement('style');style.textContent='.bhvb-selected-preview{outline:3px solid #0f8b83!important;outline-offset:2px!important;cursor:pointer!important}';doc.head.appendChild(style);
  buildLayers(doc);
  doc.addEventListener('click',event=>{
   const node=event.target.closest('h1,h2,h3,h4,p,a,button,img,section,article');
   if(!node||node===doc.body)return;
   event.preventDefault();event.stopPropagation();selectNode(node);
  },true);
  status(previewMode==='draft'?'Draft preview ready · unpublished':'Published page preview · read-only');
 }catch(error){status('Preview loaded · selection unavailable');}
}
function setDevice(next){
 device=next;
 document.querySelectorAll('.bhvb-device-switch [data-device]').forEach(button=>{const active=button.dataset.device===device;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});
 frame.dataset.device=device;
 $('canvasDimensions').textContent=(page?.title||'Page')+' · '+device+(previewMode==='draft'?' · Draft':'');
}
function setTab(tab){
 const properties=tab==='properties';
 $('propertiesTab').classList.toggle('active',properties);$('layersTab').classList.toggle('active',!properties);
 $('propertiesTab').setAttribute('aria-selected',String(properties));$('layersTab').setAttribute('aria-selected',String(!properties));
 $('propertiesPanel').hidden=!properties;$('layersPanel').hidden=properties;
}
function safeHref(value){
 const href=String(value||'').trim();
 if(!href||/[\\u0000-\\u0020\\u007f\\\\]/.test(href)||href.startsWith('//'))return false;
 if(/^https?:\/\//i.test(href)){
  try{const parsed=new URL(href);return (parsed.protocol==='http:'||parsed.protocol==='https:')&&Boolean(parsed.hostname)&&!parsed.username&&!parsed.password;}
  catch(error){return false;}
 }
 if(/^mailto:[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/i.test(href))return true;
 if(/^tel:\\+?[0-9().-]{3,32}$/i.test(href))return true;
 return href.startsWith('/')||((href.startsWith('#')||href.startsWith('?'))&&href.length>1);
}
function renderDraft(){
 if(!draft)return;
 previewMode='draft';
 const sections=(draft.sections||[]).map(section=>{
  const children=(section.children||[]).map(block=>{
   const p=block.props||{},id=esc(block.id);
   if(block.type==='heading'){const level=Math.max(1,Math.min(4,Number(p.level)||2));return '<h'+level+' data-bh-block-id="'+id+'" style="text-align:'+esc(p.align||'left')+'">'+esc(p.text)+'</h'+level+'>';}
   if(block.type==='paragraph')return '<p data-bh-block-id="'+id+'" style="text-align:'+esc(p.align||'left')+'">'+esc(p.text)+'</p>';
   if(block.type==='button'&&safeHref(p.href))return '<p><a class="cta" data-bh-block-id="'+id+'" href="'+esc(p.href)+'">'+esc(p.text)+'</a></p>';
   return '';
  }).join('');
  const props=section.props||{};
  return '<section data-bh-section-id="'+esc(section.id)+'" style="display:flex;flex-direction:column;padding:'+Math.min(96,Math.max(0,Number(props.padding??24)))+'px;gap:'+Math.min(64,Math.max(0,Number(props.gap??16)))+'px;background:'+( /^#[0-9a-f]{6}$/i.test(props.background||'')?props.background:'#ffffff')+'">'+children+'</section>';
 }).join('');
 const title=esc(draft.page?.title||page?.title||'Draft preview');
 const hasPageHeading=(draft.sections||[]).some(section=>(section.children||[]).some(block=>block.type==='heading'&&Number(block.props?.level)===1));
 const titleMarkup=hasPageHeading?'':'<h1>'+title+'</h1>';
 frame.srcdoc='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><style>*{box-sizing:border-box}body{margin:0;padding:32px;background:#f4f8f7;color:#17221c;font:16px Calibri,Arial,sans-serif}main{max-width:960px;margin:auto}h1,h2,h3,h4{font-family:Quicksand,Arial,sans-serif;color:#244d37}section{margin:0 0 18px;border-radius:14px}p{line-height:1.7}.cta{display:inline-block;padding:12px 20px;background:#416651;color:white;border-radius:999px;text-decoration:none;font-weight:700}@media(max-width:600px){body{padding:14px}}</style></head><body><main>'+titleMarkup+sections+'</main></body></html>';
 $('canvasEmpty').hidden=true;frame.hidden=false;
 $('canvasDimensions').textContent=(page?.title||title)+' · '+device+' · Draft';
 $('saveDraft').disabled=false;
}
function convertPage(){
 const doc=frame.contentDocument;
 if(!doc||!doc.querySelector('main')){draftStatus('The selected page has no readable main content to convert.');return;}
 const main=doc.querySelector('main');
 const nodes=[...main.querySelectorAll('h1,h2,h3,h4,p,a[href]')].filter(node=>{
  if(node.tagName==='A'&&node.closest('p,h1,h2,h3,h4'))return false;
  return true;
 }).slice(0,80);
 const children=[];let skipped=0;
 nodes.forEach((node,index)=>{
  const text=(node.innerText||node.textContent||'').trim().replace(/\s+/g,' ');
  if(!text){skipped++;return;}
  const id='block-'+(index+1);
  if(/^H[1-4]$/.test(node.tagName))children.push({id,type:'heading',props:{text:text.slice(0,500),level:Number(node.tagName.slice(1))}});
  else if(node.tagName==='P')children.push({id,type:'paragraph',props:{text:text.slice(0,10000)}});
  else if(node.tagName==='A'&&safeHref(node.getAttribute('href')))children.push({id,type:'button',props:{text:text.slice(0,120),href:node.getAttribute('href')}});
  else skipped++;
 });
 const images=main.querySelectorAll('img').length;
 draft={schema_version:1,page:{title:page.title,slug:page.slug},settings:{max_width:'960px'},sections:[{id:'main-content',type:'section',props:{padding:24,gap:16},children}]};
 const message='Conversion preview: '+children.length+' supported text/link blocks. '+skipped+' empty or unsupported items skipped; '+images+' images are not converted. Original layout and styling are not carried over. Review this simplified draft before saving.';
 draftStatus(message);
 renderDraft();
 $('prepareDraft').disabled=true;
 $('editControls').hidden=true;
 $('selectedName').textContent='Draft prepared';
 $('selectedDescription').textContent='Choose a heading, paragraph or button in the draft preview to edit it.';
 $('selectedDetails').hidden=true;
}
async function api(path,body){
 const response=await fetch(path,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json','Accept':'application/json'}:{Accept:'application/json'},body:body?JSON.stringify({...body,csrf}):undefined});
 const data=await response.json();
 if(!response.ok||!data.ok)throw new Error(data.error||'Visual Builder request failed.');
 return data;
}
async function loadDraft(){
 if(!page)return;
 try{
  const data=await api('/api/admin-visual-page-drafts.php?page_id='+encodeURIComponent(page.id));
  csrf=data.csrf||csrf;
  $('setupDraftStorage').hidden=!data.setup_required;
  $('prepareDraft').disabled=false;
  if(data.setup_required){draftStatus('One-time draft storage setup is required. This creates separate builder-only tables.');$('saveDraft').disabled=true;return;}
  revisionRows=data.revisions||[];
  const select=$('revisionSelect');select.innerHTML=revisionRows.map(r=>'<option value="'+esc(r.id)+'">Revision '+esc(r.id)+' · '+esc(r.created_at)+'</option>').join('');
  $('revisionControls').hidden=!revisionRows.length;
  if(data.draft){draft=data.draft;renderDraft();draftStatus('Existing unpublished draft loaded. You can edit and save it.');}
  else{draft=null;previewMode='published';draftStatus('No builder draft exists yet. Prepare a simplified draft from this page to begin.');}
 }catch(error){draftStatus(error.message);$('saveDraft').disabled=true;}
}
async function loadPage(slug){
 page=pages.find(item=>item.slug===slug)||null;draft=null;previewMode='published';currentBlock=null;currentSection=null;layerNodes=[];
 $('selectedName').textContent='Nothing selected';$('selectedDescription').textContent='Choose a page to begin.';$('selectedDetails').hidden=true;$('editControls').hidden=true;
 $('builderLayers').replaceChildren();$('saveDraft').disabled=true;$('prepareDraft').disabled=!page;$('setupDraftStorage').hidden=true;$('revisionControls').hidden=true;
 if(!page){frame.hidden=true;$('canvasEmpty').hidden=false;return;}
 frame.hidden=false;$('canvasEmpty').hidden=true;
 $('canvasDimensions').textContent=page.title+' · '+device;
 const loaded=new Promise(resolve=>frame.addEventListener('load',resolve,{once:true}));
 frame.src='/page.php?slug='+encodeURIComponent(page.slug);
 await loaded;
 await loadDraft();
}
$('builderPage').addEventListener('change',event=>loadPage(event.target.value));
document.querySelectorAll('.bhvb-device-switch [data-device]').forEach(button=>button.addEventListener('click',()=>setDevice(button.dataset.device)));
$('propertiesTab').addEventListener('click',()=>setTab('properties'));$('layersTab').addEventListener('click',()=>setTab('layers'));
frame.addEventListener('load',attachCanvas);
$('prepareDraft').addEventListener('click',convertPage);
$('setupDraftStorage').addEventListener('click',async()=>{
 try{const data=await api('/api/admin-visual-page-drafts.php',{action:'setup'});csrf=data.csrf||csrf;draftStatus(data.message||'Draft storage is ready.');$('setupDraftStorage').hidden=true;await loadDraft();}
 catch(error){draftStatus(error.message);}
});
$('saveDraft').addEventListener('click',async()=>{
 if(!draft||!page||draftHasValidationError)return;
 $('saveDraft').disabled=true;
 try{const data=await api('/api/admin-visual-page-drafts.php',{action:'save_draft',page_id:page.id,document:draft});draft=data.document||draft;draftStatus(data.message||'Draft saved.');await loadDraft();}
 catch(error){draftStatus(error.message);}
 finally{$('saveDraft').disabled=false;}
});
$('restoreRevision').addEventListener('click',async()=>{
 const revisionId=Number($('revisionSelect').value);if(!revisionId||!page)return;
 if(!window.confirm('Restore this previous builder draft? Your current draft will first be saved as a revision. The published page will remain unchanged.'))return;
 try{const data=await api('/api/admin-visual-page-drafts.php',{action:'restore_revision',page_id:page.id,revision_id:revisionId});draft=data.document||null;renderDraft();draftStatus(data.message||'Draft restored.');await loadDraft();}
 catch(error){draftStatus(error.message);}
});
function applyEdit(){
 if(!currentBlock||previewMode!=='draft')return;
 const nextText=$('editText').value;
 const nextHref=currentBlock.type==='button' ? $('editHref').value.trim() : '';
 if(currentBlock.type==='button'&&!safeHref(nextHref)){
  draftHasValidationError=true;$('saveDraft').disabled=true;
  draftStatus('Link not saved: enter a valid http(s) website, email, phone or same-site link. Correct the address before saving.');
  return;
 }
 const props=currentBlock.props;
 props.text=nextText;
 if(currentBlock.type==='button')props.href=nextHref;
 draftHasValidationError=false;$('saveDraft').disabled=false;
 const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');
 if(node){node.textContent=props.text;if(currentBlock.type==='button')node.setAttribute('href',props.href);}
 if(node){$('selectedText').textContent=props.text;$('selectedName').textContent=readableName(node);}
 draftStatus('Unsaved draft changes. Save draft when ready.');
}
$('editText').addEventListener('input',applyEdit);$('editHref').addEventListener('change',applyEdit);
function applySectionEdit(){if(!currentSection||previewMode!=='draft')return;const props=currentSection.props||(currentSection.props={});props.background=$('sectionBackground').value;props.padding=Number($('sectionPadding').value);props.gap=Number($('sectionGap').value);$('sectionPaddingValue').textContent=props.padding+'px';$('sectionGapValue').textContent=props.gap+'px';const node=frame.contentDocument?.querySelector('[data-bh-section-id="'+CSS.escape(currentSection.id)+'"]');if(node){node.style.background=props.background;node.style.padding=props.padding+'px';node.style.gap=props.gap+'px';}draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved section changes. Save draft when ready.');}
$('sectionBackground').addEventListener('input',applySectionEdit);$('sectionPadding').addEventListener('input',applySectionEdit);$('sectionGap').addEventListener('input',applySectionEdit);
async function init(){
 try{
  const auth=await fetch('/admin-auth.php?action=check',{credentials:'same-origin',cache:'no-store'});
  if(!auth.ok)throw new Error('Admin sign-in required.');
  const response=await fetch('/api/admin-pages.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok||!data.ok)throw new Error(data.error||'Could not load pages.');
  if(data.setup_required){status('Page Manager setup required');$('canvasEmpty').textContent='Complete Page Manager setup before selecting pages.';return;}
  csrf=data.csrf||'';pages=(data.pages||[]).filter(item=>item.status==='published');
  const select=$('builderPage');select.innerHTML='<option value="">Choose a published page…</option>'+pages.map(item=>'<option value="'+esc(item.slug)+'">'+esc(item.title)+'</option>').join('');select.disabled=false;
  status(pages.length?'Connected · '+pages.length+' published pages':'Connected · no published pages yet');
  if(!pages.length)$('canvasEmpty').textContent='There are no published managed pages to preview yet. Your existing homepage and system pages are not modified by this first prototype.';
 }catch(error){status(error.message);$('canvasEmpty').textContent=error.message;}
}
init();
})();