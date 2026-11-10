document.addEventListener("DOMContentLoaded", () => {
  const upcomingRoot = document.getElementById("leaderUpcoming");
  const enquiriesRoot = document.getElementById("leaderRecentEnquiries");
  if (!upcomingRoot || !enquiriesRoot) return;
  const escapeText = value => String(value ?? "");
  const make = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = escapeText(text);
    return node;
  };
  const empty = (root, message, linkText, href) => {
    root.replaceChildren();
    const p = make("p", "leader-home-empty", message);
    if (linkText && href) {
      const a = make("a", "", linkText);
      a.href = href;
      p.append(" ", a);
    }
    root.append(p);
  };
  const dateLabel = value => {
    if (!value) return "";
    const date = value instanceof Date ? value : new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat("en-GB", { day:"numeric", month:"short", year:"numeric" }).format(date);
  };
  const dayIndex = value => {
    const raw = String(value ?? "").trim().toLowerCase();
    // bh_sessions stores ISO-style weekdays: Monday=1 through Sunday=7.
    if (/^[1-7]$/.test(raw)) return Number(raw) % 7;
    const key = raw.slice(0,3);
    const days = ["sun","mon","tue","wed","thu","fri","sat"];
    return days.indexOf(key);
  };
  const timeLabel = value => String(value || "").slice(0,5);
  const renderUpcoming = data => {
    const sessions = [];
    (data.classes || []).forEach(activity => {
      (activity.venues || []).forEach(venue => {
        (venue.sessions || []).forEach(session => {
          const index = dayIndex(session.day_of_week);
          if (index < 0) return;
          const now = new Date();
          const next = new Date(now);
          next.setHours(0,0,0,0);
          let offset = (index - next.getDay() + 7) % 7;
          if (offset === 0) {
            const starts = timeLabel(session.start_time);
            const [hour, minute] = starts.split(":").map(Number);
            const startMinutes = (Number.isFinite(hour) ? hour : 0) * 60 + (Number.isFinite(minute) ? minute : 0);
            const currentMinutes = now.getHours() * 60 + now.getMinutes();
            if (startMinutes <= currentMinutes) offset = 7;
          }
          next.setDate(next.getDate() + offset);
          sessions.push({ title: activity.title, venue: venue.venue_name, day: next, start: session.start_time, end: session.end_time, termOnly: !!Number(session.term_time_only) });
        });
      });
    });
    sessions.sort((a,b) => a.day-b.day || String(a.start).localeCompare(String(b.start)));
    const rows = sessions.slice(0,4);
    if (!rows.length) {
      empty(upcomingRoot, "You haven’t added any session times yet. Add a timetable so families know when your activities run.", "Manage schedule", "/leader/schedule.html");
      return;
    }
    upcomingRoot.replaceChildren();
    rows.forEach(item => {
      const row = make("div","leader-home-row");
      const main = make("div","leader-home-row-main");
      main.append(make("strong","leader-home-row-title",item.title || "Activity"));
      main.append(make("span","leader-home-row-meta",dateLabel(item.day) + " · " + (item.start ? timeLabel(item.start) : "Time TBC") + (item.end ? "–" + timeLabel(item.end) : "") + (item.venue ? " · " + item.venue : "")));
      row.append(main);
      if (item.termOnly) row.append(make("span","leader-home-row-tag","Term time"));
      upcomingRoot.append(row);
    });
  };
  const renderEnquiries = data => {
    const messages = (data.messages || []).slice().sort((a,b) => String(b.created_at || "").localeCompare(String(a.created_at || ""))).slice(0,4);
    if (!messages.length) {
      empty(enquiriesRoot, "No enquiries just yet. New family messages and booking questions will appear here.", "Open messages", "/leader/messages.html");
      return;
    }
    enquiriesRoot.replaceChildren();
    messages.forEach(item => {
      const row = make("div","leader-home-row");
      const main = make("div","leader-home-row-main");
      main.append(make("strong","leader-home-row-title",item.subject || (item.category === "booking" ? "Booking enquiry" : "Family enquiry")));
      main.append(make("span","leader-home-row-meta",(item.sender_email || "Family member") + " · " + dateLabel(item.created_at)));
      row.append(main);
      row.append(make("span","leader-home-row-tag",item.replied_at ? "Replied" : "New"));
      enquiriesRoot.append(row);
    });
  };
  Promise.all([
    fetch("/api/leader-portal.php",{credentials:"same-origin",cache:"no-store"}).then(r=>r.ok?r.json():Promise.reject()),
    fetch("/api/leader-messages.php",{credentials:"same-origin",cache:"no-store"}).then(r=>r.ok?r.json():Promise.resolve({messages:[]}))
  ]).then(([portal,inbox]) => {
    if (!portal || !portal.ok) throw new Error("Could not load leader dashboard data");
    renderUpcoming(portal);
    renderEnquiries(inbox && inbox.ok ? inbox : {messages:[]});
  }).catch(() => {
    empty(upcomingRoot,"Your upcoming activities couldn’t be loaded just now. You can still manage your timetable from the schedule page.","Open schedule","/leader/schedule.html");
    empty(enquiriesRoot,"Your enquiries couldn’t be loaded just now. Open messages to check your inbox.","Open messages","/leader/messages.html");
  });
});