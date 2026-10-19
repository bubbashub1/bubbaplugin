document.addEventListener('DOMContentLoaded',()=>{
const form=document.getElementById('exhibitorForm');if(!form)return;
const grid=form.querySelector('div[style*="grid-template-columns"]');if(!grid)return;
const fields=Array.from(grid.children);
const socials=fields.find(n=>n.querySelector('[name="tiktok_url"]'));
const logoLabel=form.querySelector('[name="logo_url"]')?.closest('label');
const logoUpload=form.querySelector('[data-expo-logo-upload]');
if(socials&&logoLabel&&logoUpload){socials.after(logoLabel,logoUpload);logoLabel.style.gridColumn='1/-1';logoUpload.style.gridColumn='1/-1';}
const groups=[
 {name:'Your business',legend:['1. Business & contact details','2. Website, social media & logo','3. About your business']},
 {name:'Your Expo space',legend:['4. What you will offer','5. Preferred exhibitor table','6. Stall & facilities']},
 {name:'Final details',legend:['7. Optional donation','8. Public liability insurance']},
 {name:'Review & submit',legend:['9. Additional information & agreements']}
];
const sections=groups.map((g,i)=>{const section=document.createElement('section');section.className='expo-wizard-step';section.dataset.step=String(i);section.hidden=i!==0;const h=document.createElement('h2');h.textContent='Step '+(i+1)+' of 4 — '+g.name;section.append(h);return section;});
const intro=document.createElement('div');intro.className='expo-wizard-progress';intro.setAttribute('role','status');intro.setAttribute('aria-live','polite');
form.insertBefore(intro,grid);
const fieldset=(title)=>{const f=document.createElement('fieldset');f.className='expo-wizard-group';const l=document.createElement('legend');l.textContent=title;f.append(l);return f;};
const first=fieldset(groups[0].legend[0]),second=fieldset(groups[0].legend[1]),third=fieldset(groups[0].legend[2]);
const business=fields.filter(n=>n.querySelector('[name="business_name"],[name="contact_name"],[name="email"],[name="phone"]'));
for(const n of business)first.append(n);
for(const n of Array.from(grid.children))second.append(n);
const firstGrid=document.createElement('div');firstGrid.className='expo-wizard-grid';while(first.firstChild&&first.firstChild.tagName!=='LEGEND')firstGrid.append(first.firstChild);
for(const n of Array.from(first.children).filter(n=>n.tagName!=='LEGEND'))firstGrid.append(n);first.append(firstGrid);
const secondGrid=document.createElement('div');secondGrid.className='expo-wizard-grid';while(second.children.length>1)secondGrid.append(second.children[1]);second.append(secondGrid);
sections[0].append(first,second);
const description=form.querySelector('[name="description"]')?.closest('label');if(description)third.append(description);sections[0].append(third);
const fieldsets=Array.from(form.querySelectorAll('fieldset')).filter(f=>!f.classList.contains('expo-wizard-group'));
fieldsets.forEach((f,i)=>{const legends=['4. What you will offer','5. Preferred exhibitor table','6. Stall & facilities','7. Optional donation','8. Public liability insurance'];if(i<5){f.classList.add('expo-wizard-group');f.querySelector('legend').textContent=legends[i];sections[i<3?1:2].append(f);}});
const last=fieldset(groups[3].legend[0]);const extras=form.querySelector('[name="requirements"]')?.closest('label');
const submit=form.querySelector('#expoSubmit');
if(extras&&submit){let n=extras;while(n&&n!==submit){const next=n.nextSibling;last.append(n);n=next;}}
sections[3].append(last);
const buttons=document.createElement('div');buttons.className='expo-wizard-nav';const back=document.createElement('button'),next=document.createElement('button');back.type=next.type='button';back.textContent='← Back';next.textContent='Continue →';buttons.append(back,next);
form.insertBefore(sections[0],grid);for(let i=1;i<4;i++)sections[i-1].after(sections[i]);grid.remove();
const submitStatus=form.querySelector('#expoSubmitStatus');sections[3].append(submit,submitStatus);
sections[3].append(buttons);let current=0;
function render(){sections.forEach((s,i)=>s.hidden=i!==current);intro.textContent='Step '+(current+1)+' of 4 · '+groups[current].name;back.hidden=current===0;next.hidden=current===3;sections[current].append(buttons);window.scrollTo({top:Math.max(0,form.getBoundingClientRect().top+window.scrollY-90),behavior:'smooth'});}
function valid(){for(const input of sections[current].querySelectorAll('input,select,textarea')){if(!input.checkValidity()){input.reportValidity();return false;}}return true;}
back.onclick=()=>{if(current>0){current--;render();}};next.onclick=()=>{if(valid()&&current<3){current++;render();}};
form.addEventListener('submit',e=>{for(let i=0;i<4;i++){for(const input of sections[i].querySelectorAll('input,select,textarea')){if(!input.checkValidity()){current=i;render();input.reportValidity();e.preventDefault();return;}}}},true);
render();
});