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
    let currentView = "list";

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

    document.querySelectorAll(".directory-view").forEach(button => {
      button.onclick = () => {
        currentView = button.dataset.view;
        document.querySelectorAll(".directory-view")
          .forEach(item => item.classList.toggle("active", item === button));
        $("results").hidden = currentView === "map";
        $("mapView").hidden = currentView !== "map";

        if (currentView === "map") {
          render();
          setTimeout(() => map && map.invalidateSize(), 50);
        } else {
          render();
        }
      };
    });

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