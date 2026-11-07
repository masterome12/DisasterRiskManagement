// FR-13 Statistical Dashboard & Visualization.
(async function () {
  const res = await fetch(window.BASE_URL + '/api/dashboard.php');
  const data = await res.json();

  if (!res.ok) {
    document.getElementById('statCards').innerHTML = `<div class="alert alert-danger">Failed to load dashboard data.</div>`;
    return;
  }

  const totalApproved = data.by_type.reduce((s, r) => s + Number(r.total), 0);
  const modMap = Object.fromEntries(data.moderation.map(m => [m.status, m.total]));

  function kpi(colorClass, icon, value, label, delta, deltaClass) {
    return `
      <div class="col-md-3">
        <div class="kpi-card ${colorClass}">
          <div class="kpi-decor"></div>
          <div class="kpi-icon"><i class="bi ${icon}"></i></div>
          <div class="kpi-value">${value}</div>
          <div class="kpi-label">${label}</div>
          <div class="kpi-delta ${deltaClass}">${delta}</div>
        </div>
      </div>`;
  }

  document.getElementById('statCards').innerHTML = [
    kpi('kpi-green', 'bi-check-circle-fill', totalApproved, 'Approved Incidents', '&nbsp;', 'text-success'),
    kpi('kpi-orange', 'bi-hourglass-split', modMap.pending || 0, 'Pending Review', '&nbsp;', 'text-warning'),
    kpi('kpi-green', 'bi-x-circle', modMap.declined || 0, 'Declined', '&nbsp;', 'text-muted'),
    kpi('kpi-orange', 'bi-geo-alt-fill', data.by_barangay.length, 'Barangays Affected', '&nbsp;', 'text-muted'),
  ].join('');

  // Green-forward palette (with orange accents) for multi-series charts.
  const palette = ['#16a34a', '#f97316', '#22c55e', '#fb923c', '#0f7a3d', '#facc15', '#4ade80', '#9ca3af'];

  new Chart(document.getElementById('chartByType'), {
    type: 'bar',
    data: {
      labels: data.by_type.map(r => r.hazard_type),
      datasets: [{ label: 'Incidents', data: data.by_type.map(r => r.total), backgroundColor: palette, borderRadius: 4 }],
    },
    options: { plugins: { legend: { display: false } }, responsive: true },
  });

  new Chart(document.getElementById('chartByBarangay'), {
    type: 'bar',
    data: {
      labels: data.by_barangay.map(r => r.barangay),
      datasets: [{ label: 'Incidents', data: data.by_barangay.map(r => r.total), backgroundColor: '#16a34a', borderRadius: 4 }],
    },
    options: { indexAxis: 'y', plugins: { legend: { display: false } }, responsive: true },
  });

  new Chart(document.getElementById('chartBySeverity'), {
    type: 'doughnut',
    data: {
      labels: data.by_severity.map(r => r.severity),
      datasets: [{ data: data.by_severity.map(r => r.total), backgroundColor: palette }],
    },
    options: { responsive: true, cutout: '65%' },
  });

  new Chart(document.getElementById('chartTrend'), {
    type: 'line',
    data: {
      labels: data.trend.map(r => r.month),
      datasets: [{
        label: 'Incidents / month', data: data.trend.map(r => r.total),
        borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.12)', tension: 0.3, fill: true,
      }],
    },
    options: { responsive: true, plugins: { legend: { display: false } } },
  });
})();
