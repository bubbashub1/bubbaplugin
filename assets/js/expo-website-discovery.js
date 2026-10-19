document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-expo-discover]').forEach(button=>{
  button.addEventListener('click',async()=>{
   const container=button.closest('[data-expo-discovery-form]')||document;
   const find=name=>container.querySelector('[name="'+name+'"]');
   const website=find('website')||find('website_url');
   const notice=container.querySelector('[data-expo-discovery-status]');
   if(!website||!website.value.trim()){if(notice)notice.textContent='Enter a website URL first.';return;}
   button.disabled=true;if(notice)notice.textContent='Reading public website details…';
   try{
    const r=await fetch('/api/expo-website-discovery.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({url:website.value.trim()})});
    const j=await r.json();if(!r.ok||!j.ok)throw Error(j.error||'Website lookup failed');
    const fields={logo_url:j.logo_url,facebook_url:j.social_links?.facebook,instagram_url:j.social_links?.instagram,tiktok_url:j.social_links?.tiktok};
    let count=0;for(const [name,value] of Object.entries(fields)){const field=find(name);if(field&&value&&!field.value.trim()){field.value=value;field.dispatchEvent(new Event('input',{bubbles:true}));count++;}}
    if(notice)notice.textContent=count+' suggestions added. Check every link and image before saving. Existing values were kept.';
   }catch(e){if(notice)notice.textContent='Could not read website: '+e.message;}finally{button.disabled=false;}
  });
 });
});