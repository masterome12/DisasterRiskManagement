// Admin Dashboard overview (admin/index.php) — KPI cards + charts + top list.
let _trendChart = null, _statusChart = null, _peakChart = null;

function kpiCard(colorClass, icon, value, label, deltaText, deltaClass) {
  return `
    <div class="col-md-6 col-xl-3">
      <div class="kpi-card ${colorClass}">
        <div class="kpi-decor"></div>
        <div class="kpi-icon"><i class="bi ${icon}"></i></div>
        <div class="kpi-value">${value}</div>
        <div class="kpi-label">${label}</div>
        <div class="kpi-delta ${deltaClass}">${deltaText}</div>
      </div>
    </div>`;
}

/** Same idea, styled as a trend-chip pill (▲/▼) next to the number — used
 * where a KPI has a genuine period-over-period delta to show. */
function kpiTrend(colorClass, icon, value, trendVal, trendUp, label) {
  const chip = trendVal === null ? ''
    : `<span class="trend-chip ${trendUp ? 'trend-up' : 'trend-down'}">${trendUp ? '▲' : '▼'} ${Math.abs(trendVal)}</span>`;
  return `
    <div class="col-md-6 col-xl-3">
      <div class="kpi-card ${colorClass}">
        <div class="kpi-decor"></div>
        <div class="kpi-icon"><i class="bi ${icon}"></i></div>
        <div class="d-flex align-items-baseline gap-2"><div class="kpi-value">${value}</div>${chip}</div>
        <div class="kpi-label">${label}</div>
      </div>
    </div>`;
}

async function loadOverview() {
  const res = await fetch(window.BASE_URL + '/api/dashboard.php');
  const data = await res.json();
  if (!res.ok || !data.ok) {
    document.getElementById('kpiRow').innerHTML = '<div class="col-12"><div class="alert alert-danger">Failed to load dashboard data.</div></div>';
    return;
  }
  const o = data.overview;

  // ---- KPI cards ----------------------------------------------------------
  document.getElementById('kpiRow').innerHTML = [
    kpiTrend('kpi-green', 'bi-people-fill', o.total_users, o.users_this_month, true, 'Total Users · this month'),
    kpiCard('kpi-orange', 'bi-layers-fill', o.total_layers, 'Geospatial Layers', `${o.visible_layers} visible`, 'text-success'),
    kpiTrend('kpi-green', 'bi-calendar2-check-fill', o.total_reports, o.reports_this_month, true, 'Incident Reports · this month'),
    kpiCard('kpi-orange', 'bi-exclamation-triangle-fill', o.pending_count, 'Pending Reviews', `${o.pending_critical} critical`, o.pending_critical > 0 ? 'text-danger' : 'text-success'),
  ].join('');

  // ---- Incident Trend (line, green) ---------------------------------------
  if (_trendChart) _trendChart.destroy();
  _trendChart = new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
      labels: data.trend.map(r => r.month),
      datasets: [{
        label: 'Approved incidents', data: data.trend.map(r => r.total),
        borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.12)',
        fill: true, tension: 0.35, pointBackgroundColor: '#16a34a',
      }],
    },
    options: { responsive: true, plugins: { legend: { display: false } } },
  });

  // ---- Report Status (donut: approved green / pending orange / declined gray) --
  const modMap = Object.fromEntries(data.moderation.map(m => [m.status, m.total]));
  if (_statusChart) _statusChart.destroy();
  _statusChart = new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Pending', 'Declined'],
      datasets: [{
        data: [modMap.approved || 0, modMap.pending || 0, modMap.declined || 0],
        backgroundColor: ['#16a34a', '#f97316', '#9ca3af'],
        borderWidth: 0,
      }],
    },
    options: { responsive: true, cutout: '68%', plugins: { legend: { position: 'bottom' } } },
  });

  // ---- Peak Reporting Hours (bar, green) -----------------------------------
  if (_peakChart) _peakChart.destroy();
  _peakChart = new Chart(document.getElementById('chartPeakHours'), {
    type: 'bar',
    data: {
      labels: o.peak_hours.map((_, h) => h + ':00'),
      datasets: [{ label: 'Reports', data: o.peak_hours, backgroundColor: '#16a34a', borderRadius: 4 }],
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } } } },
  });

  // ---- Top Barangays list ---------------------------------------------------
  const list = document.getElementById('topBarangaysList');
  const top = data.by_barangay.slice(0, 5);
  if (!top.length) {
    list.innerHTML = '<p class="text-muted small mb-0">No approved incidents yet.</p>';
  } else {
    list.innerHTML = top.map((b, i) => `
      <div class="top-list-item">
        <span class="top-list-rank">${i + 1}</span>
        <span class="flex-fill">${b.barangay}</span>
        <span class="fw-semibold text-success">${b.total}</span>
      </div>`).join('');
  }
}

loadOverview();
