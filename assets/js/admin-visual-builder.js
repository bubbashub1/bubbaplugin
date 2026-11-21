(()=>{'use strict';
const $=id=>document.getElementById(id);
const frame=$('pageCanvas');
let pages=[],page=null,device='desktop',layerNodes=[],draft=null,csrf='',revisionRows=[],currentBlock=null,currentSection=null,previewMode='published',draftHasValidationError=false,inlineEditingNode=null;
function normalizedColumnWidths(value,count){const parts=Array.isArray(value)?value:String(value||'').split(',');const widths=parts.slice(0,count).map(v=>Math.max(10,Math.min(90,Number(v)||0)));if(widths.length!==count||widths.some(v=>!v))return Array(count).fill(100/count);const total=widths.reduce((a,b)=>a+b,0);return widths.map(v=>Math.round(v/total*100));}
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
 $('imageControls').hidden=!(editable&&currentBlock.type==='image');
 $('editControls').hidden=!editable;
 const sectionEditable=previewMode==='draft'&&currentSection;
 $('sectionControls').hidden=!sectionEditable;
 if(sectionEditable){const p=currentSection.props||{};const set=(id,v)=>{if($(id))$(id).value=v;};set('sectionBackground',/^#[0-9a-f]{6}$/i.test(p.background||'')?p.background:'#ffffff');set('sectionPalette',p.palette||'custom');set('sectionGradient',p.gradient||'');set('sectionTextColor',/^#[0-9a-f]{6}$/i.test(p.color||'')?p.color:'#222222');set('sectionPadding',Number.isInteger(p.padding)?p.padding:24);set('sectionGap',Number.isInteger(p.gap)?p.gap:16);set('containerColumns',Math.max(1,Math.min(4,Number(p.columns)||1)));set('containerColumnWidths',normalizedColumnWidths(p.column_widths,Math.max(1,Math.min(4,Number(p.columns)||1))).join(', '));$('containerColumnWidthsWrap').hidden=Number(p.columns||1)<=1;set('containerWidth',Number.isInteger(p.width)?p.width:100);set('containerHeight',Number.isInteger(p.min_height)?p.min_height:0);set('containerRadius',Number.isInteger(p.radius)?p.radius:12);set('containerBorderWidth',Number.isInteger(p.border_width)?p.border_width:0);set('containerBorderColor',/^#[0-9a-f]{6}$/i.test(p.border_color||'')?p.border_color:'#dce8df');set('containerAlign',p.align||'center');set('containerSpacing',Number.isInteger(p.margin_bottom)?p.margin_bottom:18);['sectionPadding','sectionGap','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='containerWidth'?'%':'px');});
 const mobile=p.mobile&&typeof p.mobile==='object'?p.mobile:{};
 $('mobileOverrideEnabled').checked=Object.keys(mobile).length>0;
 $('mobileOverrideControls').hidden=!$('mobileOverrideEnabled').checked;
 populateMobileControls(mobile);
}
 if(editable){
  const props=currentBlock.props||{};
  $('editText').value=props.text||'';$('editText').hidden=currentBlock.type==='image';$('inlineEditToggle').hidden=!['heading','paragraph','button'].includes(currentBlock.type);
  $('editText').maxLength=currentBlock.type==='heading'?500:(currentBlock.type==='button'?120:10000);
  const isButton=currentBlock.type==='button';
  $('editHref').hidden=!isButton;$('editHrefLabel').hidden=!isButton;
  $('editHref').value=isButton?(props.href||''):'';
  const size=Math.max(10,Math.min(72,Number(props.font_size)||(currentBlock.type==='heading'?32:16)));
  $('elementFontSize').value=size;$('elementFontSizeValue').textContent=size+'px';
  $('elementWidth').value=Math.max(10,Math.min(100,Number(props.width)||100));$('elementWidthValue').textContent=$('elementWidth').value+'%';
  $('elementAlign').value=['left','center','right'].includes(props.align)?props.align:'left';
  $('elementSpacing').value=Math.max(0,Math.min(80,Number(props.margin_bottom)||0));$('elementSpacingValue').textContent=$('elementSpacing').value+'px';
  $('elementTextColor').value=/^#[0-9a-f]{6}$/i.test(props.color||'')?props.color:'#222222';
  $('elementBackground').value=/^#[0-9a-f]{6}$/i.test(props.background||'')?props.background:'#ffffff';$('elementBackgroundEnabled').checked=/^#[0-9a-f]{6}$/i.test(props.background||'');
  $('elementPadding').value=Math.max(0,Math.min(48,Number(props.padding)||0));$('elementPaddingValue').textContent=$('elementPadding').value+'px';
  $('elementRadius').value=Math.max(0,Math.min(48,Number(props.radius)||0));$('elementRadiusValue').textContent=$('elementRadius').value+'px';
 }
}
function removeCanvasToolbar(){const doc=frame.contentDocument,toolbar=doc?.getElementById('bhvb-floating-toolbar');if(!toolbar)return;const view=doc.defaultView;if(toolbar._reposition){view?.removeEventListener('scroll',toolbar._reposition,true);view?.removeEventListener('resize',toolbar._reposition);view?.visualViewport?.removeEventListener('resize',toolbar._reposition);}toolbar.remove();}
function moveSelectedBlock(direction){
 if(!currentBlock||!currentSection||!draft)return;
 const items=currentSection.children||[],index=items.findIndex(item=>item.id===currentBlock.id),next=index+direction;
 if(index<0||next<0||next>=items.length)return;
 [items[index],items[next]]=[items[next],items[index]];renderDraft();
 const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(node)selectNode(node);
 draftStatus('Element moved '+(direction<0?'up':'down')+'. Save draft to keep this change.');
}
function deleteSelectedBlock(){
 if(!currentBlock||!currentSection||!draft)return;
 const id=currentBlock.id;currentSection.children=(currentSection.children||[]).filter(item=>item.id!==id);currentBlock=null;renderDraft();
 $('editControls').hidden=true;$('imageControls').hidden=true;draftStatus('Element removed from the private draft. Save draft to keep the change.');
}
function startCanvasTextEditing(node){
 if(!node||!currentBlock||previewMode!=='draft'||!['heading','paragraph','button'].includes(currentBlock.type))return;
 if(inlineEditingNode&&inlineEditingNode!==node){inlineEditingNode.contentEditable='false';inlineEditingNode.removeAttribute('data-bh-inline-editing');}
 inlineEditingNode=node;node.contentEditable='true';node.dataset.bhInlineEditing='true';
 $('inlineEditToggle').hidden=false;$('inlineEditToggle').textContent='Finish editing';
 node.focus();
 try{const selection=node.ownerDocument.defaultView.getSelection(),range=node.ownerDocument.createRange();range.selectNodeContents(node);range.collapse(false);selection.removeAllRanges();selection.addRange(range);}catch(error){}
 draftStatus('Edit the text directly on the page. Select Finish editing when done, then save your draft.');
}
function startCanvasImageEditing(){
 if(!currentBlock||currentBlock.type!=='image'||previewMode!=='draft')return;
 setTab('properties');
 $('propertiesPanel').scrollIntoView({behavior:'smooth',block:'nearest'});
 $('imageControls').hidden=false;
 $('imageUploadStatus').textContent='Choose an image from your library, or close the library to upload a replacement from your device.';
 $('browseMediaLibrary').click();
}
function showCanvasToolbar(node){
 const doc=frame.contentDocument;if(!doc||previewMode!=='draft'||!currentBlock){removeCanvasToolbar();return;}
 removeCanvasToolbar();const toolbar=doc.createElement('div');toolbar.id='bhvb-floating-toolbar';toolbar.setAttribute('role','toolbar');toolbar.setAttribute('aria-label','Selected element actions');
 const actions=[['Edit','Edit selected element on the page',()=>{if(currentBlock&&['heading','paragraph','button'].includes(currentBlock.type)){startCanvasTextEditing(node);}else if(currentBlock?.type==='image'){startCanvasImageEditing();}else{setTab('properties');$('propertiesPanel').scrollIntoView({behavior:'smooth',block:'nearest'});}}],['＋','Duplicate element',duplicateSelectedBlock],['↑','Move element up',()=>moveSelectedBlock(-1)],['↓','Move element down',()=>moveSelectedBlock(1)],['×','Delete element',deleteSelectedBlock]];
 actions.forEach(([label,title,action])=>{const button=doc.createElement('button');button.type='button';button.textContent=label;button.title=title;button.setAttribute('aria-label',title);button.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();if(title!=='Edit selected element')scheduleHistoryCapture();action();});toolbar.append(button);});
 doc.body.append(toolbar);
 const reposition=()=>{
  if(!toolbar.isConnected||!node.isConnected)return;
  const rect=node.getBoundingClientRect(),width=toolbar.offsetWidth,height=toolbar.offsetHeight,viewportWidth=doc.documentElement.clientWidth,viewportHeight=doc.documentElement.clientHeight;
  const left=Math.max(8,Math.min(viewportWidth-width-8,rect.left));
  let top=rect.top-height-9;if(top<8)top=Math.min(viewportHeight-height-8,rect.bottom+9);
  toolbar.style.left=Math.max(8,left)+'px';toolbar.style.top=Math.max(8,top)+'px';
 };
 toolbar._reposition=reposition;reposition();
 doc.defaultView?.addEventListener('scroll',reposition,true);doc.defaultView?.addEventListener('resize',reposition);doc.defaultView?.visualViewport?.addEventListener('resize',reposition);
}
function selectNode(node){
 if(!node)return;
 if(inlineEditingNode&&inlineEditingNode!==node){inlineEditingNode.contentEditable='false';inlineEditingNode.removeAttribute('data-bh-inline-editing');inlineEditingNode=null;}
 $('inlineEditToggle').hidden=true;$('inlineEditToggle').textContent='Edit directly on page';
 try{
  const doc=frame.contentDocument;
  doc?.querySelectorAll('.bhvb-selected-preview').forEach(n=>{n.classList.remove('bhvb-selected-preview');n.removeAttribute('data-bh-builder-label');});
  removeCanvasToolbar();
  node.classList.add('bhvb-selected-preview');node.setAttribute('data-bh-builder-label',node.dataset.bhSectionId?'Container':(node.tagName==='IMG'?'Image':node.tagName==='A'||node.tagName==='BUTTON'?'Button / link':node.tagName.toLowerCase()));
  currentBlock=draft&&node.dataset.bhBlockId?findBlock(node.dataset.bhBlockId):null;
  const sectionNode=node.closest('[data-bh-section-id]');currentSection=draft&&sectionNode?findSection(sectionNode.dataset.bhSectionId):null;
  ['addHeading','addParagraph','addButtonBlock','addImageBlock'].forEach(id=>$(id).disabled=!currentSection||previewMode!=='draft');
  updateSelectionDetails(node);
  showCanvasToolbar(node);
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
let duplicateSequence=0;
function freshBuilderId(prefix){duplicateSequence++;return prefix+'-copy-'+Date.now()+'-'+duplicateSequence;}
function duplicateSelectedBlock(){
 if(!currentBlock||!draft)return;
 for(const section of draft.sections||[]){
  const index=(section.children||[]).findIndex(item=>item.id===currentBlock.id);
  if(index<0)continue;
  const copy=JSON.parse(JSON.stringify(currentBlock));copy.id=freshBuilderId('block');
  section.children.splice(index+1,0,copy);currentSection=section;currentBlock=copy;
  renderDraft();
  const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(copy.id)+'"]');
  if(node)selectNode(node);
  draftStatus('Element duplicated. Edit the copy independently, then save your draft.');
  return;
 }
}
function duplicateSelectedContainer(){
 if(!currentSection||!draft)return;
 const index=draft.sections.indexOf(currentSection);if(index<0)return;
 const copy=JSON.parse(JSON.stringify(currentSection));copy.id=freshBuilderId('container');
 (copy.children||[]).forEach(block=>{block.id=freshBuilderId('block');});
 draft.sections.splice(index+1,0,copy);currentSection=copy;currentBlock=null;
 renderDraft();
 const node=frame.contentDocument?.querySelector('[data-bh-section-id="'+CSS.escape(copy.id)+'"]');
 if(node)selectNode(node);
 draftStatus('Container duplicated with all its content and styling. Edit the copy, then save your draft.');
}

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
  const button=document.createElement('button');button.type='button';button.className='bhvb-layer-button';button.style.setProperty('--layer-depth',String(depth));button.textContent=readableName(node);button.setAttribute('aria-label','Select '+readableName(node));button._bhNode=node;button.draggable=previewMode==='draft'&&(!!node.dataset.bhSectionId||!!node.dataset.bhBlockId);if(node.dataset.bhSectionId){button.dataset.sectionId=node.dataset.bhSectionId;button.dataset.layerType='section';}if(node.dataset.bhBlockId){button.dataset.blockId=node.dataset.bhBlockId;button.dataset.sectionId=node.closest('[data-bh-section-id]')?.dataset.bhSectionId||'';button.dataset.layerType='block';}button.addEventListener('click',()=>selectNode(node));parent.append(button);
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
 if(previewMode==='draft'){
  let dragged=null;
  list.querySelectorAll('.bhvb-layer-button[draggable="true"]').forEach(button=>{
   button.addEventListener('dragstart',event=>{
    dragged={type:button.dataset.layerType,blockId:button.dataset.blockId||'',sectionId:button.dataset.sectionId||''};
    button.classList.add('is-dragging');
    event.dataTransfer.effectAllowed='move';
    event.dataTransfer.setData('text/plain',button.dataset.blockId||button.dataset.sectionId||'');
   });
   button.addEventListener('dragover',event=>{
    if(!dragged)return;
    event.preventDefault();event.dataTransfer.dropEffect='move';
    list.querySelectorAll('.bhvb-layer-button.is-drop-target').forEach(item=>item.classList.remove('is-drop-target'));
    button.classList.add('is-drop-target');
   });
   button.addEventListener('drop',event=>{
    if(!dragged)return;
    event.preventDefault();event.stopPropagation();
    const targetType=button.dataset.layerType,targetBlock=button.dataset.blockId||'',targetSection=button.dataset.sectionId||'';
    const after=event.clientY>button.getBoundingClientRect().top+button.getBoundingClientRect().height/2;
    const source={...dragged};dragged=null;
    list.querySelectorAll('.is-dragging,.is-drop-target').forEach(item=>item.classList.remove('is-dragging','is-drop-target'));
    if(source.type==='section'&&targetType==='section'&&source.sectionId!==targetSection){
     const from=(draft.sections||[]).findIndex(item=>item.id===source.sectionId),to=(draft.sections||[]).findIndex(item=>item.id===targetSection);
     if(from>=0&&to>=0){const [section]=draft.sections.splice(from,1);let index=to+(after?1:0);if(from<to)index--;draft.sections.splice(Math.max(0,Math.min(draft.sections.length,index)),0,section);currentSection=section;currentBlock=null;renderDraft();draftStatus('Container reordered. Save draft to keep this change.');}
    }else if(source.type==='block'&&targetType==='section'){
     if(source.blockId!==targetBlock)moveDraftBlock(source.blockId,targetSection,'',false);
    }else if(source.type==='block'&&targetType==='block'&&source.blockId!==targetBlock){
     moveDraftBlock(source.blockId,targetSection,targetBlock,after);
    }
   });
   button.addEventListener('dragend',()=>{dragged=null;list.querySelectorAll('.is-dragging,.is-drop-target').forEach(item=>item.classList.remove('is-dragging','is-drop-target'));});
  });
 }
}
function attachCanvas(){
 try{
  const doc=frame.contentDocument;if(!doc||!doc.body)throw new Error('Page not ready');
  const style=doc.createElement('style');style.textContent='.bhvb-selected-preview{outline:3px solid #0f8b83!important;outline-offset:2px!important;cursor:pointer!important}.bhvb-canvas-handle{opacity:.96}.bhvb-canvas-handle:focus-visible{outline:3px solid #fbbf24;outline-offset:2px}.bhvb-dragging{opacity:.35!important}.bhvb-drag-over{outline:2px dashed #0f766e!important;outline-offset:3px!important}#bhvb-floating-toolbar{position:fixed;z-index:2147483000;display:flex;align-items:center;gap:3px;padding:5px;border:1px solid #cbded2;border-radius:10px;background:#fff;box-shadow:0 5px 20px rgba(25,60,42,.2);font:600 12px/1.2 Arial,sans-serif;white-space:nowrap}#bhvb-floating-toolbar button{min-width:30px;min-height:28px;padding:5px 8px;border:0;border-radius:6px;background:#f1f6f2;color:#28563e;font:700 12px/1.2 Arial,sans-serif;cursor:pointer}#bhvb-floating-toolbar button:hover{background:#dfeee4;color:#17452e}#bhvb-floating-toolbar button:last-child{background:#fff1f1;color:#9a3838}#bhvb-floating-toolbar button:last-child:hover{background:#ffe2e2}';doc.head.appendChild(style);
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
   if(targetBlock||targetSection){
    event.preventDefault();event.dataTransfer.dropEffect='move';
    doc.querySelectorAll('.bhvb-drag-over,.bhvb-drop-before,.bhvb-drop-after').forEach(node=>node.classList.remove('bhvb-drag-over','bhvb-drop-before','bhvb-drop-after'));
    if(targetBlock){const rect=targetBlock.getBoundingClientRect();targetBlock.classList.add(event.clientY>rect.top+rect.height/2?'bhvb-drop-after':'bhvb-drop-before');}
    else targetSection.classList.add('bhvb-drag-over');
   }
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
  doc.addEventListener('dragend',()=>{draggedBlockId='';doc.querySelectorAll('.bhvb-dragging,.bhvb-drag-over,.bhvb-drop-before,.bhvb-drop-after').forEach(node=>node.classList.remove('bhvb-dragging','bhvb-drag-over','bhvb-drop-before','bhvb-drop-after'));});
  doc.addEventListener('input',event=>{
   const node=event.target.closest('[data-bh-inline-editing="true"]');
   if(!node||previewMode!=='draft'||!currentBlock)return;
   const value=node.textContent||'';currentBlock.props.text=value;$('editText').value=value;
   draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Text edited directly on the page. Save draft when ready.');
  },true);
  doc.addEventListener('click',event=>{
   if(event.target.closest('#bhvb-floating-toolbar,.bhvb-canvas-handle'))return;
   if(event.target.closest('[contenteditable="true"]'))return;
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
function safeImageSource(value){const src=String(value||'').trim();if(!src||/[\u0000-\u0020\u007f\\]/.test(src)||src.startsWith('//'))return false;if(src.startsWith('/'))return !src.startsWith('/\\');try{const u=new URL(src);return u.protocol==='https:'&&!!u.hostname&&!u.username&&!u.password;}catch(error){return false;}}
function renderDraft(){
 if(!draft)return;
 previewMode='draft';
 const sections=(draft.sections||[]).map(section=>{
  const children=(section.children||[]).map(block=>{
   const p=block.props||{},id=esc(block.id);
   const size=Math.max(10,Math.min(72,Number(p.font_size)|| (block.type==='heading'?32:16))),width=Math.max(10,Math.min(100,Number(p.width)||100)),align=['left','center','right'].includes(p.align)?p.align:'left',spacing=Math.max(0,Math.min(80,Number(p.margin_bottom)||0));
   const color=/^#[0-9a-f]{6}$/i.test(p.color||'')?p.color:'#222222',background=/^#[0-9a-f]{6}$/i.test(p.background||'')?p.background:'transparent',radius=Math.min(48,Math.max(0,Number(p.radius)||0)),padding=Math.min(48,Math.max(0,Number(p.padding)||0));const elementStyle='font-size:'+size+'px;text-align:'+align+';width:'+width+'%;max-width:100%;margin:0 0 '+spacing+'px;color:'+color+';background:'+background+';border-radius:'+radius+'px;padding:'+padding+'px;box-sizing:border-box;cursor:grab';
   if(block.type==='heading'){const level=Math.max(1,Math.min(4,Number(p.level)||2));return '<h'+level+' draggable="true" data-bh-block-id="'+id+'" style="'+elementStyle+'">'+esc(p.text)+'</h'+level+'>';}
   if(block.type==='paragraph')return '<p draggable="true" data-bh-block-id="'+id+'" style="'+elementStyle+'">'+esc(p.text)+'</p>';
   if(block.type==='image'&&safeImageSource(p.src))return '<img draggable="true" data-bh-block-id="'+id+'" src="'+esc(p.src)+'" alt="'+esc(p.alt||'')+'" loading="lazy" style="display:block;width:'+width+'%;max-width:100%;height:auto;object-fit:'+(p.fit==='contain'?'contain':'cover')+';object-position:center;border-radius:'+Math.min(48,Math.max(0,Number(p.radius)||0))+'px;margin:0 0 '+spacing+'px;cursor:grab">';
   if(block.type==='button'&&safeHref(p.href))return '<p style="margin:0 0 '+spacing+'px;text-align:'+align+'"><a class="cta" draggable="true" data-bh-block-id="'+id+'" href="'+esc(p.href)+'" style="display:inline-block;font-size:'+size+'px;width:'+width+'%;max-width:100%;color:'+color+';background:'+background+';border-radius:'+radius+'px;padding:'+Math.max(8,padding)+'px;box-sizing:border-box;cursor:grab">'+esc(p.text)+'</a></p>';
   return '';
  }).join('');
  const p=section.props||{};const sectionColumns=Math.max(1,Math.min(4,Number(p.columns)||1));const columnWidths=normalizedColumnWidths(p.column_widths,sectionColumns);const columnTemplate=columnWidths.map(v=>'minmax(0,'+v+'fr)').join(' ');const w=Math.min(100,Math.max(25,Number(p.width??100))),h=Math.min(800,Math.max(0,Number(p.min_height??0))),rad=Math.min(48,Math.max(0,Number(p.radius??12))),bw=Math.min(8,Math.max(0,Number(p.border_width??0))),al=['left','center','right'].includes(p.align)?p.align:'center',bc=/^#[0-9a-f]{6}$/i.test(p.border_color||'')?p.border_color:'#dce8df';
  return '<section data-bh-section-id="'+esc(section.id)+'" style="display:grid;grid-template-columns:'+columnTemplate+';grid-auto-flow:row;align-items:start;width:'+w+'%;min-height:'+h+'px;align-self:'+(al==='left'?'flex-start':al==='right'?'flex-end':'center')+';margin:0 0 '+Math.min(96,Math.max(0,Number(p.margin_bottom??18)))+'px;padding:'+Math.min(96,Math.max(0,Number(p.padding??24)))+'px;gap:'+Math.min(64,Math.max(0,Number(p.gap??16)))+'px;color:'+( /^#[0-9a-f]{6}$/i.test(p.color||'')?p.color:'#222222')+';background:'+( ({meadow:'linear-gradient(135deg,#e4f0e6 0%,#b9d7c2 100%)',coastal:'linear-gradient(135deg,#e0f2f1 0%,#a8d6d0 100%)',sunset:'linear-gradient(135deg,#fff0df 0%,#f6d2bd 100%)',berry:'linear-gradient(135deg,#f6e9f1 0%,#dfc3d8 100%)',sand:'linear-gradient(135deg,#f8f3e8 0%,#e8dfc9 100%)'})[p.gradient]||(/^#[0-9a-f]{6}$/i.test(p.background||'')?p.background:'#ffffff'))+';border:'+bw+'px solid '+bc+';border-radius:'+rad+'px">'+children+'</section>';
 }).join('');
 const mobileCss=mobileOverridesCss();
 const columns=Math.max(1,Math.min(3,Number(draft.settings?.columns)||1));
 $('pageColumns').value=String(columns);$('pageColumns').disabled=false;
 const title=esc(draft.page?.title||page?.title||'Draft preview');
 const hasPageHeading=(draft.sections||[]).some(section=>(section.children||[]).some(block=>block.type==='heading'&&Number(block.props?.level)===1));
 const titleMarkup=hasPageHeading?'':'<h1 style="grid-column:1/-1">'+title+'</h1>';
 frame.srcdoc='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><style>*{box-sizing:border-box}body{margin:0;padding:32px;background:#f4f8f7;color:#17221c;font:16px Calibri,Arial,sans-serif}main{max-width:960px;margin:auto;display:grid;grid-template-columns:repeat('+columns+',minmax(0,1fr));gap:18px;align-items:start}h1,h2,h3,h4{font-family:Quicksand,Arial,sans-serif;color:#244d37}section{margin:0 0 18px;border-radius:14px}p{line-height:1.7}.cta{display:inline-block;padding:12px 20px;background:#416651;color:white;border-radius:999px;text-decoration:none;font-weight:700}@media(max-width:600px){body{padding:14px}main{grid-template-columns:1fr!important;gap:12px!important}main>section{grid-template-columns:1fr!important}}</style><style id="bhvb-mobile-overrides">'+mobileCss+'</style></head><body><main>'+titleMarkup+sections+'</main></body></html>';
 $('canvasEmpty').hidden=true;frame.hidden=false;
 $('canvasDimensions').textContent=(page?.title||title)+' · '+device+' · Draft';
 $('saveDraft').disabled=false;$('addContainer').disabled=false;document.querySelectorAll('[data-section-preset]').forEach(button=>button.disabled=false);
}

const builderUndoStack=[],builderRedoStack=[];
let historyTimer=null,historyBefore=null,historyApplying=false;
function historySnapshot(){return draft?JSON.stringify(draft):'';}
function updateHistoryButtons(){
 const undo=$('builderUndo'),redo=$('builderRedo');
 if(undo)undo.disabled=!builderUndoStack.length;
 if(redo)redo.disabled=!builderRedoStack.length;
}
function resetBuilderHistory(){builderUndoStack.length=0;builderRedoStack.length=0;historyBefore=null;clearTimeout(historyTimer);historyTimer=null;updateHistoryButtons();}
function recordHistory(before,after){
 if(!before||!after||before===after||historyApplying)return;
 builderUndoStack.push(before);
 if(builderUndoStack.length>60)builderUndoStack.shift();
 builderRedoStack.length=0;updateHistoryButtons();
}
function scheduleHistoryCapture(){
 if(previewMode!=='draft'||!draft||historyApplying)return;
 if(!historyBefore)historyBefore=historySnapshot();
 clearTimeout(historyTimer);
 historyTimer=setTimeout(()=>{
  const before=historyBefore;historyBefore=null;historyTimer=null;
  recordHistory(before,historySnapshot());
 },450);
}
function applyHistorySnapshot(source,target,message){
 if(!source.length)return;
 const current=historySnapshot();target.push(current);
 const previous=source.pop();historyApplying=true;
 try{draft=JSON.parse(previous);currentBlock=null;currentSection=null;inlineEditingNode=null;renderDraft();updateHistoryButtons();draftStatus(message+' Save draft to keep this version.');}
 finally{historyApplying=false;}
}
function undoBuilderChange(){if(historyTimer){clearTimeout(historyTimer);historyTimer=null;recordHistory(historyBefore,historySnapshot());historyBefore=null;}applyHistorySnapshot(builderUndoStack,builderRedoStack,'Last change undone.');}
function redoBuilderChange(){if(historyTimer){clearTimeout(historyTimer);historyTimer=null;recordHistory(historyBefore,historySnapshot());historyBefore=null;}applyHistorySnapshot(builderRedoStack,builderUndoStack,'Change restored.');}
document.addEventListener('input',event=>{if(event.target.closest('.bhvb-inspector')||event.target.id==='pageColumns')scheduleHistoryCapture();},true);
document.addEventListener('change',event=>{if(event.target.closest('.bhvb-inspector')||event.target.id==='pageColumns')scheduleHistoryCapture();},true);
document.addEventListener('click',event=>{if(event.target.closest('.bhvb-builder-actions,.bhvb-add-controls,[data-bh-add],#duplicateElement,#duplicateContainer,#moveContainerUp,#moveContainerDown,#deleteContainer,[data-section-preset]'))scheduleHistoryCapture();},true);
$('builderUndo').addEventListener('click',undoBuilderChange);
$('builderRedo').addEventListener('click',redoBuilderChange);

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
 main.querySelectorAll('img').forEach((img,index)=>{const src=img.getAttribute('src')||'';if(safeImageSource(src)){children.push({id:'image-'+(index+1),type:'image',props:{src,alt:(img.getAttribute('alt')||'').slice(0,300),width:100,fit:'cover',radius:0}});}else skipped++;});
 const images=main.querySelectorAll('img').length;
 draft={schema_version:1,page:{title:page.title,slug:page.slug},settings:{max_width:'960px',columns:1},sections:[{id:'main-content',type:'section',props:{padding:24,gap:16},children}]};resetBuilderHistory();
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
  if(data.draft){draft=data.draft;resetBuilderHistory();renderDraft();draftStatus('Existing unpublished draft loaded. You can edit and save it.');}
  else{draft=null;resetBuilderHistory();previewMode='published';draftStatus('No builder draft exists yet. Prepare a simplified draft from this page to begin.');}
 }catch(error){draftStatus(error.message);$('saveDraft').disabled=true;}
}
async function loadPage(slug){
 page=pages.find(item=>item.slug===slug)||null;draft=null;resetBuilderHistory();previewMode='published';currentBlock=null;currentSection=null;layerNodes=[];
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
 try{const data=await api('/api/admin-visual-page-drafts.php',{action:'save_draft',page_id:page.id,document:draft});draft=data.document||draft;resetBuilderHistory();draftStatus(data.message||'Draft saved.');await loadDraft();}
 catch(error){draftStatus(error.message);}
 finally{$('saveDraft').disabled=false;}
});
$('restoreRevision').addEventListener('click',async()=>{
 const revisionId=Number($('revisionSelect').value);if(!revisionId||!page)return;
 if(!window.confirm('Restore this previous builder draft? Your current draft will first be saved as a revision. The published page will remain unchanged.'))return;
 try{const data=await api('/api/admin-visual-page-drafts.php',{action:'restore_revision',page_id:page.id,revision_id:revisionId});draft=data.document||null;resetBuilderHistory();renderDraft();draftStatus(data.message||'Draft restored.');await loadDraft();}
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
 if(currentBlock.type==='image'){props.src=$('imageSource').value.trim();props.alt=$('imageAlt').value.slice(0,300);props.fit=$('imageFit').value;if(!safeImageSource(props.src)){draftHasValidationError=true;$('saveDraft').disabled=true;draftStatus('Image not saved: use a secure https image URL or a site-relative image path.');return;}}
 props.font_size=Number($('elementFontSize').value);props.width=Number($('elementWidth').value);props.align=$('elementAlign').value;props.margin_bottom=Number($('elementSpacing').value);props.color=$('elementTextColor').value;props.background=$('elementBackground').value;props.radius=Number($('elementRadius').value);
 draftHasValidationError=false;$('saveDraft').disabled=false;
 const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');
 if(node){node.textContent=props.text;if(currentBlock.type==='button')node.setAttribute('href',props.href);node.style.fontSize=props.font_size+'px';node.style.width=props.width+'%';node.style.maxWidth='100%';node.style.textAlign=props.align;node.style.marginBottom=props.margin_bottom+'px';if(currentBlock.type==='button'){node.style.display='inline-block';node.parentElement.style.textAlign=props.align;node.parentElement.style.marginBottom=props.margin_bottom+'px';}}
 if(node){$('selectedText').textContent=props.text;$('selectedName').textContent=readableName(node);}
 draftStatus('Unsaved draft changes. Save draft when ready.');
}
$('imageSource').addEventListener('change',()=>{if(!currentBlock||currentBlock.type!=='image')return;currentBlock.props.src=$('imageSource').value.trim();if(!safeImageSource(currentBlock.props.src)){draftHasValidationError=true;$('saveDraft').disabled=true;draftStatus('Enter a secure https image URL or a site-relative image path.');return;}const n=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(n)n.src=currentBlock.props.src;draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Image source changed. Save draft when ready.');});

function applyLibraryImage(src){
 if(!currentBlock||currentBlock.type!=='image')return;
 currentBlock.props.src=src;$('imageSource').value=src;
 const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');
 if(node)node.src=src;
 draftHasValidationError=false;$('saveDraft').disabled=false;
 $('mediaLibrary').hidden=true;$('imageUploadStatus').textContent='Library image selected · Save draft to keep it on the page.';
 draftStatus('Media library image added to the draft. Save draft when ready.');
}
async function saveMediaDetails(name,title,folder,button){
 button.disabled=true;
 try{
  const form=new FormData();form.append('action','metadata');form.append('name',name);form.append('title',title);form.append('folder',folder);
  const response=await fetch('/api/admin-visual-builder-media.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-CSRF-Token':csrf,'Accept':'application/json'},body:form});
  const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Could not save image details.');
  $('mediaLibraryStatus').textContent='Image details saved.';loadMediaLibrary();
 }catch(error){$('mediaLibraryStatus').textContent=error.message;button.disabled=false;}
}
async function loadMediaLibrary(){
 const statusNode=$('mediaLibraryStatus'),grid=$('mediaLibraryGrid'),folderSelect=$('mediaLibraryFolder');
 statusNode.textContent='Loading images…';grid.replaceChildren();
 try{
  const query=$('mediaLibrarySearch').value.trim(),folder=folderSelect.value;
  const params=new URLSearchParams();if(query)params.set('q',query);if(folder)params.set('folder',folder);
  const data=await api('/api/admin-visual-builder-media.php'+(params.toString()?'?'+params.toString():''));
  const currentFolder=folderSelect.value;folderSelect.replaceChildren();
  const allOption=document.createElement('option');allOption.value='';allOption.textContent='All folders';folderSelect.append(allOption);
  (data.folders||[]).forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=value;folderSelect.append(option);});
  folderSelect.value=currentFolder;
  statusNode.textContent=data.total?data.total+' image'+(data.total===1?'':'s')+' found'+(data.total>data.items.length?' · showing the 120 most recent matches':'')+'.':'No uploaded images found yet.';
  data.items.forEach(item=>{
   const card=document.createElement('div');card.className='bhvb-media-item';
   const choose=document.createElement('button');choose.type='button';choose.className='bhvb-media-choose';choose.title='Use this image';
   const image=document.createElement('img');image.src=item.src;image.alt='';image.loading='lazy';
   const details=document.createElement('span');details.textContent=item.width+' × '+item.height+' · '+new Date(item.modified*1000).toLocaleDateString('en-GB');
   choose.append(image,details);choose.addEventListener('click',()=>applyLibraryImage(item.src));
   const title=document.createElement('input');title.type='text';title.maxLength=120;title.placeholder='Image title';title.value=item.title||'';title.setAttribute('aria-label','Image title');
   const folderInput=document.createElement('input');folderInput.type='text';folderInput.maxLength=40;folderInput.placeholder='Folder (e.g. Homepage)';folderInput.value=item.folder||'';folderInput.setAttribute('aria-label','Image folder');
   const save=document.createElement('button');save.type='button';save.className='bhvb-media-save';save.textContent='Save details';save.addEventListener('click',()=>saveMediaDetails(item.name,title.value,folderInput.value,save));
   card.append(choose,title,folderInput,save);grid.append(card);
  });
 }catch(error){statusNode.textContent=error.message;}
}
function closeMediaLibrary(){$('mediaLibrary').hidden=true;$('browseMediaLibrary').focus();}
$('browseMediaLibrary').addEventListener('click',()=>{$('mediaLibrary').hidden=false;loadMediaLibrary();$('mediaLibrarySearch').focus();});
$('closeMediaLibrary').addEventListener('click',closeMediaLibrary);
$('mediaLibrary').addEventListener('click',event=>{if(event.target===$('mediaLibrary'))closeMediaLibrary();});
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!$('mediaLibrary').hidden)closeMediaLibrary();});
$('refreshMediaLibrary').addEventListener('click',loadMediaLibrary);
$('mediaLibrarySearch').addEventListener('change',loadMediaLibrary);
$('mediaLibraryFolder').addEventListener('change',loadMediaLibrary);

$('chooseImageFile').addEventListener('click',()=>$('imageFileUpload').click());
$('imageFileUpload').addEventListener('change',async()=>{const file=$('imageFileUpload').files?.[0];if(!file)return;if(!currentBlock||currentBlock.type!=='image'){ $('imageUploadStatus').textContent='Select an image element in the draft first.';$('imageFileUpload').value='';return;}if(!['image/jpeg','image/png','image/webp','image/gif'].includes(file.type)){ $('imageUploadStatus').textContent='Choose a JPG, PNG, WebP or GIF image.';$('imageFileUpload').value='';return;}if(file.size>8*1024*1024){$('imageUploadStatus').textContent='Images must be 8 MB or smaller.';$('imageFileUpload').value='';return;}const button=$('chooseImageFile');button.disabled=true;$('imageUploadStatus').textContent='Uploading image…';try{const form=new FormData();form.append('image',file);const response=await fetch('/api/admin-visual-builder-media.php',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-CSRF-Token':csrf,'Accept':'application/json'},body:form});const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Image upload failed.');$('imageSource').value=data.src;currentBlock.props.src=data.src;const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(node)node.src=data.src;draftHasValidationError=false;$('saveDraft').disabled=false;$('imageUploadStatus').textContent='Uploaded: '+file.name+' · Save draft to keep this image on the page.';draftStatus('Image uploaded and added to the draft. Save draft when ready.');}catch(error){$('imageUploadStatus').textContent=error.message;}finally{button.disabled=false;$('imageFileUpload').value='';}});$('imageAlt').addEventListener('input',()=>{if(currentBlock?.type!=='image')return;currentBlock.props.alt=$('imageAlt').value.slice(0,300);const n=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(n)n.alt=currentBlock.props.alt;$('saveDraft').disabled=false;draftStatus('Image alternative text updated. Save draft when ready.');});$('imageFit').addEventListener('change',()=>{if(currentBlock?.type!=='image')return;currentBlock.props.fit=$('imageFit').value;const n=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(n)n.style.objectFit=currentBlock.props.fit;$('saveDraft').disabled=false;});
$('inlineEditToggle').addEventListener('click',()=>{if(!currentBlock||previewMode!=='draft'||!['heading','paragraph','button'].includes(currentBlock.type))return;const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(!node)return;if(inlineEditingNode===node){node.contentEditable='false';node.removeAttribute('data-bh-inline-editing');inlineEditingNode=null;$('inlineEditToggle').textContent='Edit directly on page';draftStatus('Inline editing finished. Save draft to keep your changes.');}else{if(inlineEditingNode){inlineEditingNode.contentEditable='false';inlineEditingNode.removeAttribute('data-bh-inline-editing');}inlineEditingNode=node;node.contentEditable='true';node.dataset.bhInlineEditing='true';node.focus();$('inlineEditToggle').textContent='Finish editing';draftStatus('Click into the text on the page and type. Select Finish editing when done.');}});
$('editText').addEventListener('input',applyEdit);$('editHref').addEventListener('change',applyEdit);
function applyElementStyle(){if(!currentBlock||previewMode!=='draft')return;const p=currentBlock.props;p.font_size=Number($('elementFontSize').value);p.padding=Number($('elementPadding').value);p.width=Number($('elementWidth').value);p.align=$('elementAlign').value;p.margin_bottom=Number($('elementSpacing').value);p.color=$('elementTextColor').value;p.background=$('elementBackgroundEnabled').checked?$('elementBackground').value:'';p.radius=Number($('elementRadius').value);$('elementFontSizeValue').textContent=p.font_size+'px';$('elementWidthValue').textContent=p.width+'%';$('elementSpacingValue').textContent=p.margin_bottom+'px';const node=frame.contentDocument?.querySelector('[data-bh-block-id="'+CSS.escape(currentBlock.id)+'"]');if(node){node.style.fontSize=p.font_size+'px';node.style.width=p.width+'%';node.style.maxWidth='100%';node.style.textAlign=p.align;node.style.marginBottom=p.margin_bottom+'px';node.style.color=p.color;node.style.backgroundColor=p.background||'transparent';node.style.borderRadius=p.radius+'px';node.style.padding=p.padding+'px';node.style.boxSizing='border-box';if(currentBlock.type==='image'){node.style.width=p.width+'%';node.style.objectFit=p.fit==='contain'?'contain':'cover';node.style.borderRadius=Math.min(48,Math.max(0,Number(p.radius)||0))+'px';}if(currentBlock.type==='button'){node.style.display='inline-block';node.parentElement.style.textAlign=p.align;node.parentElement.style.marginBottom=p.margin_bottom+'px';}}draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved element styling. Save draft when ready.');}
$('elementPaddingValue').textContent=$('elementPadding').value+'px';['elementFontSize','elementWidth','elementAlign','elementSpacing','elementTextColor','elementBackground','elementBackgroundEnabled','elementPadding','elementRadius'].forEach(id=>$(id).addEventListener('input',applyElementStyle));$('elementRadius').addEventListener('input',()=>{$('elementRadiusValue').textContent=$('elementRadius').value+'px';});
function applySectionEdit(){if(!currentSection||previewMode!=='draft')return;const p=currentSection.props||(currentSection.props={});p.background=$('sectionBackground').value;p.gradient=$('sectionGradient').value;p.color=$('sectionTextColor').value;p.padding=Number($('sectionPadding').value);p.gap=Number($('sectionGap').value);p.columns=Math.max(1,Math.min(4,Number($('containerColumns').value)||1));const widthParts=$('containerColumnWidths').value.split(',').map(v=>Number(v.trim()));p.column_widths=p.columns>1&&widthParts.length===p.columns&&widthParts.every(v=>Number.isFinite(v)&&v>=10&&v<=90)?normalizedColumnWidths(widthParts,p.columns):Array(p.columns).fill(100/p.columns);$('containerColumnWidthsWrap').hidden=p.columns<=1;p.width=Number($('containerWidth').value);p.min_height=Number($('containerHeight').value);p.radius=Number($('containerRadius').value);p.border_width=Number($('containerBorderWidth').value);p.border_color=$('containerBorderColor').value;p.align=$('containerAlign').value;p.margin_bottom=Number($('containerSpacing').value);['sectionPadding','sectionGap','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='containerWidth'?'%':'px');});const node=frame.contentDocument?.querySelector('[data-bh-section-id="'+CSS.escape(currentSection.id)+'"]');if(node){node.style.background=({meadow:'linear-gradient(135deg,#e4f0e6 0%,#b9d7c2 100%)',coastal:'linear-gradient(135deg,#e0f2f1 0%,#a8d6d0 100%)',sunset:'linear-gradient(135deg,#fff0df 0%,#f6d2bd 100%)',berry:'linear-gradient(135deg,#f6e9f1 0%,#dfc3d8 100%)',sand:'linear-gradient(135deg,#f8f3e8 0%,#e8dfc9 100%)'})[p.gradient]||p.background;node.style.color=p.color;node.style.padding=p.padding+'px';node.style.gap=p.gap+'px';node.style.gridTemplateColumns=normalizedColumnWidths(p.column_widths,p.columns).map(v=>'minmax(0,'+v+'fr)').join(' ');node.style.width=p.width+'%';node.style.minHeight=p.min_height+'px';node.style.borderRadius=p.radius+'px';node.style.border=p.border_width+'px solid '+p.border_color;node.style.alignSelf=p.align==='left'?'flex-start':p.align==='right'?'flex-end':'center';node.style.marginBottom=p.margin_bottom+'px';}draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved container changes. Save draft when ready.');}
function applyMobileEdit(){if(!currentSection||previewMode!=='draft'||!$('mobileOverrideEnabled').checked)return;const p=currentSection.props||(currentSection.props={});p.mobile={width:Number($('mobileContainerWidth').value),padding:Number($('mobileContainerPadding').value),gap:Number($('mobileContainerGap').value),min_height:Number($('mobileContainerHeight').value),radius:Number($('mobileContainerRadius').value),border_width:Number($('mobileContainerBorderWidth').value),margin_bottom:Number($('mobileContainerSpacing').value),align:$('mobileContainerAlign').value};['mobileContainerWidth','mobileContainerPadding','mobileContainerGap','mobileContainerHeight','mobileContainerRadius','mobileContainerBorderWidth','mobileContainerSpacing'].forEach(id=>{const o=$(id+'Value');if(o)o.textContent=$(id).value+(id==='mobileContainerWidth'?'%':'px');});updateMobilePreviewStyle();draftHasValidationError=false;$('saveDraft').disabled=false;draftStatus('Unsaved mobile layout changes. Save draft when ready.');}
function toggleMobileOverrides(){if(!currentSection||previewMode!=='draft')return;const p=currentSection.props||(currentSection.props={});if($('mobileOverrideEnabled').checked){p.mobile={width:100,padding:16,gap:12,min_height:0,radius:12,border_width:0,margin_bottom:16,align:'center'};populateMobileControls(p.mobile);$('mobileOverrideControls').hidden=false;applyMobileEdit();}else{delete p.mobile;$('mobileOverrideControls').hidden=true;updateMobilePreviewStyle();draftStatus('Mobile overrides removed. The container now inherits its desktop settings.');}}
['sectionBackground','sectionTextColor','sectionGradient','sectionPadding','sectionGap','containerColumns','containerWidth','containerHeight','containerRadius','containerBorderWidth','containerBorderColor','containerAlign','containerSpacing'].forEach(id=>$(id).addEventListener('input',applySectionEdit));
const bhPalettes={meadow:{background:'#e4f0e6',color:'#244d37',border:'#9fb7a5'},coastal:{background:'#e0f2f1',color:'#164e4b',border:'#8dbdb7'},sunshine:{background:'#fff4d9',color:'#614b20',border:'#e6c77d'},blush:{background:'#f8edf2',color:'#70465b',border:'#d9b8c8'},neutral:{background:'#f4f1e9',color:'#4c493f',border:'#d6cfbd'}};
$('sectionPalette').addEventListener('change',()=>{if(!currentSection||previewMode!=='draft')return;const v=bhPalettes[$('sectionPalette').value];if(!v)return;$('sectionBackground').value=v.background;$('sectionTextColor').value=v.color;$('containerBorderColor').value=v.border;$('sectionGradient').value='';applySectionEdit();currentSection.props.palette=$('sectionPalette').value;draftStatus('Colour theme applied. Save draft when ready.');});
$('sectionGradient').addEventListener('change',()=>{if(!currentSection||previewMode!=='draft')return;currentSection.props.gradient=$('sectionGradient').value;applySectionEdit();});
$('sectionBackground').addEventListener('input',()=>{if($('sectionGradient').value){$('sectionGradient').value='';if(currentSection)currentSection.props.gradient='';applySectionEdit();}});

$('containerColumns').addEventListener('change',()=>{if(!currentSection)return;const count=Math.max(1,Math.min(4,Number($('containerColumns').value)||1));$('containerColumnWidths').value=Array(count).fill(Math.round(100/count)).join(', ');$('containerColumnWidthsWrap').hidden=count<=1;applySectionEdit();});$('containerColumnWidths').addEventListener('change',applySectionEdit);
$('mobileOverrideEnabled').addEventListener('change',toggleMobileOverrides);
['mobileContainerWidth','mobileContainerPadding','mobileContainerGap','mobileContainerHeight','mobileContainerRadius','mobileContainerBorderWidth','mobileContainerSpacing','mobileContainerAlign'].forEach(id=>$(id).addEventListener('input',applyMobileEdit));
function addContainer(){if(!draft)return;draft.sections.push({id:'container-'+Date.now(),type:'section',props:{background:'#ffffff',color:'#222222',padding:24,gap:16,columns:1,width:100,min_height:120,radius:12,border_width:1,border_color:'#dce8df',align:'center',margin_bottom:18},children:[]});renderDraft();draftStatus('New container added. Select it in the canvas or Layers to style it.');}
function addSectionPreset(kind){if(!draft)return;const presets={split:{title:'Two-column feature',columns:2,widths:[35,65],blocks:[{type:'heading',text:'A little introduction',level:2},{type:'paragraph',text:'Add a short introduction here.'},{type:'heading',text:'More to explore',level:2},{type:'paragraph',text:'Add the supporting details here.'}]},features:{title:'Three feature columns',columns:3,widths:[33,34,33],blocks:[{type:'heading',text:'Feature one',level:3},{type:'paragraph',text:'Describe your first feature.'},{type:'heading',text:'Feature two',level:3},{type:'paragraph',text:'Describe your second feature.'},{type:'heading',text:'Feature three',level:3},{type:'paragraph',text:'Describe your third feature.'}]},callout:{title:'Call to action',columns:1,widths:[100],blocks:[{type:'heading',text:'Ready to get started?',level:2},{type:'paragraph',text:'Add a short message to encourage visitors to take the next step.'},{type:'button',text:'Find out more',href:'/index.html'}]}};const preset=presets[kind];if(!preset)return;const id='section-'+Date.now();draft.sections.push({id,type:'section',props:{background:'#e4f0e6',color:'#244d37',padding:28,gap:16,columns:preset.columns,column_widths:preset.widths,width:100,min_height:100,radius:14,border_width:0,border_color:'#9fb7a5',align:'center',margin_bottom:20,palette:'meadow'},children:preset.blocks.map((item,index)=>({id:id+'-block-'+(index+1),type:item.type,props:{text:item.text,...(item.type==='heading'?{level:item.level}:{}),...(item.type==='button'?{href:item.href,background:'#416651',color:'#ffffff',padding:12,radius:8}:{})}}))});currentSection=draft.sections[draft.sections.length-1];currentBlock=null;renderDraft();draftStatus(preset.title+' section added. Edit its text, colours and layout, then save your draft.');}
function addBlock(type){if(!currentSection||!draft)return;const id='block-'+Date.now();let block;if(type==='image')block={id,type,props:{src:'/images/home/hero-family-art.svg',alt:'',width:100,fit:'cover',radius:0}};else if(type==='heading')block={id,type,props:{text:'New heading',level:2}};else if(type==='paragraph')block={id,type,props:{text:'Add your text here.'}};else block={id,type:'button',props:{text:'Learn more',href:'/index.html'}};currentSection.children.push(block);renderDraft();draftStatus('Element added to selected container. Select it in the preview to edit its content.');}
function moveContainer(direction){if(!currentSection||!draft)return;const index=draft.sections.indexOf(currentSection),next=index+direction;if(next<0||next>=draft.sections.length)return;[draft.sections[index],draft.sections[next]]=[draft.sections[next],draft.sections[index]];renderDraft();draftStatus('Container moved. Save draft to keep this order.');}
function deleteContainer(){if(!currentSection||!draft||draft.sections.length<2){draftStatus('A page must keep at least one container.');return;}if(!window.confirm('Delete this container and its contents from the private draft?'))return;draft.sections=draft.sections.filter(section=>section!==currentSection);currentSection=null;renderDraft();$('sectionControls').hidden=true;draftStatus('Container removed from the private draft. Save draft to keep the change.');}
$('pageColumns').addEventListener('change',()=>{if(!draft)return;draft.settings=draft.settings||{};draft.settings.columns=Math.max(1,Math.min(3,Number($('pageColumns').value)||1));renderDraft();draftStatus('Page layout changed to '+draft.settings.columns+' columns. Mobile preview automatically stacks to one column. Save draft when ready.');});
document.querySelectorAll('[data-bh-add]').forEach(button=>button.addEventListener('click',()=>$(button.dataset.bhAdd)?.click()));
$('duplicateElement').addEventListener('click',duplicateSelectedBlock);$('duplicateContainer').addEventListener('click',duplicateSelectedContainer);$('addContainer').addEventListener('click',addContainer);document.querySelectorAll('[data-section-preset]').forEach(button=>button.addEventListener('click',()=>addSectionPreset(button.dataset.sectionPreset)));$('addHeading').addEventListener('click',()=>addBlock('heading'));$('addParagraph').addEventListener('click',()=>addBlock('paragraph'));$('addButtonBlock').addEventListener('click',()=>addBlock('button'));$('addImageBlock').addEventListener('click',()=>addBlock('image'));$('moveContainerUp').addEventListener('click',()=>moveContainer(-1));$('moveContainerDown').addEventListener('click',()=>moveContainer(1));$('deleteContainer').addEventListener('click',deleteContainer);
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