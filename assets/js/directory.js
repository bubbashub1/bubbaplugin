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

    const categoryValues = [...new Set(activities.map(x => categoryMap[x.category] || x.category).filter(Boolean))].sort();
    const regionValues = [...new Set(activities.flatMap(x => bhVenues(x).map(v => v.region || x.region)).filter(Boolean))].sort();

    fillSelect("category", categoryValues, params.get("category") || "", "All categories");
    fillSelect("heroCategory", categoryValues, params.get("category") || "", "Category");
    fillSelect("area", regionValues, params.get("region") || "", "All regions");
    fillSelect("heroRegion", regionValues, params.get("region") || "", "Region");
    fillSelect(
      "town",
      [...new Set(activities.flatMap(x => bhVenues(x).map(v => v.town || x.town)).filter(Boolean))].sort(),
      params.get("town") || "",
      "All towns"
    );

    if ($("search") && params.get("search")) $("search").value = params.get("search");
    if ($("search") && params.get("q")) $("search").value = params.get("q");
    if ($("search") && params.get("keyword")) $("search").value = params.get("keyword");

    const agePresets = {
      baby: [0, 1],
      toddler: [1, 3],
      preschool: [3, 5],
      school: [5, 9]
    };
    const agePresetKey = params.get("age_preset") || "";
    const agePreset = agePresets[agePresetKey];
    if (agePreset) {
      $("ageMin").value = String(agePreset[0]);
      $("ageMax").value = String(agePreset[1]);
      if ($("ageRange")) $("ageRange").value = agePresetKey;
      if ($("heroAge")) $("heroAge").value = agePresetKey;
    }
    if (params.get("age_min") !== null) $("ageMin").value = params.get("age_min");
    if (params.get("age_max") !== null) $("ageMax").value = params.get("age_max");
    if (params.get("day")) $("day").value = params.get("day");
    if (params.get("free")) $("free").checked = true;
    if (params.get("max_price")) $("maxPrice").value = params.get("max_price");
    if (params.get("sessionLength")) $("sessionLength").value = params.get("sessionLength");
    if (params.get("sen")) $("sen").value = params.get("sen");
    if (params.get("termTime")) $("termTime").value = params.get("termTime");
    if (params.get("bookingRequired")) $("bookingRequired").checked = params.get("bookingRequired") === "1";
    if (params.get("accessibility") && $("accessibility")) $("accessibility").checked = params.get("accessibility") === "1";

    // Main filters stay simple; all other filters live inside Advanced search.
    const setupAdvancedSearch = () => {
      const advancedToggle = $("advancedToggle");
      const advancedFields = $("advancedFields");
      const mobileToggle = $("filterToggle");
      const panel = $("directoryFilters");
      const openSearch = $("openSearchFilters");

      if (advancedToggle && advancedFields) {
        advancedToggle.onclick = () => {
          const open = advancedFields.hidden;
          advancedFields.hidden = !open;
          advancedToggle.setAttribute("aria-expanded", String(open));
          advancedToggle.innerHTML = open
            ? "More filters <span aria-hidden=\"true\">−</span>"
            : "More filters <span aria-hidden=\"true\">＋</span>";
        };
      }

      const setMobileOpen = open => {
        if (!panel || !mobileToggle) return;
        panel.classList.toggle("is-open", open);
        mobileToggle?.setAttribute("aria-expanded", String(open));
        if (mobileToggle) mobileToggle.innerHTML = open
          ? "Close filters <span aria-hidden=\"true\">×</span>"
          : "Filters <span aria-hidden=\"true\">＋</span>";
        desktopToggle?.setAttribute("aria-expanded", String(open));
        if (desktopToggle) desktopToggle.innerHTML = open
          ? "Close filters <span aria-hidden=\"true\">×</span>"
          : "Filters <span aria-hidden=\"true\">☰</span>";
        document.body.classList.toggle("directory-filter-open", open);
      };

      const desktopToggle = $("desktopFilterButton");
      if (mobileToggle) mobileToggle.onclick = () => setMobileOpen(!panel.classList.contains("is-open"));
      if (desktopToggle) desktopToggle.onclick = () => setMobileOpen(!panel.classList.contains("is-open"));
      if (openSearch) openSearch.onclick = () => {
        if (advancedFields) {
          advancedFields.hidden = false;
          if (advancedToggle) {
            advancedToggle.setAttribute("aria-expanded", "true");
            advancedToggle.innerHTML = 'More filters <span aria-hidden="true">−</span>';
          }
        }
        setMobileOpen(true);
        requestAnimationFrame(() => {
          const target = advancedFields || panel;
          target?.scrollIntoView({behavior:"smooth", block:"nearest"});
        });
      };

      window.addEventListener("resize", () => {
        if (window.innerWidth > 900 && panel && panel.classList.contains("is-open")) {
          panel.classList.remove("is-open");
          document.body.classList.remove("directory-filter-open");
        }
      });

      if (window.innerWidth > 900 && panel) setMobileOpen(false);
      else setMobileOpen(false);
    };

    const advancedFilterDefaults = {category:true,region:true,town:true,nearby:true,age:true,day:true,price:true,session_length:true,sen:true,term_time:true,booking:true,accessibility:true,free:true};
    const applyAdvancedFilterSettings = async () => {
      try {
        const response = await fetch("api/search-settings.php",{cache:"no-store",headers:{Accept:"application/json"}});
        const payload = await response.json();
        const settings = response.ok && payload.ok ? {...advancedFilterDefaults,...(payload.data||{})} : advancedFilterDefaults;
        document.querySelectorAll(".directory-advanced-filter-field[data-filter-key]").forEach(el => {
          el.hidden = settings[el.dataset.filterKey] === false;
        });
      } catch (_) {}
    };
    setupAdvancedSearch();
    void applyAdvancedFilterSettings();

    let map = null;
    let markers = [];
    const storedView = localStorage.getItem("bh_directory_view");
    let currentView = storedView === "list" ? "grid" : (storedView || "grid");
    let calendarMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let cardCount = Number(localStorage.getItem("bh_directory_cards") || 3);
    let homeLocation = null;
    let homeLocationLoading = false;

    const distanceMiles = (lat1, lon1, lat2, lon2) => {
      const toRad = value => value * Math.PI / 180;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
      return 3958.7613 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const nearestVenue = activity => {
      if (!homeLocation) return null;
      const venues = bhVenues(activity).filter(v =>
        Number.isFinite(Number(v.lat)) && Number.isFinite(Number(v.long))
      );
      if (!venues.length) return null;
      return venues.reduce((best, venue) => {
        const miles = distanceMiles(homeLocation.lat, homeLocation.long, Number(venue.lat), Number(venue.long));
        return !best || miles < best.miles ? { venue, miles } : best;
      }, null);
    };

    const setNearbyStatus = message => {
      const el = $("nearbyStatus");
      if (el) el.textContent = message || "";
    };

    const loadHomeLocation = async () => {
      if (homeLocationLoading) return;
      homeLocationLoading = true;
      setNearbyStatus("Loading your saved home area…");
      try {
        const response = await fetch("api/profile.php", { credentials: "same-origin", cache: "no-store" });
        const payload = await response.json();
        if (!response.ok || !payload.ok || !payload.user) {
          throw new Error("Please sign in and add your home town or postcode in My account.");
        }
        const address = payload.user.address || {};
        const town = String(address.city || "").trim();
        const postcode = String(address.postcode || "").trim();

        // Prefer coordinates already saved on the private profile. If they are
        // missing, geocode only the town (not the full home address) so the
        // private street address is never sent to the public map service.
        let lat = Number(address.latitude);
        let long = Number(address.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(long)) {
          if (!town) throw new Error("Add your home town in My account first.");
          const geo = await fetch("api/geocode.php?q=" + encodeURIComponent(town + ", UK"), {cache:"no-store"});
          const geoPayload = await geo.json();
          const result = geoPayload?.results?.[0];
          lat = Number(result?.lat);
          long = Number(result?.lon);
          if (!Number.isFinite(lat) || !Number.isFinite(long)) throw new Error("We could not locate your home town.");
          try {
            await fetch("api/profile.php", {
              method: "POST",
              credentials: "same-origin",
              headers: {"Content-Type":"application/json","Accept":"application/json"},
              body: JSON.stringify({
                csrf: payload.csrf,
                first_name: payload.user.first_name || "",
                last_name: payload.user.last_name || "",
                phone: payload.user.phone || "",
                date_of_birth: payload.user.date_of_birth || "",
                address: {...address, latitude: lat, longitude: long}
              })
            });
          } catch (_) {}
        }
        homeLocation = {lat, long, town, postcode};
        setNearbyStatus(town ? "Near " + town : "Nearby results");
        render();
      } catch (error) {
        homeLocation = null;
        setNearbyStatus(error.message || "Nearby search unavailable.");
      } finally {
        homeLocationLoading = false;
      }
    };


    if (![2,3,4,5,6].includes(cardCount)) cardCount = 3;
    $("cardCount").value = String(cardCount);
    const updateCardCountVisibility = () => {
      const control = document.querySelector(".directory-count-control");
      if (control) control.hidden = currentView !== "grid";
    };
    updateCardCountVisibility();

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

    // A plain directory URL must always open unfiltered. This prevents browser
    // autofill/restored form state from silently reducing the initial results.
    const directoryFilterKeys = ["search","q","keyword","category","region","town","age_min","age_max","age_preset","day","max_price","free","sessionLength","sen","termTime","bookingRequired","accessibility","saved"];
    const hasUrlFilters = directoryFilterKeys.some(key => params.has(key) && params.get(key) !== "");
    if (!hasUrlFilters) {
      if ($("search")) $("search").value = "";
      if ($("category")) $("category").value = "";
      if ($("area")) $("area").value = "";
      if ($("town")) $("town").value = "";
      if ($("day")) $("day").value = "";
      if ($("maxPrice")) $("maxPrice").value = "";
      if ($("sessionLength")) $("sessionLength").value = "";
      if ($("sen")) $("sen").value = "";
      if ($("termTime")) $("termTime").value = "";
      if ($("accessibility")) $("accessibility").checked = false;
      if ($("bookingRequired")) $("bookingRequired").checked = false;
      if ($("free")) $("free").checked = false;
      if ($("ageMin")) $("ageMin").value = "0";
      if ($("ageMax")) $("ageMax").value = "9";
      if ($("ageRange")) $("ageRange").value = "";
    }

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
      const bookingRequired = $("bookingRequired")?.checked || false;
      const accessibility = $("accessibility")?.checked ? "1" : "";

      const hasActiveDirectoryFilters = !!(
        search || category || region || town || day || maxPrice || freeOnly ||
        sessionLength || sen || termTime || bookingRequired || accessibility ||
        minAge > 0 || maxAge < 9 || params.get("saved")
      );

      // A completely fresh directory must show every published activity returned
      // by the API. Only run the detailed matcher when the visitor has actually
      // selected a filter. This prevents default form values from hiding listings.
      let list = hasActiveDirectoryFilters ? activities.filter(activity => {
        const venues = bhVenues(activity);
        const sessions = bhSessions(activity);
        const text = [
          activity.title,
          activity.description,
          activity.category,
          activity.organiser_name,
          activity.county,
          activity.age_range,
          ...venues.flatMap(v => [v.name, v.town, v.region, v.address, v.postcode])
        ].filter(Boolean).join(" ").toLowerCase();

        const locationMatch = (!region && !town) || venues.some(v =>
          (!region || String(v.region || activity.region || "").toLowerCase() === String(region).toLowerCase()) &&
          (!town || String(v.town || activity.town || "").toLowerCase() === String(town).toLowerCase())
        );

        const sessionMatch = sessions.some(session => !day || session.day === day);
        const price = Number(activity.price_value ?? sessions[0]?.price_value ?? 0);
        const priceMatch = !maxPrice || price <= Number(maxPrice);
        const freeMatch = !freeOnly || price === 0 || String(activity.price || "").toLowerCase().includes("free");
        const duration = sessions.map(x => Number(x.duration_minutes || 0)).filter(Boolean);
        const durationMatch = !sessionLength || (sessionLength === "181" ? duration.some(x => x >= 181) : sessionLength === "60" ? duration.some(x => x <= 60) : sessionLength === "120" ? duration.some(x => x > 60 && x <= 120) : duration.some(x => x > 120 && x <= 180));
        const senValue = String(activity.sen_friendly ?? activity.sen_friendly_flag ?? "").toLowerCase();
        const senMatch = !sen || (sen === "yes" ? ["yes","1","true"].includes(senValue) : !["yes","1","true"].includes(senValue));
        const termMatch = !termTime || (termTime === "yes" ? sessions.some(x => !!x.term_time) : !sessions.some(x => !!x.term_time));
        const bookingMatch = !bookingRequired || !!activity.booking_url;
        const accessibilityMatch = !accessibility || (Array.isArray(activity.accessibility) && activity.accessibility.length > 0);

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
          accessibilityMatch &&
          (!params.get("saved") || bhIsSaved(activity.id));
      }) : [...activities];

      if (homeLocation) {
        list = list
          .map(activity => ({ activity, nearby: nearestVenue(activity) }))
          .sort((a, b) => {
            if (a.nearby && b.nearby) return a.nearby.miles - b.nearby.miles;
            if (a.nearby) return -1;
            if (b.nearby) return 1;
            return String(a.activity.title || "").localeCompare(String(b.activity.title || ""));
          })
          .map(item => item.activity);
      }

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
            ${homeLocation && nearestVenue(activity) ? '<div class="directory-distance">📍 ' + escapeHtml(nearestVenue(activity).miles.toFixed(1)) + ' miles away</div>' : ""}
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
        render();
    };

    if ($("mainSearchButton")) $("mainSearchButton").addEventListener("click", render);
    if ($("search")) $("search").addEventListener("keydown", event => { if (event.key === "Enter") { event.preventDefault(); render(); } });

    ["search", "category", "area", "town", "day", "maxPrice", "free", "sessionLength", "sen", "termTime", "bookingRequired", "accessibility"].forEach(id => {
      if (!$(id)) return;
      $(id).addEventListener("input", render);
      $(id).addEventListener("change", render);
    });

    if ($("ageRange")) {
      $("ageRange").addEventListener("change", () => {
        const preset = agePresets[$("ageRange").value];
        if (preset) {
          $("ageMin").value = String(preset[0]);
          $("ageMax").value = String(preset[1]);
        } else {
          $("ageMin").value = "0";
          $("ageMax").value = "9";
        }
        render();
      });
    }

    document.querySelectorAll(".directory-popular-chip").forEach(button => {
      button.addEventListener("click", () => {
        const category = button.dataset.category || "";
        const select = $("category");
        const heroSelect = $("heroCategory");
        const words = category.toLowerCase().split(/\s+/).filter(Boolean);
        const option = select && [...select.options].find(o =>
          o.value === category ||
          o.textContent.trim().toLowerCase() === category.toLowerCase() ||
          words.some(word => word.length > 3 && o.textContent.toLowerCase().includes(word))
        );
        const heroOption = heroSelect && [...heroSelect.options].find(o =>
          o.value === category ||
          o.textContent.trim().toLowerCase() === category.toLowerCase() ||
          words.some(word => word.length > 3 && o.textContent.toLowerCase().includes(word))
        );
        if (select && option) select.value = option.value;
        if (heroSelect && heroOption) heroSelect.value = heroOption.value;
        if ($("search")) $("search").value = "";
        render();
        document.querySelectorAll(".directory-popular-chip").forEach(x => x.classList.remove("active"));
        button.classList.add("active");
      });
    });

    // Age is intentionally a simple dropdown rather than a slider.

    $("useHomeLocation").onclick = loadHomeLocation;
    if (params.get("nearby") === "1") void loadHomeLocation();

    $("clear").onclick = () => {
      ["search", "category", "area", "town", "day", "maxPrice", "sessionLength", "sen", "termTime", "accessibility"].forEach(id => { if ($(id)) $(id).value = ""; });
      $("ageMin").value = 0;
      $("ageMax").value = 9;
      if ($("ageRange")) $("ageRange").value = "";
      $("free").checked = false;
      if ($("bookingRequired")) $("bookingRequired").checked = false;
      homeLocation = null;
      setNearbyStatus("");

      const clean = new URL(location.href);
      ["saved", "category", "region", "town", "age_min", "age_max", "day", "max_price"].forEach(key =>
        clean.searchParams.delete(key)
      );
      history.replaceState({}, "", clean);
      render();
    };

    $("filterToggle").addEventListener("click", () => {
      const filters = $("directoryFilters");
      const open = filters.classList.toggle("is-open");
      $("filterToggle").setAttribute("aria-expanded", String(open));
    });

    $("cardCount").addEventListener("change", () => {
      cardCount = Number($("cardCount").value);
      if (![2,3,4,5,6].includes(cardCount)) cardCount = 3;
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