document.addEventListener("DOMContentLoaded", async () => {
  "use strict";
  const $ = id => document.getElementById(id);
  const list = $("slotList"), activity = $("bookingActivity"), venue = $("bookingVenue"), message = $("bookingMessage");
  const modeEls = [...document.querySelectorAll('input[name="mode"]')];
  const external = $("externalPanel"), bubba = $("bubbaPanel"), url = $("bookingUrl");
  const form = $("slotForm");
  let csrf = "", classes = [], venues = [], slots = [];
  const selectedMode = () => modeEls.find(x => x.checked)?.value || "bubba";
  const renderMode = () => {
    if (external) external.hidden = selectedMode() !== "external";
    if (bubba) bubba.hidden = selectedMode() === "external";
  };
  const selectedClass = () => classes.find(x => String(x.id) === String(activity.value)) || null;
  function applyClass() {
    const current = selectedClass();
    if (!current) return;
    const externalMode = !!String(current.booking_url || "").trim();
    modeEls.forEach(x => { x.checked = x.value === (externalMode ? "external" : "bubba"); });
    if (url) url.value = current.booking_url || "";
    const options = venues.filter(v => String(v.activity_id) === String(current.id));
    venue.replaceChildren(new Option("Choose a venue (optional)", ""));
    options.forEach(v => venue.add(new Option(v.venue_name + (v.town ? " · " + v.town : ""), String(v.id))));
    renderMode();
    renderSlots();
  }
  async function api(method="GET", body=null) {
    const response = await fetch("/api/booking-requests.php?view=manager", {
      method, credentials:"same-origin", cache:"no-store",
      headers: body ? {"Content-Type":"application/json","Accept":"application/json"} : {"Accept":"application/json"},
      body: body ? JSON.stringify({...body,csrf}) : undefined
    });
    const data = await response.json().catch(()=>({}));
    if(!response.ok || !data.ok) throw new Error(data.message || data.error || "Booking Manager unavailable.");
    if(data.csrf) csrf=data.csrf;
    return data;
  }
  function renderSlots() {
    const current = selectedClass();
    const rows = slots.filter(s => !current || String(s.activity_id) === String(current.id));
    list.replaceChildren();
    if(!rows.length){list.textContent="No bookable sessions yet. Add your first session below.";return;}
    rows.forEach(s=>{
      const card=document.createElement("article");card.className="leader-booking-slot";
      const info=document.createElement("div");
      const title=document.createElement("strong");title.textContent=s.title;
      const capacity=s.capacity===null?"Unlimited places":String(s.capacity)+" places";
      const price=s.price===null?"Free / price not set":"£"+Number(s.price).toFixed(2)+" per place";
      const meta=document.createElement("span");
      meta.textContent=new Date(String(s.starts_at).replace(" ","T")).toLocaleString("en-GB")+" · "+(s.venue_name||"Venue to confirm")+" · "+capacity+" · "+price;
      info.append(title,meta);
      const actions=document.createElement("div");
      const state=document.createElement("span");state.textContent=s.status;
      const remove=document.createElement("button");remove.type="button";remove.className="button button-soft";remove.textContent=s.status==="cancelled"?"Closed":"Close session";
      remove.disabled=s.status==="cancelled";
      remove.addEventListener("click",async()=>{
        if(!confirm("Close this session? Existing active bookings must be dealt with first."))return;
        remove.disabled=true;
        try{await api("POST",{action:"delete_slot",slot_id:s.id});message.textContent="Session closed.";await load()}
        catch(e){message.textContent=e.message;remove.disabled=false}
      });
      actions.append(state,remove);card.append(info,actions);list.append(card);
    });
  }
  async function load() {
    try {
      const data=await api();
      classes=data.classes||[];venues=data.venues||[];slots=data.slots||[];
      const previous=activity.value;
      activity.replaceChildren(new Option("Choose a class",""));
      classes.forEach(c=>activity.add(new Option(c.title,String(c.id))));
      const first=classes.some(c=>String(c.id)===previous)?previous:(classes[0]?String(classes[0].id):"");
      activity.value=first;
      if(first)applyClass();
      else {list.textContent="No classes are linked to your leader account yet.";venue.replaceChildren(new Option("Choose a venue (optional)",""));}
      message.textContent="Booking settings and sessions are connected to your organiser account.";
    } catch(e) {
      list.textContent=e.message;
      message.textContent=e.message;
    }
  }
  activity.addEventListener("change",applyClass);
  modeEls.forEach(x=>x.addEventListener("change",renderMode));
  form.addEventListener("submit",async e=>{
    e.preventDefault();
    const current=selectedClass();
    if(!current){message.textContent="Choose a class first.";activity.focus();return}
    const date=$("slotDate").value,time=$("slotTime").value,endTime=$("slotEndTime").value;
    const capacity=Number($("slotCapacity").value);
    const price=$("slotPrice").value;
    if(!date||!time||!Number.isInteger(capacity)||capacity<1||capacity>500){message.textContent="Enter a valid date, start time and capacity between 1 and 500.";return}
    const startsAt=date+" "+time;
    const endsAt=endTime?date+" "+endTime:"";
    if(endTime&&endTime<=time){message.textContent="The end time must be later than the start time.";return}
    const submit=form.querySelector('button[type="submit"]');submit.disabled=true;
    try{
      await api("POST",{action:"create_slot",activity_id:Number(current.id),venue_id:Number(venue.value||0),starts_at:startsAt,ends_at:endsAt,capacity,price});
      message.textContent="Session added to "+current.title+".";
      form.reset();$("slotCapacity").value="10";await load();
    }catch(err){message.textContent=err.message||"Could not add this session."}
    finally{submit.disabled=false}
  });
  $("saveBooking").addEventListener("click",async()=>{
    const current=selectedClass();
    if(!current){message.textContent="Choose a class first.";activity.focus();return}
    const mode=selectedMode(),bookingUrl=mode==="external"?url.value.trim():"";
    if(mode==="external"&&!bookingUrl){message.textContent="Enter your external booking website address.";url.focus();return}
    const button=$("saveBooking");button.disabled=true;
    try{
      await api("POST",{action:"save_booking_settings",activity_id:Number(current.id),mode,booking_url:bookingUrl});
      current.booking_url=bookingUrl;
      message.textContent=mode==="external"?"External booking link saved to this class.":"Bubba Hub booking requests enabled for this class.";
      renderMode();
    }catch(err){message.textContent=err.message||"Could not save booking settings."}
    finally{button.disabled=false}
  });
  await load();
});