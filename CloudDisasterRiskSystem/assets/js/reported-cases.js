// Reported Cases tab: browse the imported historical dataset, and predict
// next-year risk per barangay from it. The "Show top" filter drives both
// the prediction table and the map markers together, always in sync.
(function () {
  const tabBtn = document.querySelector('[data-bs-target="#reportedCasesTab"]');
  if (!tabBtn) return;

  // ---- Filter option population -------------------------------------------
  fetch(window.BASE_URL + '/api/reported_cases.php?action=meta').then(r => r.json()).then(data => {
    document.getElementById('rcTotalBadge').textContent = data.range.total.toLocaleString();
    document.getElementById('rcRangeBadge').textContent = `${data.range.min_year}–${data.range.max_year}`;

    const hazardSel = document.getElementById('rcHazard');
    data.hazards.forEach(h => { const o = document.createElement('option'); o.value = h; o.textContent = h; hazardSel.appendChild(o); });

    const districtSel = document.getElementById('rcDistrict');
    data.districts.forEach(d => { const o = document.createElement('option'); o.value = d; o.textContent = d; districtSel.appendChild(o); });

    document.getElementById('rcYearFrom').value = data.range.min_year;
    document.getElementById('rcYearTo').value = data.range.max_year;
  }).catch(() => {});

  // ---- Records table --------------------------------------------------------
  window.loadReportedCases = async function () {
    const params = new URLSearchParams({ action: 'list', limit: document.getElementById('rcTableLimit').value });
    const hazard = document.getElementById('rcHazard').value;
    const severity = document.getElementById('rcSeverity').value;
    const district = document.getElementById('rcDistrict').value;
    const yf = document.getElementById('rcYearFrom').value;
    const yt = document.getElementById('rcYearTo').value;
    if (hazard) params.set('hazard', hazard);
    if (severity) params.set('severity', severity);
    if (district) params.set('district', district);
    if (yf) params.set('year_from', yf);
    if (yt) params.set('year_to', yt);

    const tbody = document.getElementById('rcTable');
    tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Loading…</td></tr>';

    const res = await fetch(window.BASE_URL + '/api/reported_cases.php?' + params.toString());
    const data = await res.json();
    if (!res.ok || !data.rows) { tbody.innerHTML = '<tr><td colspan="9" class="text-danger">Failed to load.</td></tr>'; return; }

    if (!data.rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-muted">No records match these filters.</td></tr>';
      document.getElementById('rcShownNote').textContent = '';
      return;
    }

    tbody.innerHTML = data.rows.map(r => {
      const hz = hazardMeta(r.hazard_type);
      const detail = r.weather_condition || r.crime_category || r.fire_cause || (r.magnitude ? `M${r.magnitude}` : '—');
      return `
        <tr>
          <td>${r.occurred_on}</td>
          <td>${r.district}</td>
          <td>Brgy. ${r.barangay}</td>
          <td><span class="legend-dot" style="background:${hz.color}"></span>${r.hazard_type}</td>
          <td class="text-capitalize">${r.severity}</td>
          <td>${r.casualties}</td>
          <td>${r.injuries}</td>
          <td>₱${Number(r.estimated_damage_php).toLocaleString()}</td>
          <td class="text-muted small">${detail}</td>
        </tr>`;
    }).join('');
    document.getElementById('rcShownNote').textContent =
      `Showing ${data.rows.length} of ${data.total.toLocaleString()} matching record(s), most recent first.`;
  };

  // ---- Predicted Risk by Barangay: table only (no map — kept out of the
  // way; the "Show top" dropdown controls how many are listed) -----------
  let predictTableLoaded = false;

  window.loadReportedCasesPredictions = async function () {
    const limit = document.getElementById('rcPredictLimit').value;
    const tbody = document.getElementById('rcPredictTable');
    tbody.innerHTML = '<tr><td colspan="4" class="text-muted">Loading…</td></tr>';

    const res = await fetch(window.BASE_URL + '/api/reported_cases.php?action=predict&limit=' + limit);
    const data = await res.json();
    if (!res.ok || !data.barangays) { tbody.innerHTML = '<tr><td colspan="4" class="text-danger">Failed to load.</td></tr>'; return; }

    if (!data.barangays.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No barangay has enough history yet.</td></tr>';
      return;
    }

    tbody.innerHTML = data.barangays.map((b, i) => {
      const hz = hazardMeta(b.dominant_hazard);
      const predicted = b.accepted && b.predicted_next_year !== null ? b.predicted_next_year : '—';
      const badge = b.accepted
        ? `<span class="badge" style="background:${hz.color}">MAE ${b.backtest_mae}</span>`
        : `<span class="badge" style="background:#9ca3af">withheld</span>`;
      return `
        <tr>
          <td>#${i + 1} Brgy. ${b.barangay}<div class="text-muted small">${b.district}</div></td>
          <td><span class="legend-dot" style="background:${hz.color}"></span>${b.dominant_hazard}</td>
          <td>${predicted}</td>
          <td>${badge}</td>
        </tr>`;
    }).join('');
  };

  tabBtn.addEventListener('shown.bs.tab', () => {
    if (!predictTableLoaded) {
      predictTableLoaded = true;
      loadReportedCases();
      loadReportedCasesPredictions();
    }
  });
})();
