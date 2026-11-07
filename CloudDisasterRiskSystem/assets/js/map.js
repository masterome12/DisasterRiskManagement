// FR-03 Interactive Risk Map, FR-04 Base Layer Selection, FR-05/FR-06 Overlays,
// FR-07 Navigation, FR-08 Feature Popup, FR-14 Search, plus click-to-report
// (quick-report.js) which reuses this map instance via window.riskMap.
(function () {
  const center = window.APP.center;
  const map = L.map('map').setView(center, 12);
  window.riskMap = map;

  const baseLayers = {
    street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }),
    satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri', maxZoom: 19,
    }),
  };
  let currentBase = baseLayers.street.addTo(map);

  document.querySelectorAll('.base-layer-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      map.removeLayer(currentBase);
      // FR-04 alternate flow: fall back to street layer if a tile provider fails.
      const chosen = baseLayers[btn.dataset.layer] || baseLayers.street;
      currentBase = chosen.addTo(map);
    });
  });
  baseLayers.satellite.on('tileerror', () => {
    map.removeLayer(currentBase);
    currentBase = baseLayers.street.addTo(map);
  });

  const clusterGroup = L.markerClusterGroup();

  // ---- FR-03: load approved incident markers, tagged with a hazard icon --
  async function loadIncidents() {
    const res = await fetch(window.BASE_URL + '/api/incidents.php?action=list');
    const data = await res.json();
    const badge = document.getElementById('markerCountBadge');
    clusterGroup.clearLayers();

    if (!data.incidents || !data.incidents.length) {
      badge.textContent = data.message || 'No data available';
      return;
    }
    badge.textContent = `${data.count} incident(s) on map`;

    data.incidents.forEach(inc => {
      const marker = L.marker([inc.latitude, inc.longitude], {
        icon: hazardDivIcon(inc.hazard_type, inc.severity),
      });
      // FR-08: attribute popup, omitting missing fields rather than erroring.
      const rows = [
        ['Hazard', inc.hazard_type],
        ['Severity', inc.severity],
        ['Barangay', inc.barangay],
        ['Reported by', inc.reporter],
        ['Reported', new Date(inc.created_at).toLocaleString()],
        ['Description', inc.description],
      ].filter(([, v]) => v);
      let html = `<div style="min-width:200px">` +
        rows.map(([k, v]) => `<div><b>${k}:</b> ${v}</div>`).join('') + `</div>`;
      if (inc.photo_path) html += `<img src="${window.BASE_URL}/${inc.photo_path}" style="width:100%;margin-top:6px;border-radius:4px;">`;
      marker.bindPopup(html);
      clusterGroup.addLayer(marker);
    });
  }
  map.addLayer(clusterGroup);
  window.reloadIncidents = loadIncidents;
  loadIncidents();

  // ---- Hazard legend (collapsible on small screens) ------------------------
  const legend = L.control({ position: 'bottomleft' });
  legend.onAdd = function () {
    const div = L.DomUtil.create('div', 'hazard-legend');
    div.innerHTML = `
      <button type="button" class="hazard-legend-toggle"><i class="bi bi-info-circle"></i> Legend</button>
      <div class="hazard-legend-body d-none">
        ${HAZARD_PRIMARY.map(h => `<div class="hazard-legend-row"><span class="hazard-legend-dot" style="background:${h.color}"><i class="bi ${h.icon}"></i></span>${h.key}</div>`).join('')}
      </div>`;
    L.DomEvent.disableClickPropagation(div);
    div.querySelector('.hazard-legend-toggle').addEventListener('click', () => {
      div.querySelector('.hazard-legend-body').classList.toggle('d-none');
    });
    return div;
  };
  legend.addTo(map);

  // ---- FR-05 / FR-06: geospatial overlay layers with visibility toggles --
  const overlayLayers = {}; // id -> L.geoJSON
  // Above this many point features, auto-plotting every one on the public
  // map stops being a useful overlay and starts being visual noise (a
  // large imported CSV can carry thousands of rows) — those layers stay
  // available as a table in admin/data-management.php instead of flooding
  // the map with markers.
  const MAX_AUTO_PLOT_POINTS = 300;

  async function loadOverlayLayers() {
    if (!window.APP.isAuthenticated) return;
    const listEl = document.getElementById('layerToggleList');
    try {
      const res = await fetch(window.BASE_URL + '/api/layers.php?action=list');
      const data = await res.json();
      if (!data.layers || !data.layers.length) {
        if (listEl) listEl.innerHTML = '<li class="text-muted small px-2">No layers available.</li>';
        return;
      }
      if (listEl) {
        listEl.innerHTML = data.layers.map(l => {
          const tooMany = (l.mapped_count ?? 0) > MAX_AUTO_PLOT_POINTS;
          return `
          <li class="form-check px-2">
            <input class="form-check-input" type="checkbox" id="ov-${l.id}" ${tooMany ? 'disabled' : 'checked'}>
            <label class="form-check-label small" for="ov-${l.id}">${l.name}${tooMany ? ` <span class="text-muted">(${l.mapped_count} points — too many to map, see Geospatial Data)</span>` : ''}</label>
          </li>`;
        }).join('');
      }
      data.layers.forEach(l => {
        const pointCount = (l.geojson.features || []).filter(f => f.geometry && f.geometry.type === 'Point').length;
        if (pointCount > MAX_AUTO_PLOT_POINTS) return; // table-only per MAX_AUTO_PLOT_POINTS above
        try {
          const gj = L.geoJSON(l.geojson, {
            // Muted slate, not hazard-red — an uploaded overlay dataset
            // (a boundary, a baseline layer) is reference data, not an
            // active hazard, and shouldn't visually compete with the real
            // hazard markers on the same map.
            style: { color: '#475569', weight: 2, fillOpacity: 0.1 },
            pointToLayer: (feature, latlng) => L.circleMarker(latlng, { radius: 4, color: '#475569', weight: 1, fillOpacity: 0.5 }),
          }).addTo(map);
          overlayLayers[l.id] = gj;
          const cb = document.getElementById(`ov-${l.id}`);
          if (cb) cb.addEventListener('change', () => {
            if (cb.checked) map.addLayer(gj); else map.removeLayer(gj);
          });
        } catch (e) {
          // FR-05 alternate flow: inline error for the failing layer only.
          if (listEl) listEl.insertAdjacentHTML('beforeend', `<li class="text-danger small px-2">Failed to load "${l.name}"</li>`);
        }
      });
    } catch (e) {
      if (listEl) listEl.innerHTML = '<li class="text-danger small px-2">Could not load layers.</li>';
    }
  }
  loadOverlayLayers();

  // ---- FR-14: search -------------------------------------------------------
  const searchBox = document.getElementById('searchBox');
  const searchResults = document.getElementById('searchResults');
  let searchTimer;
  searchBox.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchBox.value.trim();
    if (!q) { searchResults.classList.add('d-none'); return; }
    searchTimer = setTimeout(async () => {
      const res = await fetch(window.BASE_URL + '/api/search.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      if (!data.results.length) {
        searchResults.innerHTML = '<div class="list-group-item text-muted small">No results found.</div>';
      } else {
        searchResults.innerHTML = data.results.map(r => `
          <button type="button" class="list-group-item list-group-item-action small" data-lat="${r.lat}" data-lng="${r.lng}">
            <i class="bi bi-${r.type === 'barangay' ? 'signpost' : 'geo-alt'}"></i> ${r.label}
          </button>`).join('');
      }
      searchResults.classList.remove('d-none');
    }, 300);
  });
  searchResults.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-lat]');
    if (!btn) return;
    map.setView([parseFloat(btn.dataset.lat), parseFloat(btn.dataset.lng)], 17);
    searchResults.classList.add('d-none');
    searchBox.value = '';
  });
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#searchBox') && !e.target.closest('#searchResults')) {
      searchResults.classList.add('d-none');
    }
  });

  // ---- Responsiveness: recompute tile layout on rotate/resize -------------
  window.addEventListener('resize', () => map.invalidateSize());
  window.addEventListener('orientationchange', () => setTimeout(() => map.invalidateSize(), 200));
})();
