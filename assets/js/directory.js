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

    let map = null;
    let markers = [];
    let currentView = localStorage.getItem("bh_directory_view") || "list";
    let calendarMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let cardCount = Number(localStorage.getItem("bh_directory_cards") || 4);
    if (![2,3,4,5,6].includes(cardCount)) cardCount = 4;
    $("cardCount").value = String(cardCount);

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

      valid.forEach(({ activity, venue }) => {
        const marker = L.marker([Number(venue.lat), Number(venue.long)])
          .addTo(map)
          .bindPopup(
            `<strong>${escapeHtml(activity.title)}</strong><br>` +
            `${escapeHtml(venue.name || venue.address || venue.town || "")}<br>` +
            `<a href="${bhActivityUrl(activity)}">View activity</a>`
          );
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

    const buildCalendarEvents = list => {
      const events = {};
      const year = calendarMonth.getFullYear();
      const month = calendarMonth.getMonth();
      const monthStart = new Date(year, month, 1);
      const monthEnd = new Date(year, month + 1, 0);

      list.forEach(activity => {
        bhSessions(activity).forEach(session => {
          const day = Number(session.day_of_week);
          if (day < 1 || day > 7) return;

          const sessionStart = parseLocalDate(session.start_date);
          const sessionEnd = parseLocalDate(session.end_date);

          for (let date = new Date(monthStart); date <= monthEnd; date.setDate(date.getDate() + 1)) {
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

      const events = buildCalendarEvents(list);
      const year = calendarMonth.getFullYear();
      const month = calendarMonth.getMonth();
      const monthStart = new Date(year, month, 1);
      const monthEnd = new Date(year, month + 1, 0);
      const firstDay = monthStart.getDay() === 0 ? 6 : monthStart.getDay() - 1;
      const daysInMonth = monthEnd.getDate();
      const previousMonthDays = new Date(year, month, 0).getDate();
      const cells = [];

      for (let i = firstDay - 1; i >= 0; i--) {
        cells.push({ date: new Date(year, month - 1, previousMonthDays - i), muted: true });
      }
      for (let day = 1; day <= daysInMonth; day++) {
        cells.push({ date: new Date(year, month, day), muted: false });
      }
      while (cells.length % 7 !== 0) {
        cells.push({ date: new Date(year, month, cells.length - firstDay - daysInMonth + 1), muted: true });
      }

      const monthLabel = monthStart.toLocaleDateString("en-GB", { month: "long", year: "numeric" });
      const todayKey = calendarDateKey(new Date());

      calendar.innerHTML = `
        <div class="directory-calendar-head">
          <div><strong>${escapeHtml(monthLabel)}</strong><span>${list.length} matching activit${list.length === 1 ? "y" : "ies"}</span></div>
          <div class="directory-calendar-actions">
            <button type="button" class="button button-soft" data-calendar-prev aria-label="Previous month">‹</button>
            <button type="button" class="button button-soft" data-calendar-today>Today</button>
            <button type="button" class="button button-soft" data-calendar-next aria-label="Next month">›</button>
          </div>
        </div>
        <div class="directory-calendar-grid">
          ${["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"].map(day => `<div class="directory-calendar-weekday">${day}</div>`).join("")}
          ${cells.map(cell => {
            const key = calendarDateKey(cell.date);
            const dayEvents = events[key] || [];
            return `<div class="directory-calendar-day${cell.muted ? " is-muted" : ""}${key === todayKey ? " is-today" : ""}">
              <div class="directory-calendar-date">${cell.date.getDate()}</div>
              <div class="directory-calendar-events">
                ${dayEvents.slice(0, 4).map(event => `
                  <a class="directory-calendar-event" href="${bhActivityUrl(event.activity)}">
                    <span>${escapeHtml(event.time || "")}</span>
                    <strong>${escapeHtml(event.activity.title)}</strong>
                    <small>${escapeHtml(event.venue?.town || event.venue?.name || "")}</small>
                  </a>`).join("")}
                ${dayEvents.length > 4 ? `<span class="directory-calendar-more">+${dayEvents.length - 4} more</span>` : ""}
              </div>
            </div>`;
          }).join("")}
        </div>
      `;

      calendar.querySelector("[data-calendar-prev]").onclick = () => { calendarMonth = new Date(year, month - 1, 1); render(); };
      calendar.querySelector("[data-calendar-next]").onclick = () => { calendarMonth = new Date(year, month + 1, 1); render(); };
      calendar.querySelector("[data-calendar-today]").onclick = () => {
        const now = new Date();
        calendarMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        render();
      };
    };

    const render = () => {
      const search = $("search").value.trim().toLowerCase();
      const category = $("category").value;
      const region = $("area").value;
      const town = $("town").value;
      const minAge = Number($("ageMin").value);
      const maxAge = Number($("ageMax").value);
      const day = $("day").value;
      const maxPrice = $("maxPrice").value;
      const freeOnly = $("free").checked;

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

        return (!search || text.includes(search)) &&
          (!category || (categoryMap[activity.category] || activity.category) === category) &&
          locationMatch &&
          ageMatches(activity, minAge, maxAge) &&
          sessionMatch &&
          priceMatch &&
          freeMatch &&
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
      if (currentView === "calendar") renderCalendar(list);
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

    ["search", "category", "area", "town", "day", "maxPrice", "free"].forEach(id => {
      $(id).addEventListener("input", render);
      $(id).addEventListener("change", render);
    });

    $("ageMin").addEventListener("input", () => updateAge("min"));
    $("ageMax").addEventListener("input", () => updateAge("max"));

    $("clear").onclick = () => {
      ["search", "category", "area", "town", "day", "maxPrice"].forEach(id => $(id).value = "");
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
        document.querySelectorAll(".directory-view")
          .forEach(item => item.classList.toggle("active", item === button));
        $("results").hidden = currentView === "map" || currentView === "calendar";
        $("mapView").hidden = currentView !== "map";
        $("calendarView").hidden = currentView !== "calendar";

        if (currentView === "map") {
          render();
          setTimeout(() => map && map.invalidateSize(), 50);
        } else {
          render();
        }
      };
    });

    document.querySelectorAll(".directory-view").forEach(button => button.classList.toggle("active", button.dataset.view === currentView));
    $("results").hidden = currentView === "map" || currentView === "calendar";
    $("mapView").hidden = currentView !== "map";
    $("calendarView").hidden = currentView !== "calendar";
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