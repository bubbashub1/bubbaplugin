document.addEventListener("DOMContentLoaded",async()=>{
 const params=new URLSearchParams(location.search),key=params.get("section")||"recent";
 const config={
  recent:["Recently Viewed","Activities you have looked at recently."],
  saved:["Saved activities","Your family's saved favourites."],
  visited:["Visited Groups","Groups and activities your family has visited."],
  suggested:["Suggested groups & activities","Ideas selected using your family details and preferences."],
  plans:["My Plans","Activities you have added to your family planner."],
  messages:["My Messages","Your private questions and replies from Bubba Hub."]
 };
 const [title,intro]=config[key]||config.recent;
 document.title=title+" · Bubba Hub";
 document.getElementById("familySectionTitle").textContent=title;
 document.getElementById("familySectionIntro").textContent=intro;
 const root=document.getElementById("familySectionContent");
 const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
 const empty=(heading,body)=>"<div class='hub-empty'><strong>"+heading+"</strong><p>"+body+"</p><a class='button button-soft' href='/directory.html'>Find activities</a></div>";
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
  root.innerHTML=rows.length?rows.map(({a,meta})=>"<a class='hub-list-card' href='"+esc(typeof bhActivityUrl==="function"?bhActivityUrl(a):"/activity.html?id="+encodeURIComponent(a.id))+"'><div><span>"+esc(a.category||"Activity")+"</span><strong>"+esc(a.title)+"</strong><small>"+esc(meta||a.town||a.location||"")+"</small></div><b>→</b></a>").join(""):empty(key==="saved"?"No saved activities yet":key==="visited"?"No visited groups recorded":key==="recent"?"Nothing viewed yet":key==="plans"?"No plans yet":"No suggestions available","Explore the directory and your family activity will appear here.");
 }catch(e){root.textContent=e.message||"Could not load this section.";}
});