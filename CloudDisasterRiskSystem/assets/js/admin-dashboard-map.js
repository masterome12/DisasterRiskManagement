// Map Dashboard — live incident map, a real geospatial-layers toggle panel,
// a pins/heatmap view switch, and a lightweight ranked-hotspots summary.
// Mirrors the uploaded mockup's 3-column map-dashboard layout (layers |
// map | ranked list) with a floating toolbar + legend over the map, using
// only data this app already has (no invented metrics).
(function () {
  const map = L.map('dashMap').setView(window.APP.center, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
  }).addTo(map);

  const clusterGroup = L.markerClusterGroup();
  let heatLayer = null;
  let incidents = [];
  map.addLayer(clusterGroup);

  // Real filters: a minimum-severity threshold (mirrors the mockup's risk
  // slider, using our actual severity scale) and a hazard-type filter
  // (mirrors its "cause filter") — both applied client-side to the same
  // fetched incident list, same pattern used on Hotspot Analysis's hazard
  // filter (fetch once, re-render on filter change).
  const SEVERITY_LEVELS = ['low', 'moderate', 'high', 'critical'];
  let rangeDays = 30;
  function filteredIncidents() {
    const minIdx = +document.getElementById('dashSeverityRange').value;
    const hazard = document.getElementById('dashHazardFilter').value;
    const cutoff = rangeDays ? Date.now() - rangeDays * 86400000 : 0;
    return incidents.filter(i => {
      if (SEVERITY_LEVELS.indexOf(i.severity) < minIdx) return false;
      if (hazard && i.hazard_type !== hazard) return false;
      if (cutoff && new Date(i.created_at).getTime() < cutoff) return false;
      return true;
    });
  }

  function renderPins() {
    clusterGroup.clearLayers();
    filteredIncidents().forEach(inc => {
      const marker = L.marker([inc.latitude, inc.longitude], { icon: hazardDivIcon(inc.hazard_type, inc.severity) });
      marker.bindPopup(`<b>${inc.hazard_type}</b><br>${inc.barangay ?? ''}<br>${inc.severity}`);
      clusterGroup.addLayer(marker);
    });
  }

  let currentViz = 'pins';
  function setViz(mode) {
    currentViz = mode;
    document.getElementById('dashVizPins').classList.toggle('active', mode === 'pins');
    document.getElementById('dashVizHeat').classList.toggle('active', mode === 'heat');
    if (mode === 'pins') {
      if (heatLayer) map.removeLayer(heatLayer);
      renderPins();
      map.addLayer(clusterGroup);
    } else {
      map.removeLayer(clusterGroup);
      if (heatLayer) map.removeLayer(heatLayer);
      heatLayer = L.heatLayer(filteredIncidents().map(i => [i.latitude, i.longitude, 0.6]), { radius: 25, blur: 20 }).addTo(map);
    }
  }
  document.getElementById('dashVizPins').addEventListener('click', () => setViz('pins'));
  document.getElementById('dashVizHeat').addEventListener('click', () => setViz('heat'));
  document.getElementById('dashFitCity').addEventListener('click', () => map.setView(window.APP.center, 12));

  const sevRange = document.getElementById('dashSeverityRange');
  const sevLabel = document.getElementById('dashSeverityLabel');
  const hazFilter = document.getElementById('dashHazardFilter');
  function refreshFiltered() {
    sevLabel.textContent = +sevRange.value === 0 ? 'Any' : SEVERITY_LEVELS[+sevRange.value];
    setViz(currentViz);
    document.getElementById('dashMapBadge').textContent = `${filteredIncidents().length} incident(s)`;
  }
  sevRange.addEventListener('input', refreshFiltered);
  hazFilter.addEventListener('change', refreshFiltered);

  // Real time-range filter (7d/30d/90d/All time) — filters the same fetched
  // incident list by created_at, same client-side pattern as the other two.
  document.querySelectorAll('#dashRangeSeg button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#dashRangeSeg button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      rangeDays = +btn.dataset.days;
      refreshFiltered();
    });
  });

  async function loadDashMap() {
    const res = await fetch(window.BASE_URL + '/api/incidents.php?action=list');
    const data = await res.json();
    const badge = document.getElementById('dashMapBadge');
    if (!data.incidents || !data.incidents.length) {
      badge.textContent = data.message || 'No data available';
      incidents = [];
      renderPins();
      return;
    }
    incidents = data.incidents;
    renderPins();
    badge.textContent = `${filteredIncidents().length} incident(s)`;

    // Legend + hazard filter list only from hazards actually present.
    const present = [...new Set(incidents.map(i => i.hazard_type))];
    document.getElementById('dashMapLegend').innerHTML = present.map(hz => {
      const m = hazardMeta(hz);
      return `<div class="row-item"><span class="legend-dot" style="background:${m.color}"></span>${hz}</div>`;
    }).join('');
    present.forEach(hz => {
      const o = document.createElement('option');
      o.value = hz; o.textContent = hz;
      hazFilter.appendChild(o);
    });
  }
  loadDashMap();

  // ---- Geospatial layers toggle panel (real data, api/layers.php) --------
  // Purely a local view toggle on this map — doesn't touch each layer's
  // stored visibility (that's Data & Layers' job); mirrors the mockup's
  // "Layers" panel, which is also just a per-view show/hide control.
  const overlayLayers = {};
  async function loadDashLayers() {
    const list = document.getElementById('dashLayerList');
    let rows = `<div class="lyr on" id="lyrIncidents"><i class="sw"></i><span>Incident markers</span></div>`;

    try {
      const res = await fetch(window.BASE_URL + '/api/layers.php?action=list');
      const data = await res.json();
      if (data.layers && data.layers.length) {
        data.layers.forEach(l => {
          const pointCount = (l.geojson.features || []).filter(f => f.geometry && f.geometry.type === 'Point').length;
          if (pointCount > 300) return; // same "too many to map" cap as the public map
          const gj = L.geoJSON(l.geojson, {
            style: { color: '#475569', weight: 2, fillOpacity: 0.1 },
            pointToLayer: (f, latlng) => L.circleMarker(latlng, { radius: 4, color: '#475569', weight: 1, fillOpacity: 0.5 }),
          });
          overlayLayers[l.id] = gj;
          rows += `<div class="lyr" id="lyr-${l.id}"><i class="sw"></i><span>${l.name}</span></div>`;
        });
      }
    } catch (e) { /* geospatial layers are optional here */ }

    list.innerHTML = rows;

    document.getElementById('lyrIncidents').addEventListener('click', function () {
      const on = this.classList.toggle('on');
      if (on) { setViz(document.getElementById('dashVizHeat').classList.contains('active') ? 'heat' : 'pins'); }
      else { map.removeLayer(clusterGroup); if (heatLayer) map.removeLayer(heatLayer); }
    });
    Object.keys(overlayLayers).forEach(id => {
      const row = document.getElementById(`lyr-${id}`);
      if (row) row.addEventListener('click', function () {
        const on = this.classList.toggle('on');
        if (on) map.addLayer(overlayLayers[id]); else map.removeLayer(overlayLayers[id]);
      });
    });
  }
  loadDashLayers();

  // ---- Ranked hotspots (balanced sensitivity, 90-day window — same
  // defaults as Hotspot Analysis's own default selection) --------------
  async function loadDashHotspots() {
    const list = document.getElementById('dashHotspotList');
    const params = new URLSearchParams({ window_days: 90, eps_meters: 250, min_points: 4 });
    const res = await fetch(window.BASE_URL + '/api/hotspots.php?' + params.toString());
    const data = await res.json();
    const countBadge = document.getElementById('dashHotspotCount');
    if (!res.ok || data.insufficient_data || !data.hotspots || !data.hotspots.length) {
      list.innerHTML = `<p class="text-muted small mb-0">${data.message || 'No hotspots found with default settings.'}</p>`;
      if (countBadge) countBadge.textContent = '0 active';
      return;
    }
    if (countBadge) countBadge.textContent = `${data.hotspots.length} active`;
    // Leaderboard style: hazard-colored dot, name, incidents as a trailing
    // number — mirrors the mockup's "Hotspot Ranking" list.
    list.innerHTML = data.hotspots
      .slice()
      .sort((a, b) => b.incident_count - a.incident_count)
      .slice(0, 10)
      .map(h => {
        const hz = hazardMeta(h.dominant_hazard);
        return `
          <div class="lyr" style="cursor:default">
            <i class="legend-dot" style="background:${hz.color};width:8px;height:8px;flex:none;margin:0"></i>
            <span class="fw-semibold small flex-fill">${h.dominant_hazard}</span>
            <span class="mono small text-muted">${h.incident_count}</span>
          </div>`;
      }).join('');
  }
  loadDashHotspots();
})();
