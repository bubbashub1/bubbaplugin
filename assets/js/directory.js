document.addEventListener("DOMContentLoaded", async () => {
  const $ = id => document.getElementById(id);
  const escapeHtml = value => bhEscape(value);

  try {
    const activities = await bhActivities();
    const params = new URLSearchParams(location.search);

    const categoryMap = {
      "Baby classes": "Baby",
      "Baby & toddler": "Toddler",
      "Family activities": "Family"
    };

    const ageText = value => {
      const n = Number(value);
      if (n === 9) return "9 years";
      const years = Math.floor(n);
      const months = Math.round((n - years) * 12);
      if (years === 0) return months ? `${months} ${months === 1 ? "month" : "months"}` : "0 months";
      if (!months) return `${years} ${years === 1 ? "year" : "years"}`;
      return `${years} ${years === 1 ? "year" : "years"} and ${months} ${months === 1 ? "month" : "months"}`;
    };

    const parseAgeRange = value => {
      const text = String(value || "").toLowerCase().trim();
      const numbers = [...text.matchAll(/(\d+(?:\.\d+)?)/g)].map(m => Number(m[1]));
      if (!numbers.length) return null;
      if (/\+/.test(text)) return { min: numbers[0], max: 9 };
      if (/under|less/.test(text)) return { min: 0, max: numbers[0] };
      if (numbers.length >= 2) return { min: numbers[0], max: numbers[1] };
      return { min: numbers[0], max: numbers[0] };
    };

    const ageMatches = (activity, min, max) => {
      if (min === 0 && max === 9) return true;
      const ranges = Array.isArray(activity.age_range) ? activity.age_range : [activity.age_range];
      return ranges.some(range => {
        const parsed = parseAgeRange(range);
        return parsed && parsed.max >= min && parsed.min <= max;
      });
    };

    const fillSelect = (id, values, selected, label) => {
      const el = $(id);
      el.innerHTML = `<option value="">${label}</option>` +
        values.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join("");
      if (values.includes(selected)) el.value = selected;
    };

    fillSelect(
      "category",
      [...new Set(activities.map(x => categoryMap[x.category] || x.category).filter(Boolean))].sort(),
      params.get("category") || "",
      "All categories"
    );
    fillSelect(
      "area",
      [...new Set(activities.flatMap(x => bhVenues(x).map(v => v.region || x.region)).filter(Boolean))].sort(),
      params.get("region") || "",
      "All regions"
    );
    fillSelect(
      "town",
      [...new Set(activities.flatMap(x => bhVenues(x).map(v => v.town || x.town)).filter(Boolean))].sort(),
      params.get("town") || "",
      "All towns"
    );

    if (params.get("age_min") !== null) $("ageMin").value = params.get("age_min");
    if (params.get("age_max") !== null) $("ageMax").value = params.get("age_max");
    if (params.get("day")) $("day").value = params.get("day");
    if (params.get("free")) $("free").checked = true;
    if (params.get("max_price")) $("maxPrice").value = params.get("max_price");

    // Basic vs advanced search: keep the common fields visible and move deeper filters into a collapsible panel.
    const setupAdvancedSearch = () => {
      const toolbar = $("directoryFilters");
      const toggle = $("filterToggle");
      if (!toolbar || !toggle) return;

      const basicIds = ["search","category","area","town"];
      const advancedIds = ["ageMin","day","maxPrice","free"];
      const advancedWrap = document.createElement("div");
      advancedWrap.className = "directory-advanced-fields";
      advancedWrap.id = "advancedFields";

      advancedIds.forEach(id => {
        const el = $(id);
        if (!el) return;
        const field = el.closest("div, label") || el.parentElement;
        if (field && !advancedWrap.contains(field)) advancedWrap.appendChild(field);
      });

      const extras = [
        ["sessionLength","Session length",[["","Any length"],["60","Up to 1 hour"],["120","1–2 hours"],["180","2–3 hours"],["181","3+ hours"]]],
        ["sen","SEN friendly",[["","Any"],["yes","Yes"],["no","No"]]],
        ["termTime","Term time",[["","Any"],["yes","Term time only"],["no","Not term time only"]]],
        ["booking","Booking",[["","Any"],["yes","Bookable online"],["no","No online booking"]]]
      ];
      extras.forEach(([id,label,options]) => {
        const wrap=document.createElement("div");
        wrap.innerHTML='<label class="directory-filter-label" for="'+id+'">'+label+'</label><select class="filter-input" id="'+id+'">'+options.map(o=>'<option value="'+o[0]+'">'+o[1]+'</option>').join("")+'</select>';
        advancedWrap.appendChild(wrap);
      });

      const heading=document.createElement("div");
      heading.className="advanced-search-heading";
      heading.innerHTML='<span class="eyebrow">Refine your search</span><strong>Find something that fits your family</strong><span>Age, day, price and practical details.</span>';

      const clear=$( "clear" );
      toolbar.insertBefore(heading, toolbar.firstChild);
      toolbar.appendChild(advancedWrap);
      if (clear) advancedWrap.appendChild(clear);

      const advancedFieldEls=[...advancedWrap.querySelectorAll("input,select")];
      toggle.textContent="Advanced search ＋";
      toggle.setAttribute("aria-expanded","false");
      toolbar.hidden=true;
      toggle.onclick=()=>{
        const open=!toolbar.hidden;
        toolbar.hidden=!open;
        toggle.setAttribute("aria-expanded",String(open));
        toggle.innerHTML=open?"Advanced search −":"Advanced search ＋";
      };
      advancedFieldEls.forEach(el=>{
        el.addEventListener("input",render);
        el.addEventListener("change",render);
      });
    };

    setupAdvancedSearch();

    let map = null;
    let markers = [];
    let currentView = localStorage.getItem("bh_directory_view") || "list";
    let calendarMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let cardCount = Number(localStorage.getItem("bh_directory_cards") || 4);
    if (![2,3,4,5,6].includes(cardCount)) cardCount = 4;
    $("cardCount").value = String(cardCount);
    const updateCardCountVisibility = () => {
      const control = document.querySelector(".directory-count-control");
      if (control) control.hidden = currentView !== "grid";
    };
    updateCardCountVisibility();

    const renderAgeTrack = () => {
      const min = Number($("ageMin").value);
      const max = Number($("ageMax").value);
      const fill = $("ageFill");
      if (fill) {
        fill.style.left = `${(min / 9) * 100}%`;
        fill.style.width = `${((max - min) / 9) * 100}%`;
      }
      $("ageValue").textContent = min === 0 && max === 9
        ? "Any age"
        : `${ageText(min)} – ${ageText(max)}`;
    };

    const initMap = () => {
      if (map || !window.L) return;
      map = L.map("mapView").setView([50.42, -3.57], 10);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "© OpenStreetMap contributors"
      }).addTo(map);
    };

    const renderMap = list => {
      initMap();
      if (!map) return;

      markers.forEach(marker => marker.remove());
      markers = [];

      const venueRows = list.flatMap(activity =>
        bhVenues(activity).map(venue => ({ activity, venue }))
      );
      const valid = venueRows.filter(({ venue }) =>
        Number.isFinite(Number(venue.lat)) && Number.isFinite(Number(venue.long))
      );

      const sleekDarkIcon = L.divIcon({
        className: "custom-sleek-dark-marker",
        html: '<div class="bubba-dark-pin"><div class="dark-core"></div></div>',
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36]
      });

      const popupHtml = (activity, venue) => {
        const sessions = bhSessions(activity);
        const session = sessions.find(s => s.venue_id == null || String(s.venue_id) === String(venue.id)) || sessions[0] || {};
        const category = activity.category || "Family activity";
        const age = Array.isArray(activity.age_range) ? activity.age_range.join(" · ") : (activity.age_range || "All ages");
        const price = activity.price || session.price || "Price on request";
        const time = [session.day, session.start && session.start.slice(0,5)].filter(Boolean).join(" · ");
        const image = activity.image_url
          ? '<img class="bh-map-popup-image" src="' + escapeHtml(activity.image_url) + '" alt="' + escapeHtml(activity.title) + '">'
          : '<div class="bh-map-popup-image bh-map-popup-placeholder">Bubba Hub</div>';
        return '<article class="bh-map-popup-card">' +
          image +
          '<div class="bh-map-popup-body">' +
            '<span class="bh-map-popup-category">' + escapeHtml(category) + '</span>' +
            '<h3>' + escapeHtml(activity.title) + '</h3>' +
            '<p class="bh-map-popup-location">📍 ' + escapeHtml(venue.name || venue.town || venue.address || "") + '</p>' +
            '<div class="bh-map-popup-meta">' +
              '<span>👶 ' + escapeHtml(age) + '</span>' +
              '<span>💷 ' + escapeHtml(price) + '</span>' +
              (time ? '<span>🕒 ' + escapeHtml(time) + '</span>' : '') +
            '</div>' +
            '<a class="button button-primary bh-map-popup-link" href="' + bhActivityUrl(activity) + '">View activity →</a>' +
          '</div>' +
        '</article>';
      };

      valid.forEach(({ activity, venue }) => {
        const marker = L.marker([Number(venue.lat), Number(venue.long)], { icon: sleekDarkIcon })
          .addTo(map)
          .bindPopup(popupHtml(activity, venue), {
            maxWidth: 340,
            minWidth: 260,
            className: "bh-map-popup"
          });
        markers.push(marker);
      });

      if (valid.length) {
        map.fitBounds(
          L.latLngBounds(valid.map(({ venue }) => [Number(venue.lat), Number(venue.long)])),
          { padding: [30, 30], maxZoom: 14 }
        );
      } else {
        map.setView([50.42, -3.57], 10);
      }
    };

    const calendarDateKey = date => {
      const y = date.getFullYear();
      const m = String(date.getMonth() + 1).padStart(2, "0");
      const d = String(date.getDate()).padStart(2, "0");
      return y + "-" + m + "-" + d;
    };

    const parseLocalDate = value => {
      if (!value) return null;
      const parts = String(value).split("-").map(Number);
      if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
      return new Date(parts[0], parts[1] - 1, parts[2]);
    };

    const addCalendarEvent = (events, date, activity, session) => {
      const key = calendarDateKey(date);
      if (!events[key]) events[key] = [];
      const venue = bhVenues(activity).find(v => String(v.id) === String(session.venue_id));
      events[key].push({
        activity,
        session,
        venue,
        time: session.start ? session.start.slice(0,5) : "",
        end: session.end ? session.end.slice(0,5) : ""
      });
    };

    const schoolHolidayCache = new Map();
    const activityIsTermTime = activity => bhSessions(activity).some(s => !!s.term_time);
    const countyForActivity = activity => activity.county || "";
    const getSchoolHolidayStatus = async county => {
      if (!county) return false;
      if (schoolHolidayCache.has(county)) return schoolHolidayCache.get(county);
      try {
        const response = await fetch("api/school-holidays.php?county="+encodeURIComponent(county), {cache:"no-store"});
        const payload = await response.json();
        const holiday = !!(response.ok && payload.ok && payload.is_school_holiday);
        schoolHolidayCache.set(county, holiday);
        return holiday;
      } catch (e) {
        // If the external holiday source is unavailable, do not hide activities.
        schoolHolidayCache.set(county, false);
        return false;
      }
    };
    const filterTermTimeForCalendar = async list => {
      const counties = [...new Set(list.filter(activityIsTermTime).map(countyForActivity).filter(Boolean))];
      const statuses = await Promise.all(counties.map(async county => [county, await getSchoolHolidayStatus(county)]));
      const holidayByCounty = new Map(statuses);
      return list.filter(activity => !(activityIsTermTime(activity) && holidayByCounty.get(countyForActivity(activity)) === true));
    };

    const buildCalendarEvents = (list, rangeStart, rangeEnd) => {
      const events = {};
      list.forEach(activity => {
        bhSessions(activity).forEach(session => {
          const day = Number(session.day_of_week);
          if (day < 1 || day > 7) return;
          const sessionStart = parseLocalDate(session.start_date);
          const sessionEnd = parseLocalDate(session.end_date);
          for (let date = new Date(rangeStart); date <= rangeEnd; date.setDate(date.getDate() + 1)) {
            const jsDay = date.getDay() === 0 ? 7 : date.getDay();
            if (jsDay !== day) continue;
            if (sessionStart && date < sessionStart) continue;
            if (sessionEnd && date > sessionEnd) continue;
            addCalendarEvent(events, new Date(date), activity, session);
          }
        });
      });
      return events;
    };

    const renderCalendar = list => {
      const calendar = $("calendarView");
      if (!calendar) return;

      // The calendar is intentionally opt-in: with 1,000+ listings we only
      // show sessions after the visitor has narrowed the directory results.
      const hasCalendarFilter = () => {
        const search = $("search").value.trim();
        const category = $("category").value;
        const region = $("area").value;
        const town = $("town").value;
        const minAge = Number($("ageMin").value);
        const maxAge = Number($("ageMax").value);
        const day = $("day").value;
        const maxPrice = $("maxPrice").value;
        const freeOnly = $("free").checked;
        return !!(search || category || region || town || day || maxPrice || freeOnly || minAge > 0 || maxAge < 9);
      };

      if (!hasCalendarFilter()) {
        calendar.innerHTML = `
          <div class="directory-calendar-empty-state">
            <div class="directory-calendar-empty-icon" aria-hidden="true">🔎</div>
            <h2>Find your perfect listings</h2>
            <p>Please use the filters above to narrow down activities by location, age, category, day or price. Your matching listings will then appear in the weekly calendar.</p>
          </div>
        `;
        return;
      }

      if (!list.length) {
        calendar.innerHTML = `
          <div class="directory-calendar-empty-state">
            <div class="directory-calendar-empty-icon" aria-hidden="true">📅</div>
            <h2>No matching listings</h2>
            <p>Try widening your filters to find activities for your family.</p>
          </div>
        `;
        return;
      }

      const baseDate = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
      const weekStart = new Date(baseDate);
      const dayOffset = (weekStart.getDay() + 6) % 7;
      weekStart.setDate(weekStart.getDate() - dayOffset);

      const days = Array.from({length:7}, (_, index) => {
        const date = new Date(weekStart);
        date.setDate(weekStart.getDate() + index);
        return date;
      });
      const events = buildCalendarEvents(list, days[0], days[6]);

      const weekLabel = days[0].toLocaleDateString("en-GB", {day:"numeric", month:"short"}) +
        " – " + days[6].toLocaleDateString("en-GB", {day:"numeric", month:"short", year:"numeric"});
      const todayKey = calendarDateKey(new Date());

      calendar.innerHTML = `
        <div class="directory-calendar-head">
          <div><strong>${escapeHtml(weekLabel)}</strong><span>${list.length} matching activit${list.length === 1 ? "y" : "ies"}</span></div>
          <div class="directory-calendar-actions">
            <button type="button" class="button button-soft" data-calendar-prev aria-label="Previous week">‹</button>
            <button type="button" class="button button-soft" data-calendar-today>Today</button>
            <button type="button" class="button button-soft" data-calendar-next aria-label="Next week">›</button>
          </div>
        </div>
        <div class="directory-calendar-grid directory-calendar-week-grid">
          ${days.map(date => `<div class="directory-calendar-weekday">${date.toLocaleDateString("en-GB",{weekday:"short"})}<span>${date.getDate()}</span></div>`).join("")}
          ${days.map(date => {
            const key = calendarDateKey(date);
            const dayEvents = events[key] || [];
            return `<div class="directory-calendar-day${key === todayKey ? " is-today" : ""}">
              <div class="directory-calendar-date">${date.toLocaleDateString("en-GB",{weekday:"long"})}</div>
              <div class="directory-calendar-events">
                ${dayEvents.length ? dayEvents.map(event => `
                  <a class="directory-calendar-event" href="${bhActivityUrl(event.activity)}">
                    <span>${escapeHtml(event.time || "")}</span>
                    <strong>${escapeHtml(event.activity.title)}</strong>
                    <small>${escapeHtml(event.venue?.town || event.venue?.name || "")}</small>
                  </a>`).join("") : '<span class="directory-calendar-empty">No activities</span>'}
              </div>
            </div>`;
          }).join("")}
        </div>
      `;

      calendar.querySelector("[data-calendar-prev]").onclick = () => {
        calendarMonth = new Date(weekStart);
        calendarMonth.setDate(calendarMonth.getDate() - 7);
        render();
      };
      calendar.querySelector("[data-calendar-next]").onclick = () => {
        calendarMonth = new Date(weekStart);
        calendarMonth.setDate(calendarMonth.getDate() + 7);
        render();
      };
      calendar.querySelector("[data-calendar-today]").onclick = () => {
        const now = new Date();
        calendarMonth = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        render();
      };
    };


    let calendarRenderToken = 0;
    const calendarLoading = () => {
      const calendar = $("calendarView");
      if (calendar) calendar.innerHTML = '<div class="admin-panel"><p>Checking school holiday dates…</p></div>';
    };

    const updateViewVisibility = () => {
      const results = $("results");
      const mapView = $("mapView");
      const calendarView = $("calendarView");

      // Only one primary directory view is ever visible at a time.
      if (results) {
        results.hidden = currentView === "map" || currentView === "calendar";
        results.setAttribute("aria-hidden", results.hidden ? "true" : "false");
      }
      if (mapView) {
        mapView.hidden = currentView !== "map";
        mapView.setAttribute("aria-hidden", mapView.hidden ? "true" : "false");
      }
      if (calendarView) {
        calendarView.hidden = currentView !== "calendar";
        calendarView.setAttribute("aria-hidden", calendarView.hidden ? "true" : "false");
      }
    };

    const render = () => {
      updateViewVisibility();
      const search = $("search").value.trim().toLowerCase();
      const category = $("category").value;
      const region = $("area").value;
      const town = $("town").value;
      const minAge = Number($("ageMin").value);
      const maxAge = Number($("ageMax").value);
      const day = $("day").value;
      const maxPrice = $("maxPrice").value;
      const freeOnly = $("free").checked;
      const sessionLength = $("sessionLength")?.value || "";
      const sen = $("sen")?.value || "";
      const termTime = $("termTime")?.value || "";
      const booking = $("booking")?.value || "";

      const list = activities.filter(activity => {
        const venues = bhVenues(activity);
        const sessions = bhSessions(activity);
        const text = [
          activity.title,
          activity.description,
          activity.category,
          activity.organiser_name,
          ...venues.flatMap(v => [v.name, v.town, v.region, v.address])
        ].filter(Boolean).join(" ").toLowerCase();

        const locationMatch = venues.some(v =>
          (!region || (v.region || activity.region) === region) &&
          (!town || (v.town || activity.town) === town)
        );

        const sessionMatch = sessions.some(session => !day || session.day === day);
        const price = Number(activity.price_value ?? sessions[0]?.price_value ?? 0);
        const priceMatch = !maxPrice || price <= Number(maxPrice);
        const freeMatch = !freeOnly || price === 0;
        const duration = sessions.map(x => Number(x.duration_minutes || 0)).filter(Boolean);
        const durationMatch = !sessionLength || (sessionLength === "181" ? duration.some(x => x >= 181) : duration.some(x => x > (Number(sessionLength)-60) && x <= Number(sessionLength)));
        const senValue = String(activity.sen_friendly ?? activity.sen_friendly_flag ?? "").toLowerCase();
        const senMatch = !sen || (sen === "yes" ? ["yes","1","true"].includes(senValue) : !["yes","1","true"].includes(senValue));
        const termMatch = !termTime || (termTime === "yes" ? sessions.some(x => !!x.term_time) : !sessions.some(x => !!x.term_time));
        const bookingMatch = !booking || (booking === "yes" ? !!activity.booking_url : !activity.booking_url);

        return (!search || text.includes(search)) &&
          (!category || (categoryMap[activity.category] || activity.category) === category) &&
          locationMatch &&
          ageMatches(activity, minAge, maxAge) &&
          sessionMatch &&
          priceMatch &&
          freeMatch &&
          durationMatch &&
          senMatch &&
          termMatch &&
          bookingMatch &&
          (!params.get("saved") || bhIsSaved(activity.id));
      });

      $("count").textContent =
        `${list.length} activit${list.length === 1 ? "y" : "ies"} found` +
        (params.get("saved") ? " · Saved" : "");

      $("results").className = `activity-grid directory-view-${currentView} directory-cards-${cardCount}`;
      $("results").innerHTML = list.map(activity => {
        const venues = bhVenues(activity);
        const sessions = bhSessions(activity);
        const firstSession = sessions[0];
        const price = activity.price || (firstSession?.price || "Price on request");

        return `<article class="activity-card">
          <div class="activity-image">${activity.image_url
            ? `<img src="${escapeHtml(activity.image_url)}" alt="${escapeHtml(activity.title)}" loading="lazy">`
            : "Activity image"}</div>
          <div class="activity-body">
            <div class="activity-meta">${escapeHtml(categoryMap[activity.category] || activity.category || "Family activity")}</div>
            <h3>${escapeHtml(activity.title)}</h3>
            <p>${escapeHtml(venues.length > 1
              ? venues.length + " venues"
              : (venues[0]?.town || activity.town || activity.region || ""))} ·
              ${escapeHtml(firstSession?.day || "Flexible")} ·
              ${escapeHtml(price)}</p>
            <div class="activity-footer">
              <a class="text-link" href="${bhActivityUrl(activity)}">View activity →</a>
              <button class="button button-soft bh-save" data-id="${escapeHtml(activity.id)}">
                ${bhIsSaved(activity.id) ? "♥ Saved" : "♡ Save"}
              </button>
            </div>
          </div>
        </article>`;
      }).join("") || '<div class="admin-panel"><h3>No activities found</h3><p>Try widening your age range or changing your filters.</p></div>';

      document.querySelectorAll(".bh-save").forEach(button => {
        button.onclick = () => {
          bhToggleSaved(button.dataset.id);
          render();
        };
      });

      if (currentView === "map") setTimeout(() => renderMap(list), 0);
      if (currentView === "calendar") {
        calendarRenderToken++;
        const token = calendarRenderToken;
        calendarLoading();
        filterTermTimeForCalendar(list).then(calendarList => {
          if (token !== calendarRenderToken || currentView !== "calendar") return;
          renderCalendar(calendarList);
        });
      }
    };

    const updateAge = source => {
      let min = Number($("ageMin").value);
      let max = Number($("ageMax").value);

      if (min > max) {
        if (source === "min") min = max;
        else max = min;
      }

      $("ageMin").value = min;
      $("ageMax").value = max;
      renderAgeTrack();
      render();
    };

    ["search", "category", "area", "town", "day", "maxPrice", "free", "sessionLength", "sen", "termTime", "booking"].forEach(id => {
      $(id).addEventListener("input", render);
      $(id).addEventListener("change", render);
    });

    $("ageMin").addEventListener("input", () => updateAge("min"));
    $("ageMax").addEventListener("input", () => updateAge("max"));

    $("clear").onclick = () => {
      ["search", "category", "area", "town", "day", "maxPrice", "sessionLength", "sen", "termTime", "booking"].forEach(id => { if ($(id)) $(id).value = ""; });
      $("ageMin").value = 0;
      $("ageMax").value = 9;
      $("free").checked = false;

      const clean = new URL(location.href);
      ["saved", "category", "region", "town", "age_min", "age_max", "day", "max_price"].forEach(key =>
        clean.searchParams.delete(key)
      );
      history.replaceState({}, "", clean);
      updateAge("min");
    };

    $("filterToggle").addEventListener("click", () => {
      const filters = $("directoryFilters");
      const open = filters.classList.toggle("is-open");
      $("filterToggle").setAttribute("aria-expanded", String(open));
    });

    $("cardCount").addEventListener("change", () => {
      cardCount = Number($("cardCount").value);
      if (![2,3,4,5,6].includes(cardCount)) cardCount = 4;
      localStorage.setItem("bh_directory_cards", String(cardCount));
      render();
    });

    document.querySelectorAll(".directory-view").forEach(button => {
      button.onclick = () => {
        currentView = button.dataset.view;
        localStorage.setItem("bh_directory_view", currentView);
        updateCardCountVisibility();
        document.querySelectorAll(".directory-view")
          .forEach(item => item.classList.toggle("active", item === button));
        updateViewVisibility();

        if (currentView === "map") {
          render();
          setTimeout(() => map && map.invalidateSize(), 50);
        } else {
          render();
        }
      };
    });

    document.querySelectorAll(".directory-view").forEach(button => button.classList.toggle("active", button.dataset.view === currentView));
    updateViewVisibility();
    renderAgeTrack();
    render();
  } catch (error) {
    const results = $("results");
    if (results) {
      results.innerHTML =
        '<div class="admin-panel"><h3>Activities unavailable</h3><p>' +
        bhEscape(error.message || "Unable to load activities.") +
        "</p></div>";
    }
  }
});