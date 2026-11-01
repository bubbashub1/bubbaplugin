/* Staging AI listing assistant: suggestions never apply automatically. */
document.addEventListener('DOMContentLoaded',()=>{
 if(location.hostname!=='staging.bubbahub.co.uk')return;
 const form=document.getElementById('newListingForm'),anchor=document.getElementById('bhAutoFillRun');
 if(!form||!anchor)return;
 const panel=document.createElement('details');panel.className='bh-card';panel.style.marginTop='14px';
 panel.innerHTML='<summary><strong>✨ AI Listing Assistant (staging preview)</strong></summary><p>Paste publicly available class details. AI suggests non-booking fields only; choose what to apply.</p><textarea id="bhAiPublicText" rows="6" style="width:100%" maxlength="10000" placeholder="Paste public class description or About Us text"></textarea><p><button type="button" class="button button-soft" id="bhAiSuggest">Suggest listing details</button></p><p id="bhAiStatus" role="status"></p><div id="bhAiSuggestions"></div>';
 anchor.insertAdjacentElement('afterend',panel);
 const status=panel.querySelector('#bhAiStatus'),results=panel.querySelector('#bhAiSuggestions'),button=panel.querySelector('#bhAiSuggest');
 const fields=['title','description','age_range','category','tags','website','contact_email','phone'];
 const fieldMap={contact_email:'email'};
 button.addEventListener('click',async()=>{
  const publicText=panel.querySelector('#bhAiPublicText').value.trim();
  if(!publicText){status.textContent='Paste some public listing information first.';return;}
  button.disabled=true;status.textContent='Generating suggestions…';results.replaceChildren();
  try{
   const auth=await fetch('/api/admin-ai-listing-suggest.php',{credentials:'same-origin'});
   const token=await auth.json();if(!auth.ok||!token.ok)throw Error(token.error||'AI unavailable');
   const response=await fetch('/api/admin-ai-listing-suggest.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-BH-AI-CSRF':token.csrf},body:JSON.stringify({public_text:publicText})});
   const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'AI unavailable');
   let count=0;
   for(const name of fields){
    const value=String(data.suggestions?.[name]||'').trim();if(!value)continue;
    const target=form.elements.namedItem(fieldMap[name]||name);if(!target)continue;
    count++;
    const wrapper=document.createElement('div');wrapper.style.cssText='padding:10px;border-bottom:1px solid #dde8df';
    const title=document.createElement('strong');title.textContent=name.replaceAll('_',' ')+': ';
    const preview=document.createElement('p');preview.textContent=value.slice(0,350)+(value.length>350?'…':'');
    const apply=document.createElement('button');apply.type='button';apply.className='button button-soft';apply.textContent='Use this suggestion';
    apply.addEventListener('click',()=>{
     if(String(target.value||'').trim()&&!confirm('Replace the existing '+name+' value?'))return;
     target.value=value;target.dispatchEvent(new Event('input',{bubbles:true}));target.dispatchEvent(new Event('change',{bubbles:true}));
     apply.textContent='Applied — review in wizard';apply.disabled=true;
    });
    wrapper.append(title,preview,apply);results.append(wrapper);
   }
   status.textContent=count+' suggestion(s) available for individual review. AI does not edit bookings or schedules.';
  }catch(error){status.textContent=error.message||'AI unavailable';}finally{button.disabled=false;}
 });
});