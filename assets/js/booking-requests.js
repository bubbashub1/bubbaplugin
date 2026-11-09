(() => {
  "use strict";
  const endpoint = "/api/booking-requests.php";
  let csrf = "";
  const $ = (id) => document.getElementById(id);
  const safeDate = (value) => {
    if (!value) return "Date to be confirmed";
    const d = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(d.getTime()) ? String(value) : d.toLocaleString("en-GB", {weekday:"short", day:"numeric", month:"short", year:"numeric", hour:"2-digit", minute:"2-digit"});
  };
  const node = (tag, text, className) => {
    const el = document.createElement(tag);
    if (text !== undefined && text !== null) el.textContent = String(text);
    if (className) el.className = className;
    return el;
  };
  async function request(url, options = {}) {
    const response = await fetch(url, {credentials:"same-origin", cache:"no-store", ...options,
      headers: {Accept:"application/json", ...(options.headers || {})}});
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.message || data.error || "The booking service could not be reached.");
    return data;
  }
  function bookingCard(b, leader) {
    const card = node("article", null, "bh-booking-request-card");
    const top = node("div", null, "bh-booking-request-top");
    const title = node("h3", b.title || "Family activity");
    const status = node("span", String(b.status || "pending").replace("_"," "), "bh-booking-status bh-booking-status-" + (b.status || "pending"));
    top.append(title, status);
    const details = node("p", safeDate(b.starts_at) + (b.venue_name ? " · " + b.venue_name : ""));
    const family = node("p", (b.parent_name || "") + (b.parent_email ? " · " + b.parent_email : "") + (b.parent_phone ? " · " + b.parent_phone : ""));
    const child = node("p", "Child: " + (b.child_name || "Not supplied") + (b.child_age ? " · Age " + b.child_age : "") + " · Places: " + (b.quantity || 1));
    const ref = node("small", "Booking reference: " + (b.public_reference || ""));
    card.append(top, details);
    if (leader) card.append(family, child);
    else card.append(child);
    card.append(ref);
    if (leader) {
      if (b.message) card.append(node("p", "Family message: " + b.message));
      const actions = node("div", null, "bh-booking-request-actions");
      const select = document.createElement("select");
      select.setAttribute("aria-label", "Update booking status");
      [["pending","Pending"],["confirmed","Confirmed"],["cancelled","Cancelled"],["attended","Attended"]].forEach(([v,l]) => {
        const o = document.createElement("option"); o.value = v; o.textContent = l; select.append(o);
      });
      select.value = b.status || "pending";
      const save = node("button", "Update status", "button button-soft");
      save.type = "button";
      save.addEventListener("click", async () => {
        save.disabled = true;
        try {
          await request(endpoint, {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({action:"update_status",request_id:b.id,status:select.value,csrf})});
          status.textContent = select.value;
          status.className = "bh-booking-status bh-booking-status-" + select.value;
          save.textContent = "Updated";
        } catch (e) { save.textContent = e.message; }
        finally { save.disabled = false; }
      });
      actions.append(select, save); card.append(actions);
    }
    return card;
  }
  async function init() {
    const list = $("bookingList") || $("bookings");
    if (!list) return;
    const leader = document.body.dataset.leaderPage === "bookings" || location.pathname.includes("/leader/bookings");
    const message = $("portalMessage") || $("bookingStatus");
    list.replaceChildren(node("p", "Loading your bookings…"));
    try {
      const data = await request(endpoint);
      csrf = data.csrf || "";
      list.replaceChildren();
      if (!data.bookings || !data.bookings.length) {
        list.append(node("p", leader ? "No family booking requests yet." : "You haven't made any Bubba Hub bookings yet."));
      } else {
        data.bookings.forEach(b => list.append(bookingCard(b, leader)));
      }
      if (message) message.textContent = data.bookings.length + (leader ? " booking request(s) for your organisation." : " booking(s) on your family account.");
      window.BHBookingRequests = {
        create: async (payload) => {
          if (!csrf) throw new Error("Please refresh the page and try again.");
          return request(endpoint, {method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({...payload,csrf})});
        },
        refresh: init
      };
    } catch (e) {
      list.replaceChildren(node("p", e.message));
      if (message) message.textContent = e.message;
    }
  }
  document.addEventListener("DOMContentLoaded", init);
})();