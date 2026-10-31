(()=>{'use strict';
const $=id=>document.getElementById(id),button=$('bhLeaderWebsiteRun');if(!button)return;
const status=$('bhLeaderWebsiteStatus');
button.addEventListener('click',async()=>{
 const url=String($('bhLeaderWebsiteUrl')?.value||'').trim();
 if(!/^https:\/\//i.test(url)){status.textContent='Enter a public HTTPS website URL.';return;}
 button.disabled=true;status.textContent='Reading website for review…';
 const panel=$('bhLeaderWebsiteSuggestions');panel.hidden=true;
 try{
  const response=await fetch('/api/leader-listing-import.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode:'website',url})});
  const result=await response.json();if(!response.ok||!result.ok)throw Error(result.error||'Website unavailable');
  const data=result.data||{},items=[['title','Class name',data.title],['description','Description',data.description],['bookingUrl','Booking URL',data.booking_url]].filter(item=>item[2]);
  panel.replaceChildren();const heading=document.createElement('h4');heading.textContent='Choose which website details to use';panel.append(heading);
  const checks=[];
  for(const [id,label,value] of items){
   const row=document.createElement('label');row.style.cssText='display:block;margin:10px 0;padding:8px;border-bottom:1px solid #dce8df';
   const check=document.createElement('input');check.type='checkbox';check.checked=false;
   const existing=id==='description'?$('descriptionEditor')?.textContent:$(id)?.value;
   row.append(check,document.createTextNode(' '+label+': '+String(value).slice(0,250)+(existing?' (will replace existing value)':'')));panel.append(row);
   checks.push({id,value,check});
  }
  const apply=document.createElement('button');apply.type='button';apply.className='button button-primary';apply.textContent='Apply selected details';
  apply.addEventListener('click',()=>{let count=0;for(const {id,value,check} of checks){if(!check.checked)continue;
   if(id==='description'){$('descriptionEditor').textContent=value;$('descriptionEditor').dispatchEvent(new Event('input',{bubbles:true}));$('description').value=value;$('description').dispatchEvent(new Event('input',{bubbles:true}));}
   else if($(id)){$(id).value=value;$(id).dispatchEvent(new Event('input',{bubbles:true}));}
   count++;
  }status.textContent=count+' reviewed field(s) applied. Check before saving.';});
  panel.append(apply);panel.hidden=false;status.textContent=items.length+' suggestions ready for review. Nothing was changed automatically.';
 }catch(e){status.textContent=e.message||'Unable to read website.';}finally{button.disabled=false;}
});
})();