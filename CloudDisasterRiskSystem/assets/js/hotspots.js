// AI FEATURES — FR-19 (DBSCAN hotspot detection) and FR-20 (Phase 2 forecast).
(function () {
  // ======================================================================
  // FR-19: Hotspot Detection
  // ======================================================================
  const map = L.map('hotspotMap').setView(window.APP.center, 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
  }).addTo(map);

  let heatLayer = null;
  let hotspotCircles = L.layerGroup().addTo(map);

  // Legend: same hazard colors used everywhere else (map markers, report picker),
  // so a blue hotspot circle always means "flooding" no matter where you look.
  const hotspotLegend = L.control({ position: 'bottomleft' });
  hotspotLegend.onAdd = function () {
    const div = L.DomUtil.create('div', 'hazard-legend');
    div.innerHTML = `
      <button type="button" class="hazard-legend-toggle"><i class="bi bi-info-circle"></i> Legend</button>
      <div class="hazard-legend-body d-none">
        ${HAZARD_ALL.map(h => `<div class="hazard-legend-row"><span class="hazard-legend-dot" style="background:${h.color}"><i class="bi ${h.icon}"></i></span>${h.key}</div>`).join('')}
      </div>`;
    L.DomEvent.disableClickPropagation(div);
    div.querySelector('.hazard-legend-toggle').addEventListener('click', () => {
      div.querySelector('.hazard-legend-body').classList.toggle('d-none');
    });
    return div;
  };
  hotspotLegend.addTo(map);

  // One "Sensitivity" dropdown stands in for the two raw DBSCAN parameters
  // (eps radius + min points) — friendlier than asking a non-technical user
  // to tune a clustering algorithm directly.
  const SENSITIVITY = {
    loose:    { eps: 400, minPoints: 3 },
    balanced: { eps: 250, minPoints: 4 },
    strict:   { eps: 150, minPoints: 5 },
  };

  // Populate the hazard filter once, from the shared catalogue — available
  // immediately, no need to wait for a detection run first.
  const hazardFilterSel = document.getElementById('hotspotHazardFilter');
  HAZARD_ALL.forEach(h => {
    const o = document.createElement('option');
    o.value = h.key; o.textContent = h.key;
    hazardFilterSel.appendChild(o);
  });
  hazardFilterSel.addEventListener('change', () => {
    if (lastHotspotsData) renderHotspots(lastHotspotsData, hazardFilterSel.value);
  });

  let lastHotspotsData = null;

  window.runHotspots = async function () {
    const windowDays = document.getElementById('hotspotWindow').value;
    const s = SENSITIVITY[document.getElementById('hotspotSensitivity').value] || SENSITIVITY.balanced;
    const meta = document.getElementById('hotspotMeta');
    const notice = document.getElementById('insufficientNotice');
    const table = document.getElementById('hotspotTable');

    meta.textContent = 'Running DBSCAN…';
    notice.classList.add('d-none');

    const params = new URLSearchParams({ window_days: windowDays, eps_meters: s.eps, min_points: s.minPoints });
    const res = await fetch(window.BASE_URL + '/api/hotspots.php?' + params.toString());
    const data = await res.json();

    if (!res.ok) { meta.textContent = 'Error running detection.'; return; }

    if (data.insufficient_data) {
      lastHotspotsData = null;
      notice.textContent = data.message;
      notice.classList.remove('d-none');
      table.innerHTML = '<tr><td colspan="4" class="text-muted">Not enough data.</td></tr>';
      meta.textContent = '';
      if (heatLayer) map.removeLayer(heatLayer);
      hotspotCircles.clearLayers();
      return;
    }

    lastHotspotsData = data;
    renderHotspots(data, hazardFilterSel.value);
  };

  // Renders (or re-renders) the map + table from an already-fetched result.
  // The hazard filter is applied here, client-side — it's a display filter
  // over the clusters DBSCAN already found, not a reason to re-run
  // detection, and it drives the map markers and the table together so
  // they can never show a different set of hotspots from each other.
  function renderHotspots(data, hazardFilter) {
    const meta = document.getElementById('hotspotMeta');
    const table = document.getElementById('hotspotTable');
    const hotspots = hazardFilter ? data.hotspots.filter(h => h.dominant_hazard === hazardFilter) : data.hotspots;

    meta.textContent = hazardFilter
      ? `${hotspots.length} of ${data.cluster_count} hotspot(s) match "${hazardFilter}" (${data.input_count} points analyzed, ${data.noise_count} isolated point(s) ignored).`
      : `${data.input_count} points analyzed → ${data.cluster_count} hotspot(s), ${data.noise_count} isolated point(s) ignored.`;

    // ---- KPI row + cluster detail + hazard breakdown (real numbers only,
    // derived from the same hotspots array the map/table already use) -----
    const kpiRow = document.getElementById('hotspotKpiRow');
    const detailWrap = document.getElementById('hotspotDetailWrap');
    if (!data.hotspots.length) {
      kpiRow.style.display = 'none';
      detailWrap.style.display = 'none';
    } else {
      kpiRow.style.display = '';
      detailWrap.style.display = '';
      const clusteredCount = data.hotspots.reduce((s, h) => s + h.incident_count, 0);
      const pct = data.input_count ? Math.round((clusteredCount / data.input_count) * 100) : 0;
      document.getElementById('hkClusters').textContent = data.cluster_count;
      document.getElementById('hkClustered').innerHTML = `${clusteredCount} <span class="small text-muted">/ ${data.input_count} (${pct}%)</span>`;
      document.getElementById('hkNoise').textContent = data.noise_count;

      const largest = [...data.hotspots].sort((a, b) => b.incident_count - a.incident_count)[0];
      const lhz = hazardMeta(largest.dominant_hazard);
      document.getElementById('hkLargest').innerHTML = `${largest.incident_count} <span class="small" style="color:${lhz.color}">${largest.dominant_hazard}</span>`;

      const top = largest;
      document.getElementById('hotspotTopDetail').innerHTML = `
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="legend-dot" style="background:${lhz.color}"></span>
          <b>${top.dominant_hazard}</b>
        </div>
        <p class="text-muted small mb-0">
          ${top.incident_count} incidents within a ${top.radius_meters}m radius
          (${(Math.PI * (top.radius_meters / 1000) ** 2).toFixed(2)} km² footprint).
        </p>`;

      const byHazard = {};
      data.hotspots.forEach(h => { byHazard[h.dominant_hazard] = (byHazard[h.dominant_hazard] || 0) + h.incident_count; });
      const maxHz = Math.max(...Object.values(byHazard));
      document.getElementById('hotspotHazardBars').innerHTML = Object.entries(byHazard)
        .sort((a, b) => b[1] - a[1])
        .map(([hz, n]) => {
          const m = hazardMeta(hz);
          return `<div class="hbar-row"><span>${hz}</span><span class="hbar-track"><i class="hbar-fill" style="width:${(n / maxHz) * 100}%;background:${m.color}"></i></span><span class="hbar-n">${n}</span></div>`;
        }).join('');
    }

    // Heatmap of raw incident density (unaffected by the hazard filter —
    // it's the underlying data density, not the derived hotspot list).
    if (heatLayer) map.removeLayer(heatLayer);
    heatLayer = L.heatLayer(data.heat_points, { radius: 25, blur: 20 }).addTo(map);

    // Cluster boundary circles + numbered markers, colored by dominant hazard.
    hotspotCircles.clearLayers();
    const bounds = [];
    hotspots.forEach((h, i) => {
      const hz = hazardMeta(h.dominant_hazard);
      const circle = L.circle([h.centroid_lat, h.centroid_lng], {
        radius: Math.max(h.radius_meters, 40),
        color: hz.color, weight: 2, fillColor: hz.color, fillOpacity: 0.15,
      }).bindPopup(`<b>Hotspot #${i + 1}</b><br><i class="bi ${hz.icon}"></i> Dominant hazard: ${h.dominant_hazard}<br>${h.incident_count} incidents<br>Radius: ${h.radius_meters}m`);
      hotspotCircles.addLayer(circle);
      L.marker([h.centroid_lat, h.centroid_lng], {
        icon: L.divIcon({ className: '', html: `<div class="hazard-marker" style="width:26px;height:26px;background:${hz.color};font-size:11px;font-weight:bold;">${i + 1}</div>` }),
      }).addTo(hotspotCircles);
      bounds.push([h.centroid_lat, h.centroid_lng]);
    });
    // Always frame the hotspots just found — a prior scroll-wheel zoom/pan
    // on the map (easy to trigger by accident) would otherwise leave them
    // outside the visible area with no way back except panning around.
    if (bounds.length) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });

    if (!hotspots.length) {
      table.innerHTML = `<tr><td colspan="4" class="text-muted">${hazardFilter ? 'No hotspots match this hazard filter.' : 'No dense clusters found with current settings — try Loose sensitivity or a wider time range.'}</td></tr>`;
      return;
    }
    table.innerHTML = hotspots.map((h, i) => {
      const m = hazardMeta(h.dominant_hazard);
      return `
      <tr>
        <td><span class="badge" style="background:${m.color}">#${i + 1}</span></td>
        <td><span class="legend-dot" style="background:${m.color}"></span>${h.dominant_hazard}</td>
        <td>${h.incident_count}</td>
        <td>${h.radius_meters}</td>
      </tr>`;
    }).join('');
  }

  // ======================================================================
  // FR-20: Predictive Forecast
  // ======================================================================

  // Populate barangay filter for the single-scope trend chart.
  populateBarangaySelect(document.getElementById('forecastBarangay'), 'All South Caloocan');

  let forecastChart = null;
  let forecastHazardChart = null;

  window.runForecast = async function () {
    const barangayId = document.getElementById('forecastBarangay').value;
    const badge = document.getElementById('forecastBadge');
    const notice = document.getElementById('forecastNotice');
    const dayStrip = document.getElementById('forecastDayStrip');
    const statCards = document.getElementById('forecastStatCards');
    const btWrap = document.getElementById('backtestDetailWrap');
    badge.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    notice.className = 'alert d-none';

    const qs = barangayId ? `?barangay_id=${barangayId}` : '';
    const res = await fetch(window.BASE_URL + '/api/forecast.php' + qs);
    const data = await res.json();
    badge.innerHTML = '';

    if (!data.available) {
      notice.classList.remove('d-none');
      notice.classList.add('alert-warning');
      notice.textContent = data.message;
      dayStrip.style.display = 'none';
      statCards.style.display = 'none';
      btWrap.style.display = 'none';
      if (forecastChart) { forecastChart.destroy(); forecastChart = null; }
      if (forecastHazardChart) { forecastHazardChart.destroy(); forecastHazardChart = null; }
      return;
    }

    if (data.accepted) {
      badge.innerHTML = `<span class="badge text-bg-success">Backtest passed (MAE ${data.backtest_mae} ≤ ${data.threshold_mae}) — forecast enabled</span>`;
    } else {
      badge.innerHTML = `<span class="badge text-bg-danger">Backtest failed (MAE ${data.backtest_mae ?? 'n/a'} vs threshold ${data.threshold_mae}) — forecast withheld</span>`;
    }
    notice.classList.remove('d-none');
    notice.classList.add(data.accepted ? 'alert-success' : 'alert-warning');
    notice.textContent = data.note;

    // ---- Headline stat cards --------------------------------------------
    const s = data.stats;
    statCards.style.display = '';
    document.getElementById('statAvg').textContent = s.average;
    document.getElementById('statRange').textContent = `${s.min}–${s.max}`;
    if (s.trend_pct_change === null) {
      document.getElementById('statTrend').innerHTML = '<span class="text-muted">n/a</span>';
    } else {
      const up = s.trend_pct_change > 0;
      const flat = Math.abs(s.trend_pct_change) < 1;
      document.getElementById('statTrend').innerHTML = flat
        ? '<span class="text-muted">Flat</span>'
        : `<span class="${up ? 'text-danger' : 'text-success'}"><i class="bi bi-arrow-${up ? 'up' : 'down'}-right"></i> ${Math.abs(s.trend_pct_change)}%</span>`;
    }
    document.getElementById('statMae').innerHTML = data.backtest_mae !== null
      ? `${data.backtest_mae} <span class="text-muted small">/ ${data.threshold_mae}</span>`
      : '<span class="text-muted">n/a</span>';

    // ---- Trend chart, with a real uncertainty band (±backtest MAE, not a
    // fabricated confidence interval) around the forecast line ------------
    const labels = [...data.month_labels, ...data.forecast.map(f => f.period)];
    const observed = [...data.observed, ...Array(data.forecast.length).fill(null)];
    const predicted = [...Array(data.observed.length - 1).fill(null), data.observed[data.observed.length - 1], ...data.forecast.map(f => f.predicted_incidents)];
    const mae = data.backtest_mae || 0;
    const bandUpper = predicted.map(v => v === null ? null : v + mae);
    const bandLower = predicted.map(v => v === null ? null : Math.max(0, v - mae));

    if (forecastChart) forecastChart.destroy();
    forecastChart = new Chart(document.getElementById('forecastChart'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: '±MAE band', data: bandUpper, borderWidth: 0, backgroundColor: 'rgba(220,53,69,0.12)', fill: '+1', pointRadius: 0, spanGaps: false },
          { label: '±MAE band (lower)', data: bandLower, borderWidth: 0, backgroundColor: 'rgba(220,53,69,0.12)', fill: false, pointRadius: 0, spanGaps: false, hidden: false },
          { label: 'Observed', data: observed, borderColor: '#0d6efd', spanGaps: false },
          { label: 'Forecast (provisional)', data: predicted, borderColor: '#dc3545', borderDash: [6, 4], spanGaps: false },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { filter: item => !item.text.includes('band (lower)') } } },
      },
    });

    // ---- Day-strip forecast cards (real predicted value ±MAE per period) --
    if (data.forecast.length) {
      dayStrip.style.display = '';
      const s2 = data.stats;
      dayStrip.innerHTML = data.forecast.map(f => {
        const band = f.predicted_incidents >= s2.average * 1.3 ? ['Elevated', '#a84b06']
          : f.predicted_incidents <= s2.average * 0.7 ? ['Low', '#0f5f26']
          : ['Moderate', '#d9650f'];
        const pct = Math.min(100, Math.round((f.predicted_incidents / Math.max(s2.max, 1)) * 100));
        return `
          <div class="day-card">
            <div class="dn">${f.period}</div>
            <div class="dv">${f.predicted_incidents}</div>
            <div class="db"><i style="width:${pct}%;background:${band[1]}"></i></div>
            <span class="trend-chip" style="background:${band[1]}22;color:${band[1]}">${band[0]}</span>
          </div>`;
      }).join('');
    } else {
      dayStrip.style.display = 'none';
    }

    // ---- Hazard composition doughnut ---------------------------------------
    if (forecastHazardChart) forecastHazardChart.destroy();
    if (data.hazard_breakdown && data.hazard_breakdown.length) {
      const hzLabels = data.hazard_breakdown.map(h => h.hazard_type);
      const hzColors = data.hazard_breakdown.map(h => hazardMeta(h.hazard_type).color);
      forecastHazardChart = new Chart(document.getElementById('forecastHazardChart'), {
        type: 'doughnut',
        data: {
          labels: hzLabels,
          datasets: [{ data: data.hazard_breakdown.map(h => Number(h.total)), backgroundColor: hzColors }],
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } },
      });
    }

    // ---- Backtest detail table ----------------------------------------
    if (data.backtest_detail && data.backtest_detail.length) {
      btWrap.style.display = '';
      document.getElementById('backtestDetailTable').innerHTML = data.backtest_detail.map(d => {
        const errClass = d.error <= data.threshold_mae ? 'text-success' : 'text-danger';
        return `<tr><td>${d.period}</td><td>${d.actual}</td><td>${d.predicted}</td><td class="${errClass}">±${d.error}</td></tr>`;
      }).join('');
    } else {
      btWrap.style.display = 'none';
    }
  };

  // ---- Predicted Risk by Barangay: table only (no map — kept out of the
  // way; use the "Barangay" dropdown above to inspect one on the chart) ---
  let forecastTableLoaded = false;

  function trendLabel(entry) {
    if (!entry.available) return 'Insufficient data';
    if (!entry.accepted) return 'Backtest failed — withheld';
    const diff = entry.next_period_predicted - entry.last_observed;
    if (diff > 0.5) return 'Rising risk';
    if (diff < -0.5) return 'Declining';
    return 'Stable';
  }

  let forecastBarangayChart = null;

  async function loadForecastMap() {
    const res = await fetch(window.BASE_URL + '/api/forecast.php?action=map');
    const data = await res.json();
    if (!res.ok || !data.barangays) return;

    // ---- Citywide summary cards -----------------------------------------
    if (data.summary) {
      const sum = data.summary;
      document.getElementById('forecastSummaryCards').style.display = '';
      document.getElementById('sumCitywide').textContent = sum.citywide_predicted_next_period;
      document.getElementById('sumAccepted').textContent = `${sum.trained_and_accepted}/${sum.total_barangays}`;
      document.getElementById('sumRising').textContent = sum.rising_count;
      document.getElementById('sumDeclining').textContent = sum.declining_count;
      document.getElementById('sumStable').textContent = sum.stable_count;
      document.getElementById('sumWithheld').textContent = sum.insufficient_or_withheld;

      // Top-10 predicted bar chart
      if (forecastBarangayChart) forecastBarangayChart.destroy();
      const top = sum.top_predicted || [];
      if (top.length) {
        forecastBarangayChart = new Chart(document.getElementById('forecastBarangayChart'), {
          type: 'bar',
          data: {
            labels: top.map(t => t.barangay_name),
            datasets: [{
              label: 'Predicted next month',
              data: top.map(t => t.next_period_predicted),
              backgroundColor: top.map(t => hazardMeta(t.dominant_hazard).color),
            }],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 30 } } },
          },
        });
      }
    }

    // ---- Rising-Risk Watchlist: real barangays whose forecast actually
    // rose vs. last observed, ranked by the size of that rise -------------
    const watchlist = document.getElementById('forecastWatchlist');
    if (watchlist) {
      const rising = data.barangays
        .filter(b => b.available && b.accepted && b.next_period_predicted !== null && b.last_observed !== null)
        .map(b => ({ ...b, diff: b.next_period_predicted - b.last_observed }))
        .filter(b => b.diff > 0.5)
        .sort((a, b) => b.diff - a.diff)
        .slice(0, 5);
      watchlist.innerHTML = rising.length ? rising.map(b => {
        const hz = hazardMeta(b.dominant_hazard);
        return `
          <div class="top-list-item">
            <span class="legend-dot" style="background:${hz.color}"></span>
            <div class="flex-fill">
              <div class="fw-semibold small">${b.barangay_name}</div>
              <div class="text-muted small">${b.dominant_hazard} · predicted ${b.next_period_predicted} <span class="text-danger">(+${b.diff.toFixed(1)})</span></div>
            </div>
          </div>`;
      }).join('') : '<p class="text-muted small">No barangay is currently trending upward.</p>';
    }

    const tbody = document.getElementById('forecastMapTable');
    tbody.innerHTML = data.barangays.map(entry => {
      const confident = entry.available && entry.accepted && entry.next_period_predicted !== null;
      const hz = hazardMeta(entry.dominant_hazard);
      const color = confident ? hz.color : '#9ca3af';
      const predicted = confident ? entry.next_period_predicted : '—';
      const backtest = entry.available
        ? `MAE ${entry.backtest_mae ?? 'n/a'}`
        : `${entry.trained_on_months}/${data.min_months} mo.`;
      let delta = '<span class="text-muted">—</span>';
      if (confident && entry.last_observed !== null) {
        const diff = entry.next_period_predicted - entry.last_observed;
        const sign = diff > 0 ? '+' : '';
        delta = `<span class="${diff > 0.5 ? 'text-danger' : diff < -0.5 ? 'text-success' : 'text-muted'}">${sign}${diff.toFixed(1)}</span>`;
      }
      return `
        <tr>
          <td>${entry.barangay_name}<div class="text-muted small"><span class="legend-dot" style="background:${hz.color}"></span>${entry.dominant_hazard}</div></td>
          <td>${predicted}</td>
          <td>${delta}</td>
          <td><span class="badge" style="background:${color}" title="${backtest}">${trendLabel(entry)}</span></td>
        </tr>`;
    }).join('');
  }

  // Load once the Forecast tab is first opened.
  document.querySelector('[data-bs-target="#forecastTab"]').addEventListener('shown.bs.tab', () => {
    if (!forecastTableLoaded) {
      forecastTableLoaded = true;
      loadForecastMap();
    }
  });
})();
