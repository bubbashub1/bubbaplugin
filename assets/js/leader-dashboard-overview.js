
(()=> {
 const esc = value => String(value ?? "").replace(/[&<>"']/g, ch => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[ch]));
 const $ = id => document.getElementById(id);
 const dayOrder = ["sunday","monday","tuesday","wednesday","thursday","friday","saturday"];
 const dayIndex = value => {const v=String(value||"").toLowerCase();const found=dayOrder.indexOf(v);return found<0?99:found;};
 const formatTime = value => {const m=String(value||"").match(/^(\d{1,2}):(\d{2})/);if(!m)return String(value||"Time TBC");let h=Number(m[1]);const suffix=h>=12?"pm":"am";h=h%12||12;return h+":"+m[2]+" "+suffix;};
 const statusLabel = value => ({confirmed:"Confirmed",reserved:"Pending",pending:"Pending",cancelled:"Cancelled",attended:"Attended"}[String(value||"").toLowerCase()]||String(value||"Awaiting update"));
 function renderSchedule(classes){
  const root=$("leaderV3Schedule");if(!root)return;
  const items=(classes||[]).flatMap(c=>(c.venues||[]).flatMap(v=>(v.sessions||[]).map(s=>({...s,classTitle:c.title,venueName:v.venue_name||"Venue"}))));
  items.sort((a,b)=>dayIndex(a.day_of_week)-dayIndex(b.day_of_week)||String(a.start_time||"").localeCompare(String(b.start_time||"")));
  if(!items.length){root.innerHTML="<div class='bh-leader-v3-empty'>No sessions have been added yet. Add session times to your classes to build your schedule.</div>";return;}
  root.innerHTML=items.slice(0,5).map(s=>{const day=String(s.day_of_week||"Day TBC");return "<article class='bh-leader-v3-row'><div class='bh-leader-v3-date'><strong>"+esc(day.slice(0,3))+"</strong><span>Weekly</span></div><div class='bh-leader-v3-row-main'><strong>"+esc(s.classTitle||"Class")+"</strong><small>"+esc(s.venueName)+"</small></div><span class='bh-leader-v3-time'>"+esc(formatTime(s.start_time))+(s.end_time?" – "+esc(formatTime(s.end_time)):"")+"</span></article>";}).join("");
 }
 function renderBookings(bookings){
  const root=$("leaderV3Bookings");if(!root)return;
  const items=(bookings||[]).slice().sort((a,b)=>String(b.created_at||"").localeCompare(String(a.created_at||"")));
  if(!items.length){root.innerHTML="<div class='bh-leader-v3-empty'>No bookings yet. New family reservations will appear here.</div>";return;}
  root.innerHTML=items.slice(0,5).map(b=>"<article class='bh-leader-v3-row'><div class='bh-leader-v3-user-avatar'>"+esc(String(b.email||"F").trim().slice(0,1).toUpperCase())+"</div><div class='bh-leader-v3-row-main'><strong>"+esc(b.title||"Class booking")+"</strong><small>"+esc(b.starts_at||"Session date to be confirmed")+" · "+esc(b.email||"Family")+"</small></div><span class='bh-leader-v3-status "+esc(String(b.status||"").toLowerCase())+"'>"+esc(statusLabel(b.status))+"</span></article>").join("");
 }
 async function init(){
  try{
   const response=await fetch("/api/leader-portal.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"});
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.message||"Your leader overview is temporarily unavailable.");
   const classes=data.classes||[],bookings=data.bookings||[];
   const venues=classes.flatMap(c=>c.venues||[]);
   const sessions=venues.flatMap(v=>v.sessions||[]);
   $("leaderV3Classes").textContent=classes.length;
   $("leaderV3Sessions").textContent=sessions.length;
   $("leaderV3Bookings").textContent=bookings.length;
   $("leaderV3Venues").textContent=venues.length;
   renderSchedule(classes);renderBookings(bookings);
   const name=String(data.organisation?.first_name||data.organisation?.contact_name||data.organisation?.name||"").trim().split(/\s+/)[0];
   $("leaderV3UserName").textContent=name||"Your account";
   $("leaderV3Avatar").textContent=(name||"U").slice(0,1).toUpperCase();
  }catch(error){
   ["leaderV3Classes","leaderV3Sessions","leaderV3Bookings","leaderV3Venues"].forEach(id=>{if($(id))$(id).textContent="—";});
   ["leaderV3Schedule","leaderV3Bookings"].forEach(id=>{if($(id))$(id).innerHTML="<div class='bh-leader-v3-empty'>Sign in as a class leader to see your live information.</div>";});
  }
  try{
   const response=await fetch("/api/leader-messages.php",{credentials:"same-origin",cache:"no-store"});
   const data=await response.json();
   const count=Number(data.unanswered_count||0);
   if(response.ok&&data.ok&&$("leaderV3Messages"))$("leaderV3Messages").textContent=count;
  }catch(e){}
 }
 if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init,{once:true});else init();
})();
