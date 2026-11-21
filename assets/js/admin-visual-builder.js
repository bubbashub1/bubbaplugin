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
 if(sectionEditable){const p=currentSection.props||{};const set=(id,v)=>{if($(id))$(id).value=v;};set('sectionBackground',/^#[0-9a-f]{6}$/i.test(p.background||'')?p.background:'#ffffff');set('sectionPadding',Number.isInteger(p.padding)?p.padding:24);set('sectionGap',Number.isInteger(p.gap)?p.gap:16);set('containerWidth',Number.isInteger(p.width)?p.width:100);set('containerHeight',Number.isInteger(p.min_height)?p.min_height:0);set('containerRadius',Number.isInteger(p.radius)?p.radius:12);set('containerBorderWidth',Number.isInteger(p.border_width)?p.border_width:0);set('containerBorderColor',/^#[0-9a-f]{6}$/i.test(p.border_color||'')?p.border_color:'#dce8df');set('containerAlign',p.align||'center');set('containerSpacing',Number.isInteger(p.margin_bottom)?p.margin_bottom:18);['sectionPadding','sectionGap','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='containerWidth'?'%':'px');});
 const mobile=p.mobile&&typeof p.mobile==='object'?p.mobile:{};
 $('mobileOverrideEnabled').checked=Object.keys(mobile).length>0;
 $('mobileOverrideControls').hidden=!$('mobileOverrideEnabled').checked;
 populateMobileControls(mobile);
}
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
  ['addHeading','addParagraph','addButtonBlock'].forEach(id=>$(id).disabled=!currentSection||previewMode!=='draft');
  updateSelectionDetails(node);
  installContainerHandles(currentSection?node:null);
  const index=layerNodes.indexOf(node);
  document.querySelectorAll('.bhvb-layer-button').forEach(button=>button.classList.toggle('active',button._bhNode===node));
 }catch(error){$('selectedName').textContent='Preview unavailable';$('selectedDescription').textContent='The page could not be inspected safely.';}
}
function installContainerHandles(node){
 const doc=frame.contentDocument;
 if(!doc||!node||!node.dataset.bhSectionId||previewMode!=='draft')return;
 doc.querySelectorAll('.bhvb-canvas-handle').forEach(handle=>handle.remove());
 node.style.position='relative';
 const makeHandle=(label,cls,title)=>{
  const handle=doc.createElement('button');handle.type='button';handle.className='bhvb-canvas-handle '+cls;handle.textContent=label;handle.title=title;handle.setAttribute('aria-label',title);handle.style.cssText='position:absolute;z-index:20;border:2px solid #fff;border-radius:7px;background:#0f766e;color:#fff;box-shadow:0 1px 5px #0005;font:700 14px/1 Arial;touch-action:none;cursor:grab;display:grid;place-items:center;padding:0;';
  node.appendChild(handle);return handle;
 };
 const move=makeHandle('↕','bhvb-move-handle','Drag vertically to move this container');
 move.style.cssText+=';left:8px;top:8px;width:30px;height:30px;';
 const resize=makeHandle('↘','bhvb-resize-handle','Drag to resize this container');
 resize.style.cssText+=';right:5px;bottom:5px;width:28px;height:28px;cursor:nwse-resize;';
 const start=(event,mode)=>{
  if(event.button!==0&&event.pointerType!=='touch')return;
  event.preventDefault();event.stopPropagation();event.currentTarget.setPointerCapture?.(event.pointerId);
  const startX=event.clientX,startY=event.clientY,startWidth=node.getBoundingClientRect().width,startHeight=node.getBoundingClientRect().height,parentWidth=node.parentElement?.getBoundingClientRect().width||960;
  let moved=false,done=false;
  const onMove=e=>{
   const dx=e.clientX-startX,dy=e.clientY-startY;
   if(Math.abs(dx)+Math.abs(dy)<5)return;
   moved=true;
   if(mode==='resize'){
    const width=Math.max(25,Math.min(100,Math.round((startWidth+dx)/parentWidth*100)));
    const height=Math.max(0,Math.min(800,Math.round((startHeight+dy)/20)*20));
    const section=findSection(node.dataset.bhSectionId);if(!section)return;
    section.props=section.props||{};section.props.width=width;section.props.min_height=height;
    node.style.width=width+'%';node.style.minHeight=height+'px';
    $('containerWidth').value=width;$('containerWidthValue').textContent=width+'%';
    $('containerHeight').value=height;$('containerHeightValue').textContent=height+'px';
    draftStatus('Resizing container · '+width+'% wide · '+height+'px minimum height. Save draft when ready.');
   }else if(!done&&Math.abs(dy)>=36){
    const section=findSection(node.dataset.bhSectionId),index=draft.sections.indexOf(section),next=dy<0?index-1:index+1;
    if(section&&next>=0&&next<draft.sections.length){
     [draft.sections[index],draft.sections[next]]=[draft.sections[next],draft.sections[index]];
     done=true;renderDraft();draftStatus('Container moved by dragging. Save draft to keep this order.');
    }
   }
  };
  const onEnd=()=>{doc.removeEventListener('pointermove',onMove,true);doc.removeEventListener('pointerup',onEnd,true);doc.removeEventListener('pointercancel',onEnd,true);if(moved&&!done&&mode==='resize'){$('saveDraft').disabled=false;}};
  doc.addEventListener('pointermove',onMove,true);doc.addEventListener('pointerup',onEnd,true);doc.addEventListener('pointercancel',onEnd,true);
 };
 move.addEventListener('pointerdown',event=>start(event,'move'));
 resize.addEventListener('pointerdown',event=>start(event,'resize'));
}
function moveDraftBlock(blockId,targetSectionId,targetBlockId,after){
 if(!draft)return false;
 let sourceSection=null,sourceIndex=-1,block=null;
 for(const section of draft.sections||[]){const index=(section.children||[]).findIndex(item=>item.id===blockId);if(index>=0){sourceSection=section;sourceIndex=index;block=section.children[index];break;}}
 const targetSection=findSection(targetSectionId);if(!block||!sourceSection||!targetSection)return false;
 let targetIndex=targetBlockId?(targetSection.children||[]).findIndex(item=>item.id===targetBlockId):-1;
 if(targetBlockId&&targetIndex<0)return false;
 sourceSection.children.splice(sourceIndex,1);
 if(sourceSection===targetSection&&targetIndex>sourceIndex)targetIndex--;
 let insertAt=targetBlockId?targetIndex+(after?1:0):targetSection.children.length;
 targetSection.children.splice(Math.max(0,Math.min(targetSection.children.length,insertAt)),0,block);
 currentSection=targetSection;currentBlock=block;
 renderDraft();
 draftStatus('Element moved to '+(targetSection===sourceSection?'new position':'another container')+'. Save draft to keep this change.');
 return true;
}
function findBlock(id){
 for(const section of draft?.sections||[])for(const block of section.children||[])if(block.id===id)return block;
 return null;
}
function findSection(id){return (draft?.sections||[]).find(section=>section.id===id)||null;}
function populateMobileControls(m){
 const set=(id,v)=>{if($(id))$(id).value=v;};
 set('mobileContainerWidth',Number.isInteger(m.width)?m.width:100);
 set('mobileContainerPadding',Number.isInteger(m.padding)?m.padding:16);
 set('mobileContainerGap',Number.isInteger(m.gap)?m.gap:12);
 set('mobileContainerHeight',Number.isInteger(m.min_height)?m.min_height:0);
 set('mobileContainerRadius',Number.isInteger(m.radius)?m.radius:12);
 set('mobileContainerBorderWidth',Number.isInteger(m.border_width)?m.border_width:0);
 set('mobileContainerSpacing',Number.isInteger(m.margin_bottom)?m.margin_bottom:16);
 set('mobileContainerAlign',m.align||'center');
 ['mobileContainerWidth','mobileContainerPadding','mobileContainerGap','mobileContainerHeight','mobileContainerRadius','mobileContainerBorderWidth','mobileContainerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='mobileContainerWidth'?'%':'px');});
}
function mobileOverridesCss(){
 const rules=[];
 for(const section of draft?.sections||[]){
  const p=section.props||{},m=p.mobile;if(!m||typeof m!=='object')continue;
  const align=['left','center','right'].includes(m.align)?m.align:'center';
  const self=align==='left'?'flex-start':align==='right'?'flex-end':'center';
  const selector='section[data-bh-section-id="'+section.id+'"]';
  rules.push(selector+'{width:'+Math.min(100,Math.max(25,Number(m.width??100)))+'%!important;min-height:'+Math.min(800,Math.max(0,Number(m.min_height??0)))+'px!important;padding:'+Math.min(96,Math.max(0,Number(m.padding??16)))+'px!important;gap:'+Math.min(64,Math.max(0,Number(m.gap??12)))+'px!important;border-radius:'+Math.min(48,Math.max(0,Number(m.radius??12)))+'px!important;border-width:'+Math.min(8,Math.max(0,Number(m.border_width??0)))+'px!important;margin-bottom:'+Math.min(96,Math.max(0,Number(m.margin_bottom??16)))+'px!important;align-self:'+self+'!important;}');
 }
 return rules.length?'@media(max-width:600px){'+rules.join('')+'}':'';
}
function updateMobilePreviewStyle(){const style=frame.contentDocument?.getElementById('bhvb-mobile-overrides');if(style)style.textContent=mobileOverridesCss();}
function buildLayers(doc){
 const list=$('builderLayers');list.replaceChildren();layerNodes=[];
 const main=doc.querySelector('main');
 if(!main){const empty=document.createElement('p');empty.className='bhvb-layers-empty';empty.textContent='No supported content elements were found on this page.';list.append(empty);return;}
 const supported='h1,h2,h3,h4,p,a,button,img,section,article';
 const addLayer=(node,parent,depth=0)=>{
  if(!node||layerNodes.length>=120)return;
  layerNodes.push(node);
  const button=document.createElement('button');button.type='button';button.className='bhvb-layer-button';button.style.setProperty('--layer-depth',String(depth));button.textContent=readableName(node);button.setAttribute('aria-label','Select '+readableName(node));button._bhNode=node;button.addEventListener('click',()=>selectNode(node));parent.append(button);
 };
 const sections=[...main.querySelectorAll(':scope > section')];
 const sectionSet=new Set(sections);
 [...main.children].forEach(node=>{
  if(layerNodes.length>=120)return;
  if(sectionSet.has(node)){
   const group=document.createElement('div');group.className='bhvb-layer-group';
   const heading=document.createElement('div');heading.className='bhvb-layer-group-title';heading.textContent='CONTAINER';group.append(heading);
   addLayer(node,group,0);
   const children=[...node.querySelectorAll(supported)].filter(child=>child!==node&&!child.closest('section section'));
   children.forEach(child=>addLayer(child,group,1));
   list.append(group);
  }else if(node.matches(supported)){
   addLayer(node,list,0);
  }else{
   [...node.querySelectorAll(supported)].forEach(child=>addLayer(child,list,0));
  }
 });
 if(!layerNodes.length){const empty=document.createElement('p');empty.className='bhvb-layers-empty';empty.textContent='No supported content elements were found on this page.';list.append(empty);}
}
function attachCanvas(){
 try{
  const doc=frame.contentDocument;if(!doc||!doc.body)throw new Error('Page not ready');
  const style=doc.createElement('style');style.textContent='.bhvb-selected-preview{outline:3px solid #0f8b83!important;outline-offset:2px!important;cursor:pointer!important}.bhvb-canvas-handle{opacity:.96}.bhvb-canvas-handle:focus-visible{outline:3px solid #fbbf24;outline-offset:2px}.bhvb-dragging{opacity:.35!important}.bhvb-drag-over{outline:2px dashed #0f766e!important;outline-offset:3px!important}';doc.head.appendChild(style);
  buildLayers(doc);
  let draggedBlockId='';
  doc.addEventListener('dragstart',event=>{
   const blockNode=event.target.closest('[data-bh-block-id]');
   if(previewMode!=='draft'||!blockNode)return;
   draggedBlockId=blockNode.dataset.bhBlockId;
   event.dataTransfer.effectAllowed='move';
   event.dataTransfer.setData('text/plain',draggedBlockId);
   blockNode.classList.add('bhvb-dragging');
  });
  doc.addEventListener('dragover',event=>{
   if(previewMode!=='draft'||!draggedBlockId)return;
   const targetBlock=event.target.closest('[data-bh-block-id]');
   const targetSection=event.target.closest('[data-bh-section-id]');
   if(targetBlock||targetSection){event.preventDefault();event.dataTransfer.dropEffect='move';doc.querySelectorAll('.bhvb-drag-over').forEach(node=>node.classList.remove('bhvb-drag-over'));(targetBlock||targetSection).classList.add('bhvb-drag-over');}
  });
  doc.addEventListener('drop',event=>{
   if(previewMode!=='draft'||!draggedBlockId)return;
   const targetBlock=event.target.closest('[data-bh-block-id]');
   const targetSection=event.target.closest('[data-bh-section-id]');
   if(!targetBlock&&!targetSection)return;
   event.preventDefault();event.stopPropagation();
   const sourceId=draggedBlockId;
   const sectionId=(targetBlock?.closest('[data-bh-section-id]')||targetSection)?.dataset.bhSectionId;
   const targetId=targetBlock?.dataset.bhBlockId||'';
   if(sectionId&&sourceId!==targetId){
    const after=targetBlock?event.clientY>targetBlock.getBoundingClientRect().top+targetBlock.getBoundingClientRect().height/2:false;
    moveDraftBlock(sourceId,sectionId,targetId,after);
   }
   draggedBlockId='';
  });
  doc.addEventListener('dragend',()=>{draggedBlockId='';doc.querySelectorAll('.bhvb-dragging,.bhvb-drag-over').forEach(node=>node.classList.remove('bhvb-dragging','bhvb-drag-over'));});
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
   if(block.type==='heading'){const level=Math.max(1,Math.min(4,Number(p.level)||2));return '<h'+level+' draggable="true" data-bh-block-id="'+id+'" style="text-align:'+esc(p.align||'left')+';cursor:grab">'+esc(p.text)+'</h'+level+'>';}
   if(block.type==='paragraph')return '<p draggable="true" data-bh-block-id="'+id+'" style="text-align:'+esc(p.align||'left')+';cursor:grab">'+esc(p.text)+'</p>';
   if(block.type==='button'&&safeHref(p.href))return '<p><a class="cta" draggable="true" data-bh-block-id="'+id+'" href="'+esc(p.href)+'" style="cursor:grab">'+esc(p.text)+'</a></p>';
   return '';
  }).join('');
  const p=section.props||{};const w=Math.min(100,Math.max(25,Number(p.width??100))),h=Math.min(800,Math.max(0,Number(p.min_height??0))),rad=Math.min(48,Math.max(0,Number(p.radius??12))),bw=Math.min(8,Math.max(0,Number(p.border_width??0))),al=['left','center','right'].includes(p.align)?p.align:'center',bc=/^#[0-9a-f]{6}$/i.test(p.border_color||'')?p.border_color:'#dce8df';
  return '<section data-bh-section-id="'+esc(section.id)+'" style="display:flex;flex-direction:column;width:'+w+'%;min-height:'+h+'px;align-self:'+(al==='left'?'flex-start':al==='right'?'flex-end':'center')+';margin:0 0 '+Math.min(96,Math.max(0,Number(p.margin_bottom??18)))+'px;padding:'+Math.min(96,Math.max(0,Number(p.padding??24)))+'px;gap:'+Math.min(64,Math.max(0,Number(p.gap??16)))+'px;background:'+( /^#[0-9a-f]{6}$/i.test(p.background||'')?p.background:'#ffffff')+';border:'+bw+'px solid '+bc+';border-radius:'+rad+'px">'+children+'</section>';
 }).join('');
 const mobileCss=mobileOverridesCss();
 const columns=Math.max(1,Math.min(3,Number(draft.settings?.columns)||1));
 $('pageColumns').value=String(columns);$('pageColumns').disabled=false;
 const title=esc(draft.page?.title||page?.title||'Draft preview');
 const hasPageHeading=(draft.sections||[]).some(section=>(section.children||[]).some(block=>block.type==='heading'&&Number(block.props?.level)===1));
 const titleMarkup=hasPageHeading?'':'<h1 style="grid-column:1/-1">'+title+'</h1>';
 frame.srcdoc='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><style>*{box-sizing:border-box}body{margin:0;padding:32px;background:#f4f8f7;color:#17221c;font:16px Calibri,Arial,sans-serif}main{max-width:960px;margin:auto;display:grid;grid-template-columns:repeat('+columns+',minmax(0,1fr));gap:18px;align-items:start}h1,h2,h3,h4{font-family:Quicksand,Arial,sans-serif;color:#244d37}section{margin:0 0 18px;border-radius:14px}p{line-height:1.7}.cta{display:inline-block;padding:12px 20px;background:#416651;color:white;border-radius:999px;text-decoration:none;font-weight:700}@media(max-width:600px){body{padding:14px}main{grid-template-columns:1fr!important;gap:12px!important}}</style><style id="bhvb-mobile-overrides">'+mobileCss+'</style></head><body><main>'+titleMarkup+sections+'</main></body></html>';
 $('canvasEmpty').hidden=true;frame.hidden=false;
 $('canvasDimensions').textContent=(page?.title||title)+' · '+device+' · Draft';
 $('saveDraft').disabled=false;$('addContainer').disabled=false;
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
 draft={schema_version:1,page:{title:page.title,slug:page.slug},settings:{max_width:'960px',columns:1},sections:[{id:'main-content',type:'section',props:{padding:24,gap:16},children}]};
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
 $('selectedName').textContent='Nothing selected';$('selectedDescription').textContent='Choose a page to begin.';$('selectedDetails').hidden=true;$('editControls').hidden=true;$('sectionControls').hidden=true;
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
function applySectionEdit(){if(!currentSection||previewMode!=='draft')return;const p=currentSection.props||(currentSection.props={});p.background=$('sectionBackground').value;p.padding=Number($('sectionPadding').value);p.gap=Number($('sectionGap').value);p.width=Number($('containerWidth').value);p.min_height=Number($('containerHeight').value);p.radius=Number($('containerRadius').value);p.border_width=Number($('containerBorderWidth').value);p.border_color=$('containerBorderColor').value;p.align=$('containerAlign').value;p.margin_bottom=Number($('containerSpacing').value);['sectionPadding','sectionGap','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='containerWidth'?'%':'px');});const node=frame.contentDocument?.querySelector('[data-bh-section-id="'+CSS.escape(currentSection.id)+'"]');if(node){node.style.background=p.background;node.style.padding=p.padding+'px';node.style.gap=p.gap+'px';node.style.width=p.width+'%';node.style.minHeight=p.min_height+'px';node.style.borderRadius=p.radius+'px';node.style.border=p.border_width+'px solid '+p.border_color;node.style.alignSelf=p.align==='left'?'flex-start':p.align==='right'?'flex-end':'center';node.style.marginBottom=p.margin_bottom+'px';}draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved container changes. Save draft when ready.');}
function applyMobileEdit(){if(!currentSection||previewMode!=='draft'||!$('mobileOverrideEnabled').checked)return;const p=currentSection.props||(currentSection.props={});p.mobile={width:Number($('mobileContainerWidth').value),padding:Number($('mobileContainerPadding').value),gap:Number($('mobileContainerGap').value),min_height:Number($('mobileContainerHeight').value),radius:Number($('mobileContainerRadius').value),border_width:Number($('mobileContainerBorderWidth').value),margin_bottom:Number($('mobileContainerSpacing').value),align:$('mobileContainerAlign').value};['mobileContainerWidth','mobileContainerPadding','mobileContainerGap','mobileContainerHeight','mobileContainerRadius','mobileContainerBorderWidth','mobileContainerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='mobileContainerWidth'?'%':'px');});updateMobilePreviewStyle();draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved mobile layout changes. Save draft when ready.');}
function toggleMobileOverrides(){if(!currentSection||previewMode!=='draft')return;const p=currentSection.props||(currentSection.props={});if($('mobileOverrideEnabled').checked){p.mobile={width:100,padding:16,gap:12,min_height:0,radius:12,border_width:0,margin_bottom:16,align:'center'};populateMobileControls(p.mobile);$('mobileOverrideControls').hidden=false;applyMobileEdit();}else{delete p.mobile;$('mobileOverrideControls').hidden=true;updateMobilePreviewStyle();draftStatus('Mobile overrides removed. The container now inherits its desktop settings.');}}
['sectionBackground','sectionPadding','sectionGap','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerBorderColor','containerAlign','containerSpacing'].forEach(id=>$(id).addEventListener('input',applySectionEdit));
$('mobileOverrideEnabled').addEventListener('change',toggleMobileOverrides);
['mobileContainerWidth','mobileContainerPadding','mobileContainerGap','mobileContainerHeight','mobileContainerRadius','mobileContainerBorderWidth','mobileContainerSpacing','mobileContainerAlign'].forEach(id=>$(id).addEventListener('input',applyMobileEdit));
function addContainer(){if(!draft)return;draft.sections.push({id:'container-'+Date.now(),type:'section',props:{background:'#ffffff',padding:24,gap:16,width:100,min_height:120,radius:12,border_width:1,border_color:'#dce8df',align:'center',margin_bottom:18},children:[]});renderDraft();draftStatus('New container added. Select it in the canvas or Layers to style it.');}
function addBlock(type){if(!currentSection||!draft)return;const id='block-'+Date.now();let block;if(type==='heading')block={id,type,props:{text:'New heading',level:2}};else if(type==='paragraph')block={id,type,props:{text:'Add your text here.'}};else block={id,type:'button',props:{text:'Learn more',href:'/index.html'}};currentSection.children.push(block);renderDraft();draftStatus('Element added to selected container. Select it in the preview to edit its content.');}
function moveContainer(direction){if(!currentSection||!draft)return;const index=draft.sections.indexOf(currentSection),next=index+direction;if(next<0||next>=draft.sections.length)return;[draft.sections[index],draft.sections[next]]=[draft.sections[next],draft.sections[index]];renderDraft();draftStatus('Container moved. Save draft to keep this order.');}
function deleteContainer(){if(!currentSection||!draft||draft.sections.length<2){draftStatus('A page must keep at least one container.');return;}if(!window.confirm('Delete this container and its contents from the private draft?'))return;draft.sections=draft.sections.filter(section=>section!==currentSection);currentSection=null;renderDraft();$('sectionControls').hidden=true;draftStatus('Container removed from the private draft. Save draft to keep the change.');}
$('pageColumns').addEventListener('change',()=>{if(!draft)return;draft.settings=draft.settings||{};draft.settings.columns=Math.max(1,Math.min(3,Number($('pageColumns').value)||1));renderDraft();draftStatus('Page layout changed to '+draft.settings.columns+' columns. Mobile preview automatically stacks to one column. Save draft when ready.');});
document.querySelectorAll('[data-bh-add]').forEach(button=>button.addEventListener('click',()=>$(button.dataset.bhAdd)?.click()));
$('addContainer').addEventListener('click',addContainer);$('addHeading').addEventListener('click',()=>addBlock('heading'));$('addParagraph').addEventListener('click',()=>addBlock('paragraph'));$('addButtonBlock').addEventListener('click',()=>addBlock('button'));$('moveContainerUp').addEventListener('click',()=>moveContainer(-1));$('moveContainerDown').addEventListener('click',()=>moveContainer(1));$('deleteContainer').addEventListener('click',deleteContainer);
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