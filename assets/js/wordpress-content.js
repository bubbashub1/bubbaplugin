(function(){
'use strict';
function plain(html){const t=document.createElement('template');t.innerHTML=html||'';return t.content.textContent||'';}
function fragment(html){const t=document.createElement('template');t.innerHTML=html||'';t.content.querySelectorAll('script,object,embed,base,meta,link').forEach(e=>e.remove());t.content.querySelectorAll('*').forEach(e=>{for(const a of [...e.attributes]){if(/^on/i.test(a.name)||a.name==='srcdoc'||(/^(href|src|action|formaction|xlink:href)$/i.test(a.name)&&/^\s*(javascript|vbscript):/i.test(a.value)))e.removeAttribute(a.name);}if(e.tagName==='IFRAME'){try{const u=new URL(e.src,location.origin);if(u.protocol!=='https:'){e.remove();return;}e.setAttribute('sandbox','allow-scripts allow-same-origin allow-presentation');e.setAttribute('loading','lazy');if(!e.title)e.title='Embedded content';}catch{e.remove();}}});return t.content;}
async function mount(root,options={}){
const type=options.type||root.dataset.wpType||'pages',id=options.id||root.dataset.wpId,slug=options.slug||root.dataset.wpSlug;if(!['pages','posts'].includes(type)||(!id&&!slug)){root.textContent='Choose a WordPress page or article.';return;}
root.setAttribute('aria-busy','true');root.textContent='Loading content…';
try{const route='/wp/v2/'+type+(id?'/'+encodeURIComponent(id):'');const url=new URL('/index.php',location.origin);url.searchParams.set('rest_route',route);if(!id){url.searchParams.set('slug',slug);url.searchParams.set('per_page','1');}const r=await fetch(url,{credentials:'omit',cache:'no-cache',headers:{Accept:'application/json'}});const data=await r.json();if(!r.ok)throw Error('Unable to load WordPress content.');const item=id?data:data[0];if(!item||item.status!=='publish'||item.content?.protected)throw Error('This page is not available publicly.');const title=plain(item.title?.rendered);const heading=document.querySelector(options.titleSelector||'[data-wp-title]');if(heading)heading.textContent=title;const content=fragment(item.content?.rendered);
if(type==='posts'&&root.dataset.wpDocument==='true'){
 document.body.classList.add('bh-article-page');
 const first=content.firstElementChild;
 if(heading&&first?.tagName==='H1'){heading.textContent=first.textContent;first.remove();}
 if(heading&&!document.querySelector('.bh-article-back')){const back=document.createElement('a');back.className='bh-article-back';back.href='/articles';back.textContent='← All articles';heading.before(back);}
}
root.replaceChildren(content);if(root.dataset.wpDocument==='true')document.title=title+' · Bubba Hub';root.dispatchEvent(new CustomEvent('wpcontentloaded',{detail:{id:item.id,type,slug:item.slug}}));}
catch(e){root.textContent=e.message||'Content could not be loaded.';const retry=document.createElement('button');retry.type='button';retry.className='button button-soft';retry.textContent='Try again';retry.addEventListener('click',()=>mount(root,options));root.append(document.createElement('br'),retry);}
finally{root.setAttribute('aria-busy','false');}}
window.BHWordPressContent={mount};
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-wp-content]').forEach(root=>{if(root.dataset.wpSlug||root.dataset.wpId){mount(root);return;}const q=new URLSearchParams(location.search),parts=location.pathname.split('/').filter(Boolean);const type=q.get('type')||(parts[0]==='articles'?'posts':'pages');const slug=q.get('slug')||(parts[0]==='promo'?'promo':parts[1]);mount(root,{type,slug,id:q.get('id')});});});
})();