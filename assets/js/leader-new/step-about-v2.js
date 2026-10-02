(()=>{"use strict";const B=window.BubbaNew,Q=B.Q,S=B.state;
function renderPicker(type){let s=Q(type),chips=Q(type==="categories"?"categoryChips":"tagChips"),opts=Q(type==="categories"?"categoryOptions":"tagOptions"),search=Q(type==="categories"?"categorySearch":"tagSearch"),pool=[...s.options].map(o=>o.value).filter(Boolean);chips.innerHTML=B.vals(s).map(v=>'<span class="bh-selected-chip">'+B.esc(v)+'<button type="button" data-remove="'+B.esc(v)+'" aria-label="Remove '+B.esc(v)+'">×</button></span>').join("");chips.querySelectorAll("[data-remove]").forEach(b=>b.onclick=()=>{[...s.options].find(o=>o.value===b.dataset.remove).selected=false;renderPicker(type);if(type==="categories")Q("categories").dispatchEvent(new Event("change"))});let q=search.value.trim().toLowerCase(),selected=B.vals(s).map(v=>v.toLowerCase());opts.innerHTML=pool.filter(v=>!selected.includes(v.toLowerCase())&&(!q||v.toLowerCase().includes(q))).map(v=>'<button type="button" class="bh-option" data-value="'+B.esc(v)+'">'+B.esc(v)+'</button>').join("")||'<div class="bh-option bh-option-empty">'+(q?"No matches":"No options available yet")+'</div>';opts.querySelectorAll("[data-value]").forEach(b=>b.onclick=()=>{B.addOpt(s,b.dataset.value,true);renderPicker(type);if(type==="categories")Q("categories").dispatchEvent(new Event("change"));search.focus()})}
function setupPicker(type){let root=document.querySelector('[data-picker="'+type+'"]'),input=Q(type==="categories"?"categorySearch":"tagSearch");if(!root||!input)return;const open=()=>{root.classList.add("open");renderPicker(type)};input.onfocus=open;input.onclick=open;input.oninput=open;document.addEventListener("click",e=>{if(!root.contains(e.target))root.classList.remove("open")})}
async function addCustom(type,s,i){
 const value=i.value.trim();if(!value)return;
 try{
  const d=await B.post({action:"add_option",type,value});
  B.addOpt(s,d.value||value,true);i.value="";renderPicker(type);
  if(type==="categories")Q("categories").dispatchEvent(new Event("change"));
 }catch(e){B.say(e.message||"Could not add this option.");}
}
function ageUpdate(){let a=+Q("ageMin").value,b=+Q("ageMax").value;Q("ageOutput").textContent=b>=a?B.age(a)+" to "+B.age(b):"Please check the age range."}
function init(){
 const c=Q("categories"),t=Q("tags");
 c.innerHTML="";t.innerHTML="";
 S.meta.categories.forEach(x=>B.addOpt(c,typeof x==="object"?(x.label||x.name||x.title||x.category||""):x));
 S.meta.tags.forEach(x=>B.addOpt(t,typeof x==="object"?(x.label||x.name||x.title||x.tag||""):x));
 renderPicker("categories");renderPicker("tags");
 Q("ageMin").oninput=ageUpdate;Q("ageMax").oninput=ageUpdate;
 Q("addCategory").onclick=()=>addCustom("categories",c,Q("newCategory"));
 Q("addTag").onclick=()=>addCustom("tags",t,Q("newTag"));
 setupPicker("categories");setupPicker("tags");
 Q("newCategory").onkeydown=e=>{if(e.key==="Enter"){e.preventDefault();Q("addCategory").click()}};
 Q("newTag").onkeydown=e=>{if(e.key==="Enter"){e.preventDefault();Q("addTag").click()}};
 c.onchange=async()=>{
  renderPicker("categories");
  const selected=B.vals(c);
  if(!selected.length){Q("tagSuggestions").innerHTML="";return}
  try{
   let all=[];
   for(const category of selected){
    const r=await fetch(B.API,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"tag_suggestions",category})});
    const d=await r.json();
    all.push(...(d.suggestions||[]));
   }
   const suggestions=B.uniq(all);
   suggestions.forEach(x=>B.addOpt(t,x,true));
   renderPicker("tags");
   Q("tagSuggestions").innerHTML=suggestions.map(x=>'<button class="bh-chip" type="button" data-tag="'+B.esc(x)+'">✓ '+B.esc(x)+'</button>').join("");
   Q("tagSuggestions").querySelectorAll("[data-tag]").forEach(b=>b.onclick=()=>{B.addOpt(t,b.dataset.tag,true);renderPicker("tags")});
  }catch(e){}
 };
 S.renderPicker=renderPicker;
 S.ageUpdate=ageUpdate;
}
B.register("about",init,()=>{let a=+Q("ageMin").value,b=+Q("ageMax").value,p=document.querySelector('input[name="pricing"]:checked');if(!B.vals(Q("categories")).length)return B.say("Please choose at least one category."),false;if(!B.vals(Q("tags")).length)return B.say("Please choose at least one tag."),false;if(!Number.isInteger(a)||!Number.isInteger(b)||a<0||b<a||b>216)return B.say("Please enter a valid age range."),false;if(!p)return B.say("Please choose Free, Per session or Per family."),false;if(p.value!=="free"&&(!Q("price").value||+Q("price").value<0))return B.say("Please enter a price."),false;return true});})();