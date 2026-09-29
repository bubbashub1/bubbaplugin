document.addEventListener("DOMContentLoaded",async()=>{
  const count=document.getElementById("plannerCount");
  const bookingCount=document.getElementById("bookingCount");
  const root=document.getElementById("bookingResults");
  const esc=window.bhEscape||((x)=>String(x??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m])));
  try{
    const auth=await fetch("../api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
    if(!auth.authenticated){
      count.textContent="Sign in required";
      bookingCount.textContent="Sign in required";
      root.innerHTML='<div class="hub-empty"><strong>Sign in to view your bookings</strong><p>Your planner and booking history are connected to your Bubba Hub account.</p><a class="button button-primary" href="../auth.html?next="+encodeURIComponent(location.pathname+location.search+location.hash)>Sign in</a></div>';
      return;
    }
    const data=await fetch("../api/my-hub.php",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}}).then(r=>r.json());
    if(!data.ok)throw new Error(data.message||"Could not load your account data.");
    count.textContent=(data.planner||[]).length+" planned";
    const bookings=Array.isArray(data.bookings)?data.bookings:[];
    bookingCount.textContent=bookings.length+" booking"+(bookings.length===1?"":"s");
    if(!bookings.length){
      root.innerHTML='<div class="account-booking-empty"><strong>No bookings yet</strong><p>When you make a booking through a connected Bubba Hub booking service, it will appear here.</p><a class="button button-soft" href="directory.html">Find activities</a></div>';
      return;
    }
    const formatDate=value=>{
      if(!value)return "Date TBC";
      const d=new Date(String(value).replace(" ","T"));
      return Number.isNaN(d.getTime())?String(value):d.toLocaleDateString("en-GB",{weekday:"short",day:"numeric",month:"short",year:"numeric"});
    };
    root.innerHTML=bookings.map(b=>{
      const title=b.title||"Activity";
      const venue=b.venue_name||"Venue TBC";
      const status=String(b.status||"pending").replace(/_/g," ");
      const quantity=Number(b.quantity||1);
      return '<article class="account-booking-card"><div><span class="eyebrow">'+esc(status)+'</span><h3>'+esc(title)+'</h3><p>'+esc(formatDate(b.starts_at))+(b.ends_at?' · '+esc(formatDate(b.ends_at)):"")+'</p><small>'+esc(venue)+' · '+quantity+' ticket'+(quantity===1?"":"s")+'</small></div><span class="account-booking-id">#'+esc(b.id)+'</span></article>';
    }).join("");
  }catch(error){
    count.textContent="";
    bookingCount.textContent="";
    root.innerHTML='<div class="hub-empty"><strong>Bookings unavailable</strong><p>'+esc(error.message||"Please try again.")+'</p></div>';
  }
});