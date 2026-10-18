/* Embedded Events view for the existing Directory; does not modify its search/map logic. */
(function(){
  const $=id=>document.getElementById(id);
  let active=false,loaded=false;
  const view=()=>$('directoryEventsView');
  function card(title,description,href,date,image){
    const card=document.createElement('article');card.className='directory-event-card';
    if(image){const img=document.createElement('img');img.className='event-image';img.loading='lazy';img.alt='';img.src=image;card.append(img);}
    else{const badge=document.createElement('div');badge.className='event-date';badge.textContent=date||'EVENT';card.append(badge);}
    const body=document.createElement('div');body.className='event-content';
    const h=document.createElement('h3');h.textContent=title;body.append(h);
    const p=document.createElement('p');p.textContent=description;body.append(p);
    const link=document.createElement('a');link.className='button button-primary';link.href=href;link.textContent='View event →';body.append(link);
    card.append(body);return card;
  }
  async function loadEvents(){
    const list=$('directoryEventsList');if(!list)return;
    loaded=true;list.replaceChildren();
    list.append(card('Bubba & Me — Baby & Pregnancy Expo','Saturday 9 January 2027 · 10:30am–2:30pm · Acorn Centre, Torquay · Free entry','/expo.html','9 JAN 2027'));
    try{
      for(let page=1;page<=20;page++){
        const res=await fetch('/api/activities.php?post_type=events&per_page=100&page='+page,{cache:'no-store'});
        if(!res.ok)throw Error('Events API unavailable');
        const payload=await res.json();
        if(!payload.ok)throw Error(payload.error||'Events API error');
        const batch=Array.isArray(payload.data)?payload.data:[];
        for(const event of batch){
          if(String(event.post_type||'').trim().toLowerCase()!=='events')continue;
          const slug=String(event.slug||'').trim();
          const id=String(event.id||'').trim();
          const href=slug?'/activity.html?slug='+encodeURIComponent(slug):'/activity.html?id='+encodeURIComponent(id);
          const description=[event.town,event.day,event.price].filter(Boolean).join(' · ');
          const image=String(event.image_url||event.image||'');
          list.append(card(String(event.title||event.name||'Family event'),description,href,'EVENT',image.startsWith('/')||image.startsWith('https://')?image:null));
        }
        if(batch.length<100)break;
      }
    }catch(err){
      // The featured Expo remains accessible even if the event API is unavailable.
      const note=document.createElement('p');note.className='directory-events-notice';note.textContent='Other events are temporarily unavailable. Please check back soon.';list.append(note);
      console.warn('Directory events:',err);
    }
  }
  function init(){
    const button=$('directoryShowEvents');if(!button)return;
    button.addEventListener('click',()=>{if(!loaded)void loadEvents();});
    if(new URLSearchParams(location.search).get('view')==='events')void loadEvents();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();