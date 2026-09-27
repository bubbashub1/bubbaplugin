(function(){
  "use strict";
  document.addEventListener("DOMContentLoaded", async function(){
    const root=document.getElementById("personalApp");
    if(!root)return;
    const esc=window.bhEscape||function(x){return String(x??"").replace(/[&<>"']/g,function(m){return ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])})};
    const days=["Any day","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"];
    const budgets=[["Any budget",""],["Free","0"],["Up to £5","5"],["Up to £10","10"],["Up to £15","15"],["Up to £20","20"],["Up to £30","30"]];
    const state={step:1,children:[],activities:[],day:"",region:"",town:"",budget:"",category:"",manualAge:""};
    const pref=(()=>{try{return JSON.parse(localStorage.getItem("bhPreferences")||"{}")}catch{return{}}})();
    state.region=pref.region||"";
    state.town=pref.town||"";
    state.day=pref.day||"";
    state.budget=pref.maxPrice!==undefined?String(pref.maxPrice||""):(pref.freeActivities?"0":"");
    state.category=(Array.isArray(pref.categories)&&pref.categories.length)?pref.categories[0]:"";
    const $=id=>document.getElementById(id);

    function setStep(n){
      state.step=n;
      root.querySelectorAll("[data-panel]").forEach(p=>p.hidden=Number(p.dataset.panel)!==n);
      root.querySelectorAll(".personal-step").forEach(s=>s.classList.toggle("is-active",Number(s.dataset.step)===n));
    }

    function ageYears(date){
      if(!date)return null;
      const parts=String(date).split("-");
      if(parts.length!==3)return null;
      const dob=new Date(Number(parts[0]),Number(parts[1])-1,Number(parts[2]));
      if(Number.isNaN(dob.getTime()))return null;
      const now=new Date();
      let age=now.getFullYear()-dob.getFullYear();
      const birthday=new Date(now.getFullYear(),dob.getMonth(),dob.getDate());
      if(now<birthday)age--;
      const months=(now.getFullYear()-dob.getFullYear())*12+(now.getMonth()-dob.getMonth())-(now.getDate()<dob.getDate()?1:0);
      return Math.max(0, months/12);
    }

    function parseAgeRange(raw){
      const s=String(raw||"").toLowerCase().replace(/–/g,"-").replace(/to/g,"-").trim();
      if(!s)return null;
      const nums=[...s.matchAll(/\d+(?:\.\d+)?/g)].map(m=>Number(m[0]));
      if(!nums.length)return null;
      if(/under/.test(s))return [0,nums[0]];
      if(/\+|plus/.test(s))return [nums[0],9];
      if(nums.length===1)return [nums[0],Math.min(9,nums[0]+1)];
      return [Math.min(nums[0],nums[1]),Math.max(nums[0],nums[1])];
    }

    function activityRanges(a){
      const source=Array.isArray(a.age_range)?a.age_range:[a.age_range];
      return source.map(parseAgeRange).filter(Boolean);
    }

    function ageMatches(a,age){
      if(age===null||age===undefined)return true;
      const ranges=activityRanges(a);
      if(!ranges.length)return true;
      return ranges.some(r=>age>=r[0]-0.01&&age<=r[1]+0.01);
    }

    function sessionDays(a){
      const out=new Set();
      (Array.isArray(a.venues)?a.venues:[]).forEach(v=>(Array.isArray(v.sessions)?v.sessions:[]).forEach(s=>{if(s.day)out.add(String(s.day))}));
      return out;
    }

    function activityPrice(a){
      const n=Number(a.price_value);
      return Number.isFinite(n)?n:null;
    }

    function displayPrice(a){
      const p=activityPrice(a);
      if(p===null)return a.price||"Price on request";
      return p===0?"Free":"From £"+p.toFixed(2);
    }

    function renderChoices(){
      const family=$("familyChoices");
      if(!family)return;
      if(state.children.length){
        family.innerHTML=state.children.map((c,i)=>{
          const age=ageYears(c.date_of_birth);
          return "<label class='personal-choice'><input type='checkbox' data-child-index='"+i+"' checked><span><b>👶 "+esc(c.name||"Child")+"</b><small>"+esc(age===null?"Age not set":age<2?Math.round(age*12)+" months":Math.floor(age)+" years")+"</small></span></label>";
        }).join("");
        family.innerHTML+="<label class='personal-choice'><input type='checkbox' data-all-family checked><span><b>👨‍👩‍👧‍👦 All family</b><small>Match around everyone's ages</small></span></label>";
      }else{
        family.innerHTML="<div class='personal-empty-inline'><strong>No family profiles yet.</strong><span>Add children in My Hub for automatic age matching, or use an age range below.</span></div>";
        $("manualAgeWrap").hidden=false;
      }
      family.querySelectorAll("[data-all-family]").forEach(el=>{
        el.addEventListener("change",function(){
          family.querySelectorAll("[data-child-index]").forEach(c=>c.checked=el.checked);
        });
      });
      family.querySelectorAll("[data-child-index]").forEach(el=>{
        el.addEventListener("change",function(){
          const all=[...family.querySelectorAll("[data-child-index]")];
          const allToggle=family.querySelector("[data-all-family]");
          if(allToggle)allToggle.checked=all.every(c=>c.checked);
        });
      });
    }

    function selectedAges(){
      if(state.children.length){
        const boxes=[...root.querySelectorAll("[data-child-index]:checked")];
        const ages=boxes.map(b=>ageYears(state.children[Number(b.dataset.childIndex)].date_of_birth)).filter(v=>v!==null);
        if(ages.length)return ages;
      }
      const manual=$("manualAge")?.value||"";
      if(manual){
        const p=manual.split("-").map(Number);
        if(p.length===2)return [((p[0]+p[1])/2)];
      }
      return [];
    }

    function localISODate(date){
      const d=new Date(date);
      return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0");
    }
    function shortDate(value){
      if(!value)return "";
      const p=String(value).split("-");
      if(p.length!==3)return value;
      const d=new Date(Number(p[0]),Number(p[1])-1,Number(p[2]));
      return d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short"});
    }
    function updateWhenUI(){
      const today=new Date(),tomorrow=new Date(today);tomorrow.setDate(today.getDate()+1);
      const todayISO=localISODate(today),tomorrowISO=localISODate(tomorrow);
      document.querySelectorAll("[data-when]").forEach(btn=>{
        const k=btn.dataset.when;
        btn.classList.toggle("is-selected",
          (k==="today"&&state.targetDate===todayISO)||
          (k==="tomorrow"&&state.targetDate===tomorrowISO)||
          (k==="weekend"&&state.targetDate==="__weekend__")||
          (k==="any"&&!state.targetDate&&!state.day));
      });
      const tl=document.getElementById("todayLabel"),tml=document.getElementById("tomorrowLabel");
      if(tl)tl.textContent=shortDate(todayISO);
      if(tml)tml.textContent=shortDate(tomorrowISO);
      const input=document.getElementById("personalDate");
      if(input)input.value=state.targetDate.startsWith("__")?"":state.targetDate;
      const selected=document.getElementById("selectedWhen");
      if(selected)selected.textContent=state.targetDate==="__weekend__"?"This weekend":state.targetDate?shortDate(state.targetDate):state.day||"Any day";
    }
    function setTargetDate(value){
      state.targetDate=value||"";
      if(state.targetDate){
        const p=state.targetDate.split("-");
        const d=new Date(Number(p[0]),Number(p[1])-1,Number(p[2]));
        state.day=["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"][d.getDay()];
      }
      updateWhenUI();
    }
    function initWhenChoices(){
      const today=new Date(),tomorrow=new Date(today);tomorrow.setDate(today.getDate()+1);
      document.querySelectorAll("[data-when]").forEach(btn=>btn.addEventListener("click",()=>{
        const k=btn.dataset.when;
        if(k==="today")setTargetDate(localISODate(today));
        else if(k==="tomorrow")setTargetDate(localISODate(tomorrow));
        else if(k==="weekend"){state.targetDate="__weekend__";state.day="";updateWhenUI();}
        else {state.targetDate="";state.day="";updateWhenUI();}
      }));
      document.getElementById("personalDate")?.addEventListener("change",e=>setTargetDate(e.target.value));
      updateWhenUI();
    }
    function sessionOccursOnDate(a,dateValue){
      if(!dateValue||dateValue.startsWith("__"))return true;
      const p=dateValue.split("-");
      if(p.length!==3)return true;
      const d=new Date(Number(p[0]),Number(p[1])-1,Number(p[2]));
      if(Number.isNaN(d.getTime()))return true;
      const dayNo=d.getDay()===0?7:d.getDay();
      const dayName=["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"][d.getDay()];
      const sessions=(Array.isArray(a.venues)?a.venues:[]).flatMap(v=>Array.isArray(v.sessions)?v.sessions:[]);
      return sessions.some(s=>{
        if(Number(s.day_of_week)!==dayNo&&String(s.day||"")!==dayName)return false;
        const from=String(s.start_date||"").trim(),to=String(s.end_date||"").trim();
        return (!from||dateValue>=from)&&(!to||dateValue<=to);
      });
    }

    function renderBudgetChoices(){
      $("budgetChoices").innerHTML=budgets.map(b=>"<button type='button' class='personal-chip "+(state.budget===b[1]?"is-selected":"")+"' data-budget='"+esc(b[1])+"'>"+esc(b[0])+"</button>").join("");
      $("budgetChoices").querySelectorAll("[data-budget]").forEach(btn=>btn.addEventListener("click",function(){
        state.budget=btn.dataset.budget||"";
        renderBudgetChoices();
      }));
    }

    function renderCategories(){
      const cats=[...new Set(state.activities.map(a=>String(a.category||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
      const list=["Anything"].concat(cats);
      $("categoryChoices").innerHTML=list.map(c=>"<button type='button' class='personal-chip "+((!state.category&&c==="Anything")||state.category===c?"is-selected":"")+"' data-category='"+esc(c==="Anything"?"":c)+"'>"+esc(c)+"</button>").join("");
      $("categoryChoices").querySelectorAll("[data-category]").forEach(btn=>btn.addEventListener("click",function(){
        state.category=btn.dataset.category||"";
        renderCategories();
      }));
    }

    function fillRegions(){
      const region=$("personalRegion"),town=$("personalTown");
      const regions=[...new Set(state.activities.map(a=>String(a.region||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
      region.innerHTML="<option value=''>Any region</option>"+regions.map(v=>"<option value='"+esc(v)+"'>"+esc(v)+"</option>").join("");
      region.value=state.region;
      function towns(){
        const towns=[...new Set(state.activities.filter(a=>!region.value||a.region===region.value).map(a=>String(a.town||"").trim()).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
        town.innerHTML="<option value=''>Any town</option>"+towns.map(v=>"<option value='"+esc(v)+"'>"+esc(v)+"</option>").join("");
        town.disabled=!region.value;
        if(state.town&&!towns.includes(state.town))state.town="";
        town.value=state.town;
      }
      region.addEventListener("change",function(){state.region=region.value;state.town="";towns()});
      town.addEventListener("change",function(){state.town=town.value});
      towns();
    }

    function score(a,ages){
      let score=0;
      const days=sessionDays(a),price=activityPrice(a);
      if(state.targetDate==="__weekend__"){
        if(days.has("Saturday")||days.has("Sunday"))score+=26;else score-=38;
      }else if(state.targetDate){
        score+=sessionOccursOnDate(a,state.targetDate)?28:-44;
      }else if(state.day){
        if(days.has(state.day))score+=24;else score-=40;
      }else score+=4;
      if(state.region)score+=a.region===state.region?14:-12;
      if(state.town)score+=a.town===state.town?18:-16;
      if(state.budget!==""){
        if(price===null)score+=2;
        else if(price<=Number(state.budget))score+=16;
        else score-=24;
      }else score+=3;
      if(state.category)score+=String(a.category||"").toLowerCase()===state.category.toLowerCase()?15:0;
      if(ages.length){
        const fits=ages.filter(age=>ageMatches(a,age)).length;
        if(fits===ages.length)score+=38;
        else if(fits)score+=18;
        else score-=28;
      }
      if(window.bhIsSaved&&bhIsSaved(a.id))score+=8;
      if(window.bhIsPlanned&&bhIsPlanned(a.id))score+=5;
      return score;
    }

    function renderResults(){
      const ages=selectedAges();
      const ranked=state.activities.map(a=>({a,s:score(a,ages)})).filter(x=>x.s>-10).sort((x,y)=>y.s-x.s||String(x.a.title).localeCompare(String(y.a.title))).slice(0,8).map(x=>x.a);
      const list=$("personalResultList");
      $("resultsTitle").textContent=ranked.length?"Ideas for your family":"Let's widen the search";
      const pieces=[];
      if(ages.length)pieces.push(ages.length===1?"age match":"age matches");
      if(state.targetDate==="__weekend__")pieces.push("this weekend");
      else if(state.targetDate)pieces.push(shortDate(state.targetDate));
      else if(state.day)pieces.push(state.day);
      if(state.town)pieces.push(state.town);else if(state.region)pieces.push(state.region);
      if(state.category)pieces.push(state.category);
      $("resultsSummary").textContent=ranked.length?"Showing "+ranked.length+" picks · "+(pieces.join(" · ")||"based on your choices"):"We couldn't find enough matches with those choices. Try another day, area or budget.";
      if(!ranked.length){
        list.innerHTML="<div class='personal-no-results'><span>✨</span><h3>Nothing quite fits yet</h3><p>Try changing one of your choices rather than starting over.</p><button type='button' class='button button-soft' id='widenSearch'>Change choices</button></div>";
        $("widenSearch").onclick=()=>{ $("personalResults").hidden=true; setStep(2); window.scrollTo({top:0,behavior:"smooth"}); };
        return;
      }
      list.innerHTML=ranked.map(a=>{
        const saved=window.bhIsSaved?bhIsSaved(a.id):false,planned=window.bhIsPlanned?bhIsPlanned(a.id):false;
        const url=typeof bhActivityUrl==="function"?bhActivityUrl(a):"activity.html?id="+encodeURIComponent(a.id);
        const img=a.image_url?("<img src='"+esc(a.image_url)+"' alt='' loading='lazy'>"):"<div class='personal-result-placeholder'>✦</div>";
        const daysText=[...sessionDays(a)].join(" · ");
        return "<article class='personal-result-card'><div class='personal-result-image'>"+img+"</div><div class='personal-result-body'><span class='personal-result-category'>"+esc(a.category||"Activity")+"</span><h3>"+esc(a.title)+"</h3><p>"+esc(a.town||a.location||"Devon & Cornwall")+" · "+esc(displayPrice(a))+"</p>"+(daysText?"<small>"+esc(daysText)+"</small>":"")+"<div class='personal-result-actions'><a class='button button-soft' href='"+url+"'>View</a><button type='button' class='button button-soft' data-save='"+esc(a.id)+"'>"+(saved?"Saved":"Save")+"</button><button type='button' class='button button-primary' data-plan='"+esc(a.id)+"'>"+(planned?"Planned":"Plan")+"</button></div></div></article>";
      }).join("");
      list.querySelectorAll("[data-save]").forEach(btn=>btn.onclick=()=>{const saved=bhToggleSaved(btn.dataset.save);btn.textContent=saved?"Saved":"Save"});
      list.querySelectorAll("[data-plan]").forEach(btn=>btn.onclick=()=>{const planned=bhTogglePlanned(btn.dataset.plan);btn.textContent=planned?"Planned":"Plan"});
    }

    function showResults(){
      $("personalResults").hidden=false;
      root.querySelectorAll("[data-panel]").forEach(p=>p.hidden=true);
      root.querySelectorAll(".personal-step").forEach(s=>s.classList.remove("is-active"));
      $("personalResults").scrollIntoView({behavior:"smooth",block:"start"});
      renderResults();
    }

    $("changePlan").onclick=()=>{$("personalResults").hidden=true;setStep(1);window.scrollTo({top:0,behavior:"smooth"})};
    root.querySelectorAll("[data-next]").forEach(btn=>btn.addEventListener("click",function(){setStep(Math.min(4,state.step+1));}));
    root.querySelectorAll("[data-back]").forEach(btn=>btn.addEventListener("click",function(){setStep(Math.max(1,state.step-1));}));
    $("findIdeas").onclick=showResults;
    
    try{
      state.activities=await bhActivities();
      renderCategories();fillRegions();initWhenChoices();renderBudgetChoices();
      const auth=window.bhAuthSession?await bhAuthSession():null;
      if(auth?.authenticated){
        const hub=await fetch("api/my-hub.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json()).catch(()=>null);
        if(hub?.ok) state.children=Array.isArray(hub.children)?hub.children:[];
        const prefResponse=await fetch("api/preferences.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json()).catch(()=>null);
        if(prefResponse?.ok&&prefResponse.preferences){
          const saved=prefResponse.preferences;
          state.region=saved.region||state.region;
          state.town=saved.town||state.town;
          state.day=saved.day||state.day;
          state.budget=saved.maxPrice!==undefined?String(saved.maxPrice||""):(saved.freeActivities?"0":state.budget);
          const preferred=Array.isArray(saved.categories)?saved.categories:[];
          state.category=preferred[0]||state.category;
          localStorage.setItem("bhPreferences",JSON.stringify(saved));
          $("personalRegion").value=state.region||"";
        }
      }
      renderChoices();
      fillRegions();
      initWhenChoices();
      renderBudgetChoices();
      renderCategories();
      if($("manualAge"))$("manualAge").addEventListener("change",function(){state.manualAge=this.value});
    }catch(e){
      root.innerHTML="<section class='admin-panel personal-error'><h2>We couldn't load your activity ideas</h2><p>"+esc(e.message||"Please try again.")+"</p><a class='button button-primary' href='directory.html'>Browse the directory</a></section>";
    }
  });
})();