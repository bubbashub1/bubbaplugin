document.addEventListener("DOMContentLoaded",async()=>{
 const params=new URLSearchParams(location.search),key=params.get("section")||"recent";
 const config={
  recent:["Recently Viewed","Activities you have looked at recently."],
  saved:["Saved activities","Your family's saved favourites."],
  visited:["Visited Groups","Groups and activities your family has visited."],
  suggested:["Suggested groups & activities","Ideas selected using your family details and preferences."],
  plans:["My Plans","Your planned activities, shown as a simple list. Open My Planner for your weekly calendar."],
  messages:["My Messages","Your private questions and replies from Bubba Hub."]
 };
 const [title,intro]=config[key]||config.recent;
 document.title=title+" · Bubba Hub";
 document.getElementById("familySectionTitle").textContent=title;
 document.getElementById("familySectionIntro").textContent=intro;
 const root=document.getElementById("familySectionContent");
 const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
 const empty=(heading,body)=>"<div class='hub-empty'><strong>"+heading+"</strong><p>"+body+"</p><a class='button button-soft' href='/directory.html'>Find activities</a></div>";
 const plainText=value=>{const parsed=new DOMParser().parseFromString(String(value??""),"text/html");return (parsed.body.textContent||"").replace(/!\\[([^\\]]*)\\]\\([^)]*\\)/g,"$1").replace(/\\[([^\\]]+)\\]\\([^)]*\\)/g,"$1").replace(/^\\s{0,3}#{1,6}\\s*/gm,"").replace(/[*_~]/g,"").replace(/\\s+/g," ").trim();};
 const truncate=(value,max=150)=>{const valueText=plainText(value);return valueText.length>max?valueText.slice(0,max).trimEnd()+"…":valueText;};
 const placeholder="data:image/svg+xml;charset=UTF-8,"+encodeURIComponent("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'><rect width='400' height='300' fill='#e8f3f1'/><circle cx='200' cy='112' r='48' fill='#c5e2dc'/><path d='M88 260c18-62 59-91 112-91s94 29 112 91' fill='#b5d8d0'/><path d='M178 111h44M200 89v44' stroke='#0f766e' stroke-width='9' stroke-linecap='round'/></svg>");
 try{
  const auth=await fetch("/api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
  if(!auth.authenticated){root.innerHTML=empty("Please sign in","Sign in to see your family activity.");return;}
  if(key==="messages"){
   const d=await fetch("/api/support.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
   const items=Array.isArray(d.questions)?d.questions:[];
   root.innerHTML=items.length?items.map(x=>"<article class='hub-list-card'><div><strong>"+esc(x.subject||"Support question")+"</strong><small>"+esc(x.status||"Submitted")+" · "+esc(x.created_at||"")+"</small><p>"+esc(x.question||"")+"</p>"+(x.answer?"<p><strong>Reply:</strong> "+esc(x.answer)+"</p>":"<small>Awaiting a reply</small>")+"</div></article>").join(""):"<div class='hub-empty'><strong>Your inbox is clear</strong><p>When you send a private question or receive a reply, it will appear here.</p><a class='button button-soft' href='/help-support.html#ask'>Ask a question</a></div>";
   return;
  }
  const hub=await fetch("/api/my-hub.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
  if(!hub.ok)throw Error(hub.message||"Could not load your family space.");
  const activities=await bhActivities();
  const ids=key==="saved"?(hub.saved||[]).map(String):key==="visited"?bhGet(BH_KEYS.visited):key==="recent"?bhGet(BH_KEYS.recent):[];
  let rows=[];
  if(key==="plans") rows=(hub.planner||[]).map(p=>({a:activities.find(a=>String(a.id)===String(p.id)),meta:p.date||p.starts_at||"Planned"})).filter(x=>x.a);
  else if(["saved","visited","recent"].includes(key)) rows=ids.map(id=>activities.find(a=>String(a.id)===String(id))).filter(Boolean).map(a=>({a}));
  else if(key==="suggested"){
   const saved=new Set((hub.saved||[]).map(String)),planned=new Set((hub.planner||[]).map(p=>String(p.id)));
   rows=activities.map(a=>({a,score:(saved.has(String(a.id))?2:0)+(planned.has(String(a.id))?1:0)})).sort((a,b)=>b.score-a.score).slice(0,12);
  }
  const activityCard=({a,meta})=>{
   const location=[a.town||a.location||a.venue_town||"",a.region||""].filter(Boolean).join(", ");
   const age=Array.isArray(a.age_range)?a.age_range.join(", "):String(a.age_range||a.age_group||"");
   const organiser=a.organiser_name||a.organiser||a.group_name||"";
   const description=truncate(a.short_description||a.description||a.summary||"");
   const photo=a.image_url||a.image||a.photo_url||a.photo||a.cover_image||a.image_path||placeholder;
   const href=typeof bhActivityUrl==="function"?bhActivityUrl(a):"/activity.html?id="+encodeURIComponent(a.id);
   const details=(location?"<span>⌖ "+esc(location)+"</span>":"")+(age?"<span>♡ "+esc(age)+"</span>":"")+(organiser?"<span>◉ "+esc(organiser)+"</span>":"");
   return "<article class='hub-list-card hub-activity-card'><a class='hub-activity-thumb' href='"+esc(href)+"' tabindex='-1' aria-hidden='true'><img src='"+esc(photo)+"' alt='' loading='lazy'></a><div class='hub-activity-card-main'><span class='hub-activity-category'>"+esc(a.category||"Family activity")+"</span><strong>"+esc(a.title||"Family activity")+"</strong>"+(description?"<p>"+esc(description)+"</p>":"") +"<div class='hub-activity-details'>"+(details||"<span>⌖ Devon &amp; Cornwall</span>")+"</div>"+(meta?"<small class='hub-activity-meta'>"+esc(meta)+"</small>":"")+"</div><a class='button button-soft' href='"+esc(href)+"'>View details →</a></article>";
  };
  const emptyMessage=key==="saved"?"No saved activities yet":key==="visited"?"No visited groups recorded":key==="recent"?"Nothing viewed yet":key==="plans"?"No plans yet":"No suggestions available";
  const searchable=["saved","visited","recent","suggested","plans"].includes(key);
  if(searchable){
   root.innerHTML="<div class='hub-list-toolbar'><label class='hub-list-search-label' for='hubSectionSearch'>Search this list</label><input id='hubSectionSearch' class='hub-list-search' type='search' placeholder='Search by activity, group or location…' autocomplete='off' aria-controls='hubSectionResults'><span class='hub-list-count' id='hubSectionCount' aria-live='polite'></span></div><div id='hubSectionResults' class='hub-list-results'></div>";
   const search=root.querySelector("#hubSectionSearch"),results=root.querySelector("#hubSectionResults"),count=root.querySelector("#hubSectionCount");
   const pageSize=12;let filteredRows=[],shown=0,observer=null;
   const sentinel=document.createElement("div");sentinel.className="hub-list-scroll-sentinel";sentinel.setAttribute("aria-hidden","true");
   const loadMore=()=>{
    if(shown>=filteredRows.length)return;
    const next=filteredRows.slice(shown,shown+pageSize);
    if(sentinel.isConnected)sentinel.remove();
    const emptyState=results.querySelector(".hub-empty");if(emptyState)emptyState.remove();
    results.insertAdjacentHTML("beforeend",next.map(activityCard).join(""));
    shown+=next.length;
    count.textContent=filteredRows.length?(shown+" of "+filteredRows.length+" shown"):"";
    if(shown<filteredRows.length){
     if("IntersectionObserver" in window){
      if(!sentinel.isConnected)results.append(sentinel);
      if(observer)observer.observe(sentinel);
     }else{
      const more=document.createElement("button");more.type="button";more.className="button button-soft hub-list-load-more";more.textContent="Load more";more.addEventListener("click",()=>{more.remove();loadMore();});results.append(more);
     }
    }else sentinel.remove();
   };
   const render=()=>{
    if(observer){observer.disconnect();observer=null;}
    const q=search.value.trim().toLocaleLowerCase();
    filteredRows=rows.filter(row=>{const a=row.a;const hay=[a.title,a.category,a.town,a.location,a.region,a.organiser_name,a.organiser,a.group_name,a.short_description,a.description,a.summary,a.age_range].flat().filter(Boolean).join(" ").toLocaleLowerCase();return !q||hay.includes(q);});
    shown=0;results.innerHTML="";
    if(!filteredRows.length){
     results.innerHTML="<div class='hub-empty'><strong>"+(rows.length?"No matching activities":emptyMessage)+"</strong><p>"+(rows.length?"Try another name, group or location.":"Explore the directory and your family activity will appear here.")+"</p>"+(rows.length?"":"<a class='button button-soft' href='/directory.html'>Find activities</a>")+"</div>";
     count.textContent="";return;
    }
    loadMore();
    if(sentinel.isConnected&&"IntersectionObserver" in window){
     observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting))loadMore();},{rootMargin:"240px 0px"});
     observer.observe(sentinel);
    }
   };
   search.addEventListener("input",render);render();
  }else{
   root.innerHTML=rows.length?rows.map(activityCard).join(""):empty(emptyMessage,"Explore the directory and your family activity will appear here.");
  }
  if(key==="plans")root.insertAdjacentHTML("beforeend","<p class='hub-section-footer'><a class='button button-primary' href='/planner.html'>Open My Planner →</a></p>");
 }catch(e){root.textContent=e.message||"Could not load this section.";}
});