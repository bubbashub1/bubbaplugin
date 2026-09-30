document.addEventListener("DOMContentLoaded", async () => {
  const mapEl = document.getElementById("mapView");
  const status = document.getElementById("mapStatus");
  if (!mapEl || !window.L) {
    if (status) status.textContent = "The map could not be loaded.";
    return;
  }
  const escapeHtml = value => bhEscape(value);
  try {
    const activities = await bhActivities();
    const map = L.map(mapEl).setView([50.42, -3.57], 10);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "© OpenStreetMap contributors"
    }).addTo(map);

    const icon = L.divIcon({
      className: "custom-sleek-dark-marker",
      html: '<div class="bubba-dark-pin"><div class="dark-core"></div></div>',
      iconSize: [36,36],
      iconAnchor: [18,36],
      popupAnchor: [0,-36]
    });

    const valid = activities.flatMap(activity =>
      bhVenues(activity).map(venue => ({activity, venue}))
    ).filter(({venue}) =>
      Number.isFinite(Number(venue.lat)) && Number.isFinite(Number(venue.long))
    );

    valid.forEach(({activity, venue}) => {
      const sessions = bhSessions(activity);
      const session = sessions.find(s => s.venue_id == null || String(s.venue_id) === String(venue.id)) || sessions[0] || {};
      const age = Array.isArray(activity.age_range) ? activity.age_range.join(" · ") : (activity.age_range || "All ages");
      const price = activity.price || session.price || "Price on request";
      const time = [session.day, session.start && session.start.slice(0,5)].filter(Boolean).join(" · ");
      const image = activity.image_url
        ? '<img class="bh-map-popup-image" src="' + escapeHtml(activity.image_url) + '" alt="' + escapeHtml(activity.title) + '">'
        : '<img class="bh-map-popup-image" src="images/logos/gemini_generated_image_1dzezm1dzezm1dze-20260929-213630-1f8496.jpeg" alt="" aria-hidden="true">';
      const popup =
        '<article class="bh-map-popup-card">' + image +
        '<div class="bh-map-popup-body">' +
        '<span class="bh-map-popup-category">' + escapeHtml(activity.category || "Family activity") + '</span>' +
        '<h3>' + escapeHtml(activity.title) + '</h3>' +
        '<p class="bh-map-popup-location">📍 ' + escapeHtml(venue.name || venue.town || venue.address || "") + '</p>' +
        '<div class="bh-map-popup-meta"><span>👶 ' + escapeHtml(age) + '</span><span>💷 ' + escapeHtml(price) + '</span>' +
        (time ? '<span>🕒 ' + escapeHtml(time) + '</span>' : '') + '</div>' +
        '<a class="button button-primary bh-map-popup-link" href="' + bhActivityUrl(activity) + '">View activity →</a>' +
        '</div></article>';
      L.marker([Number(venue.lat), Number(venue.long)], {icon}).addTo(map).bindPopup(popup,{maxWidth:330,minWidth:250,className:"bh-map-popup"});
    });

    if (valid.length) {
      map.fitBounds(L.latLngBounds(valid.map(({venue}) => [Number(venue.lat),Number(venue.long)])), {padding:[30,30],maxZoom:14});
      if (status) status.textContent = valid.length === 1 ? "1 mapped activity" : valid.length + " mapped activities";
    } else {
      if (status) status.textContent = "No activities with map locations are available yet.";
    }
    setTimeout(() => map.invalidateSize(), 100);
  } catch (error) {
    if (status) status.textContent = error.message || "Activities could not be loaded.";
  }
});