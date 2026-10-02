/* Shared Bubba Hub Leaflet map engine. Filtering and page-specific UI stay in page scripts. */
window.bhMapEngine = (() => {
  const markerIcon = () => L.divIcon({
    className: "custom-sleek-dark-marker",
    html: '<div class="bubba-dark-pin"><div class="dark-core"></div></div>',
    iconSize: [36,36], iconAnchor: [18,36], popupAnchor: [0,-36]
  });
  const init = (elementId, center=[50.42,-3.57], zoom=10) => {
    if (!window.L) return null;
    const el=document.getElementById(elementId);
    if (!el) return null;
    const map=L.map(el).setView(center,zoom);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"© OpenStreetMap contributors"}).addTo(map);
    return map;
  };
  const clear = markers => (markers||[]).forEach(marker=>marker.remove());
  const render = (map, list, popupHtml, markers=[]) => {
    clear(markers); markers=[];
    if (!map) return markers;
    const rows=list.flatMap(activity=>bhVenues(activity).map(venue=>({activity,venue})))
      .filter(({venue})=>Number.isFinite(Number(venue.lat))&&Number.isFinite(Number(venue.long)));
    const icon=markerIcon();
    rows.forEach(({activity,venue})=>{
      const marker=L.marker([Number(venue.lat),Number(venue.long)],{icon})
        .addTo(map).bindPopup(popupHtml(activity,venue),{maxWidth:340,minWidth:260,className:"bh-map-popup"});
      marker._bhActivityId=String(activity.id); marker._bhVenueId=String(venue.id||""); markers.push(marker);
    });
    if(rows.length) map.fitBounds(L.latLngBounds(rows.map(({venue})=>[Number(venue.lat),Number(venue.long)])),{padding:[30,30],maxZoom:14});
    else map.setView([50.42,-3.57],10);
    return markers;
  };
  return {init,clear,render};
})();