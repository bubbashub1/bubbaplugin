document.addEventListener("DOMContentLoaded", async () => {
  "use strict";
  const $ = id => document.getElementById(id);
  const list = $("slotList"), activity = $("bookingActivity"), message = $("bookingMessage");
  let csrf = "";
  const escapeHtml = value => String(value ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  async function api(method="GET", body=null) {
    const response = await fetch("/api/booking-requests.php?view=manager", {
      method, credentials:"same-origin", cache:"no-store",
      headers: body ? {"Content-Type":"application/json","Accept":"application/json"} : {"Accept":"application/json"},
      body: body ? JSON.stringify({...body,csrf}) : undefined
    });
    const data = await response.json().catch(()=>({}));
    if(!response.ok || !data.ok) throw new Error(data.message || data.error || "Booking manager unavailable.");
    if(data.csrf) csrf=data.csrf;
    return data;
  }
  async function load() {
    try {
      const data=await api();
      activity.replaceChildren(new Option("Choose a class",""));
      (data.classes||[]).forEach(c=>activity.add(new Option(c.title,String(c.id))));
      const rows=data.slots||[];
      list.replaceChildren();
      if(!rows.length){list.textContent="No bookable sessions yet. Add your first session below.";return;}
      rows.forEach(s=>{
        const card=document.createElement("article");card.className="leader-booking-slot";
        const info=document.createElement("div");
        const title=document.createElement("strong");title.textContent=s.title;
        const meta=document.createElement("span");meta.textContent=new Date(String(s.starts_at).replace(" ","T")).toLocaleString("en-GB")+" · "+(s.venue_name||"Venue to confirm")+" · "+s.capacity+" places · "+(s.price===null?"Free / not set":"£"+s.price);
        info.append(title,meta);
        const actions=document.createElement("div");
        const state=document.createElement("span");state.textContent=s.status;
        const remove=document.createElement("button");remove.type="button";remove.className="button button-soft";remove.textContent="Close session";
        remove.addEventListener("click",async()=>{if(!confirm("Close this session? Existing bookings must be dealt with first."))return;remove.disabled=true;try{await api("POST",{action:"delete_slot",slot_id:s.id});message.textContent="Session closed.";await load()}catch(e){message.textContent=e.message;remove.disabled=false}});
        actions.append(state,remove);card.append(info,actions);list.append(card);
      });
      message.textContent="Booking sessions are connected to the database.";
    } catch(e) {
      list.textContent=e.message;
      message.textContent=e.message;
    }
  }
  $("addSlot").addEventListener("click",async()=>{
    const activityId=Number(activity.value);
    if(!activityId){message.textContent="Choose a class first.";activity.focus();return;}
    const date=prompt("Session date (YYYY-MM-DD)");
    if(!date)return;
    const time=prompt("Start time (24-hour HH:MM)");
    if(!time)return;
    const end=prompt("End time (YYYY-MM-DD HH:MM) — leave blank if not known")||"";
    const capacity=Number(prompt("Number of places available","10"));
    const price=prompt("Price per place (numbers only; leave blank if free or not set)","")||"";
    const startsAt=date+" "+time;
    try {
      await api("POST",{action:"create_slot",activity_id:activityId,starts_at:startsAt,ends_at:end,capacity,price});
      message.textContent="Session added.";
      await load();
    } catch(e) { message.textContent=e.message; }
  });
  $("saveBooking").addEventListener("click",()=>{message.textContent="Your bookable sessions are saved directly to Bubba Hub."});
  $("refreshExternal")?.addEventListener("click",()=>{message.textContent="External booking URL refresh is not part of this native booking prototype yet."});
  await load();
});