/* Unified directory search: Activities, Events, Businesses & Organisers. */
(function(){
'use strict';
const $=id=>document.getElementById(id);
const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let mode='activities',organisers=null,loading=null;
const fields=()=>document.querySelector('.directory-hero-search-fields');
function keyword(){return $('search')||document.querySelector('.directory-hero-search input[type="search"]');}
function setup(){
 const target=keyword(),parent=target?.closest('label')||target?.parentElement;
 if(!target||!parent||$('bhSearchType'))return false;
 const wrap=document.createElement('label');wrap.className='bh-search-type-field';
 wrap.innerHTML='<span>Search type</span><select id="bhSearchType" aria-label="Search type"><option value="activities">Activities</option><option value="events">Events</option><option value="businesses">Businesses &amp; Organisers</option></select>';
 parent.parentElement.insertBefore(wrap,parent);
 const pane=document.createElement('section');pane.id='bhBusinessResults';pane.className='activity-grid';pane.hidden=true;pane.setAttribute('aria-live','polite');
 const list=$('results');list?.insertAdjacentElement('afterend',pane);
 const originalPlaceholder=target.placeholder;
 const select=$('bhSearchType');
 function refresh(){
   const business=mode==='businesses',event=mode==='events';
   pane.hidden=!business;
   if(business){$('directoryShowList')?.click();pane.hidden=false;list.hidden=true;const map=$('mapView');if(map)map.hidden=true;}
   else if(event){$('directoryShowEvents')?.click();}
   else {$('directoryShowList')?.click();}
   const filter=document.querySelector('.bh-approved-filter-bar');
   if(filter)filter.hidden=business||event;
   target.placeholder=business?'Search businesses or organisers…':event?'Search events…':originalPlaceholder;
   if(business)void renderBusinesses();
   if(event)filterEvents();
 }
 select.addEventListener('change',()=>{mode=select.value;refresh();});
 target.addEventListener('input',()=>{if(mode==='businesses')void renderBusinesses();if(mode==='events')filterEvents();});
 document.querySelector('.directory-hero-search')?.addEventListener('submit',()=>{if(mode==='businesses')void renderBusinesses();if(mode==='events')filterEvents();});
 const observer=new MutationObserver(()=>{if(mode==='businesses'){pane.hidden=false;if(list)list.hidden=true;if($('mapView'))$('mapView').hidden=true;}});
 observer.observe(document.querySelector('.bh-directory-content-grid')||document.body,{attributes:true,subtree:true,attributeFilter:['hidden']});
 const initial=new URLSearchParams(location.search).get('type');
 if(['activities','events','businesses'].includes(initial)){mode=initial;select.value=mode;refresh();}
 return true;
}
async function loadOrganisers(){
 if(organisers)return organisers;
 if(loading)return loading;
 loading=(async()=>{
 const map=new Map();
 for(let page=1;page<=30;page++){
   const response=await fetch('/api/activities.php?per_page=200&page='+page,{credentials:'same-origin'});
   if(!response.ok)throw Error('Unable to load directory data');
   const payload=await response.json();
   if(!payload.ok||!Array.isArray(payload.data))throw Error('Invalid directory data');
   for(const a of payload.data){
     const o=a.organiser||{},name=String(o.name||'').trim();
     if(!name||name==='Bubba Hub organiser')continue;
     const key=o.id?'id:'+o.id:'name:'+name.toLowerCase();
     if(!map.has(key))map.set(key,{name,id:o.id,slug:o.slug,website:o.website,activities:[],towns:new Set(),categories:new Set()});
     const entry=map.get(key);entry.activities.push(a);
     if(a.category)entry.categories.add(a.category);
     for(const v of a.venues||[])if(v.town)entry.towns.add(v.town);
   }
   if(payload.data.length<200||page>=Number(payload.pagination?.pages||page))break;
 }
 organisers=[...map.values()].sort((a,b)=>a.name.localeCompare(b.name));return organisers;
 })();
 try{return await loading;}finally{loading=null;}
}
async function renderBusinesses(){
 const pane=$('bhBusinessResults');if(!pane||mode!=='businesses')return;
 pane.hidden=false;pane.innerHTML='<p>Loading businesses and organisers…</p>';
 try{
 const data=await loadOrganisers();if(mode!=='businesses')return;
 const q=(keyword()?.value||'').trim().toLowerCase();
 const town=($('town')?.value||'').trim().toLowerCase();
 const category=($('category')?.value||'').trim().toLowerCase();
 const matches=data.filter(o=>(!q||[o.name,...o.towns,...o.categories].join(' ').toLowerCase().includes(q))&&(!town||[...o.towns].some(t=>t.toLowerCase().includes(town)))&&(!category||[...o.categories].some(c=>c.toLowerCase().includes(category))));
 pane.replaceChildren();
 const summary=document.createElement('p');summary.className='bh-business-summary';summary.textContent=matches.length+' businesses & organisers found';pane.append(summary);
 for(const o of matches){
 const card=document.createElement('article');card.className='activity-card bh-business-card';
 const names=[...o.towns].slice(0,5).join(', '),cats=[...o.categories].slice(0,4).join(' · ');
 const href='/organiser/'+encodeURIComponent(o.slug||o.name.toLowerCase().replace(/[^a-z0-9]+/g,'-'));
 card.innerHTML='<div class="bh-business-card-content"><h3>'+escape(o.name)+'</h3><p>'+escape(names||'Devon & Cornwall')+'</p><p>'+escape(cats)+'</p><p><strong>'+o.activities.length+' activities</strong></p><a class="button button-primary" href="'+href+'">View organiser</a> <button type="button" class="button button-soft bh-business-list-toggle" aria-expanded="false">View listings</button><div class="bh-business-listings" hidden></div></div>';
 const button=card.querySelector('button'),details=card.querySelector('.bh-business-listings');
 button.addEventListener('click',()=>{details.hidden=!details.hidden;button.setAttribute('aria-expanded',String(!details.hidden));if(!details.hidden){details.replaceChildren();for(const a of o.activities){const link=document.createElement('a');link.href='/activity.html?id='+encodeURIComponent(a.id);link.textContent=a.title||'Activity';details.append(link);}}});
 pane.append(card);
 }
 if(!matches.length){const empty=document.createElement('p');empty.textContent='No matching businesses or organisers found.';pane.append(empty);}
 }catch(e){pane.textContent='Businesses and organisers are temporarily unavailable. Please try again.';console.error(e);}
}
function filterEvents(){
 const list=$('directoryEventsList');if(!list)return;
 const q=(keyword()?.value||'').trim().toLowerCase();
 for(const card of list.querySelectorAll('.directory-event-card'))card.hidden=!!q&&!card.textContent.toLowerCase().includes(q);
}
const boot=()=>{if(setup())return;const observer=new MutationObserver(()=>{if(setup())observer.disconnect();});observer.observe(document.body,{subtree:true,childList:true});setTimeout(()=>observer.disconnect(),12000);};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();