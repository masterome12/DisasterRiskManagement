<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$activeNav = 'moderation';
$pageTitle = 'Alerts & Incidents — ' . APP_NAME;
$pageHeading = 'Alerts & Incidents';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="pagehead">
  <div><div class="kicker">Operations · Queue</div><h1>Alerts &amp; Incidents</h1></div>
</div>
<p class="text-muted small">Approve reports to publish them on the public map, or decline with an optional note (FR-10).</p>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="kpi-card kpi-orange">
      <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
      <div class="kpi-value" id="queueCount">—</div>
      <div class="kpi-label">Awaiting review</div>
    </div>
  </div>
</div>

<div id="queue" class="row g-3 mt-1"></div>

<hr class="my-4">

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <h6 class="mb-0 me-2"><i class="bi bi-card-list"></i> All Reports</h6>
  <div class="seg" id="alertStatusSeg">
    <button type="button" class="active" data-status="">All</button>
    <button type="button" data-status="pending">Pending</button>
    <button type="button" data-status="approved">Approved</button>
    <button type="button" data-status="declined">Declined</button>
  </div>
  <input type="text" class="form-control form-control-sm ms-auto" id="alertSearch" placeholder="Filter by barangay or hazard" style="max-width:220px">
</div>
<div class="widget-card sticky-col-table" style="height:auto;padding:0;overflow:hidden;">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>ID</th><th>Hazard</th><th>Severity</th><th>Barangay</th><th>Reported</th><th>Status</th><th></th></tr></thead>
    <tbody id="alertsAllBody"><tr><td colspan="7" class="text-muted">Loading…</td></tr></tbody>
  </table>
</div>

<!-- Alert detail modal — real fields only (this app has no dispatch/
     timeline/casualty tracking, so those mockup panels are omitted
     rather than faked; the map, narrative, and a real prediction check
     for the report's barangay are shown instead). -->
<div class="modal fade" id="alertDetailModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="alertDetailTitle"><i class="bi bi-geo-alt-fill"></i> Report Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="alertDetailBody"><div class="text-muted">Loading…</div></div>
    </div>
  </div>
</div>

<script>window.APP = { center: [<?= AOI_CENTER_LAT ?>, <?= AOI_CENTER_LNG ?>] };</script>
<script>
async function loadQueue() {
  const res = await fetch(window.BASE_URL + '/api/incidents.php?action=pending');
  const data = await res.json();
  const el = document.getElementById('queue');
  document.getElementById('queueCount').textContent = data.incidents ? data.incidents.length : 0;
  if (!data.incidents || !data.incidents.length) {
    el.innerHTML = '<p class="text-muted">No reports awaiting review.</p>';
    return;
  }
  el.innerHTML = data.incidents.map(r => `
    <div class="col-md-6 col-lg-4">
      <div class="card shadow-sm h-100 rounded-4 overflow-hidden">
        ${r.photo_path ? `<img src="${window.BASE_URL}/${r.photo_path}" class="card-img-top" style="max-height:180px;object-fit:cover;">` : ''}
        <div class="card-body">
          <h6 class="card-title">${r.hazard_type} <span class="badge text-bg-secondary text-capitalize">${r.severity}</span></h6>
          <p class="small text-muted mb-1">${r.barangay ?? 'Unspecified barangay'} &middot; ${new Date(r.created_at).toLocaleString()}</p>
          <p class="small mb-1">${r.description ?? ''}</p>
          <p class="small text-muted">By ${r.reporter} (${r.reporter_email})</p>
          <div class="input-group input-group-sm mb-2">
            <input type="text" class="form-control" placeholder="Optional note" id="note-${r.id}">
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success flex-fill" onclick="moderate(${r.id}, 'approve')"><i class="bi bi-check-lg"></i> Approve</button>
            <button class="btn btn-sm btn-outline-danger flex-fill" onclick="moderate(${r.id}, 'decline')"><i class="bi bi-x-lg"></i> Decline</button>
          </div>
        </div>
      </div>
    </div>`).join('');
}

async function moderate(id, decision) {
  const note = document.getElementById(`note-${id}`).value;
  const res = await fetch(window.BASE_URL + '/api/incidents.php?action=moderate', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ id, decision, note })
  });
  const data = await res.json();
  if (!res.ok) { alert(data.error || 'Action failed.'); return; }
  loadQueue();
  loadAllAlerts();
}
loadQueue();

// ---- All Reports table (every status, not just pending) ------------------
let alertsCache = [];
const STATUS_BADGE = {
  pending: '<span class="badge text-bg-warning">Pending</span>',
  approved: '<span class="badge text-bg-success">Approved</span>',
  declined: '<span class="badge text-bg-danger">Declined</span>',
};

async function loadAllAlerts() {
  const status = document.querySelector('#alertStatusSeg button.active').dataset.status;
  const params = new URLSearchParams();
  if (status) params.set('status', status);
  const tbody = document.getElementById('alertsAllBody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Loading…</td></tr>';
  const res = await fetch(window.BASE_URL + '/api/incidents.php?action=all&' + params.toString());
  const data = await res.json();
  if (!res.ok || !data.incidents) { tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Failed to load.</td></tr>'; return; }
  alertsCache = data.incidents;
  renderAlertsTable();
}

function renderAlertsTable() {
  const q = document.getElementById('alertSearch').value.trim().toLowerCase();
  const rows = alertsCache.filter(r =>
    !q || (r.barangay ?? '').toLowerCase().includes(q) || r.hazard_type.toLowerCase().includes(q));
  const tbody = document.getElementById('alertsAllBody');
  if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No reports match.</td></tr>'; return; }
  tbody.innerHTML = rows.map(r => `
    <tr>
      <td class="text-muted small">#${r.id}</td>
      <td>${r.hazard_type}</td>
      <td class="text-capitalize">${r.severity}</td>
      <td>${r.barangay ?? '—'}</td>
      <td class="small">${new Date(r.created_at).toLocaleString()}</td>
      <td>${STATUS_BADGE[r.status] || r.status}</td>
      <td><button class="btn btn-sm btn-outline-secondary" onclick="viewAlert(${r.id})"><i class="bi bi-eye"></i> View</button></td>
    </tr>`).join('');
}

document.querySelectorAll('#alertStatusSeg button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#alertStatusSeg button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadAllAlerts();
  });
});
document.getElementById('alertSearch').addEventListener('input', renderAlertsTable);
loadAllAlerts();

// ---- Detail modal: real fields only, plus a genuine prediction check for
// the report's barangay (reuses the same forecast the Prediction page
// shows — not a fabricated "prediction check" score). --------------------
let alertDetailModal;
let alertDetailMap = null;

async function viewAlert(id) {
  if (!alertDetailModal) alertDetailModal = new bootstrap.Modal(document.getElementById('alertDetailModal'));
  const body = document.getElementById('alertDetailBody');
  body.innerHTML = '<div class="text-muted">Loading…</div>';
  alertDetailModal.show();

  const res = await fetch(window.BASE_URL + '/api/incidents.php?action=detail&id=' + id);
  const data = await res.json();
  if (!res.ok || !data.incident) { body.innerHTML = `<div class="text-danger">${data.error || 'Failed to load report.'}</div>`; return; }
  const r = data.incident;

  document.getElementById('alertDetailTitle').innerHTML =
    `<i class="bi bi-geo-alt-fill"></i> ${r.hazard_type} &middot; ${r.barangay ?? 'Unspecified barangay'}`;

  const timeline = [{ t: r.created_at, label: 'Report submitted', sub: r.reporter ? `By ${r.reporter}` : '' }];
  if (r.moderated_at) {
    timeline.push({
      t: r.moderated_at,
      label: r.status === 'approved' ? 'Approved' : r.status === 'declined' ? 'Declined' : 'Reviewed',
      sub: [r.moderator ? `By ${r.moderator}` : '', r.moderation_note].filter(Boolean).join(' — '),
    });
  }

  body.innerHTML = `
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Reported</div><div class="kpi-value" style="font-size:1.2rem">${new Date(r.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</div><div class="small text-muted">${new Date(r.created_at).toLocaleDateString()}</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Severity</div><div class="kpi-value text-capitalize" style="font-size:1.2rem">${r.severity}</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Status</div><div style="margin-top:.3rem">${STATUS_BADGE[r.status] || r.status}</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Barangay</div><div class="kpi-value" style="font-size:1.2rem">${r.barangay ?? '—'}</div></div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-8">
        <div id="alertDetailMap" style="height:280px;border-radius:14px;overflow:hidden;"></div>
        <div class="widget-card mt-3" style="height:auto;">
          <div class="widget-title" style="margin-bottom:.5rem;"><i class="bi bi-file-text"></i> Narrative</div>
          <p class="mb-2">${r.description || '<span class="text-muted">No description provided.</span>'}</p>
          ${r.photo_path ? `<img src="${window.BASE_URL}/${r.photo_path}" style="max-width:100%;max-height:260px;border-radius:10px;">` : ''}
        </div>
      </div>
      <div class="col-lg-4">
        <div class="widget-card" style="height:auto;">
          <div class="widget-title" style="margin-bottom:.5rem;"><i class="bi bi-clock-history"></i> Timeline</div>
          ${timeline.map(ev => `
            <div class="d-flex gap-2 mb-2">
              <div class="text-muted small" style="width:52px;flex:none;">${new Date(ev.t).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</div>
              <div><div class="fw-semibold small">${ev.label}</div>${ev.sub ? `<div class="text-muted small">${ev.sub}</div>` : ''}</div>
            </div>`).join('')}
        </div>
        <div class="widget-card mt-3" style="height:auto;" id="alertPredictionCheck">
          <div class="widget-title" style="margin-bottom:.5rem;"><i class="bi bi-graph-up-arrow"></i> Prediction Check</div>
          <p class="text-muted small mb-0">Loading forecast for this barangay…</p>
        </div>
        <div class="widget-card mt-3" style="height:auto;">
          <div class="widget-title" style="margin-bottom:.5rem;"><i class="bi bi-info-circle"></i> Attributes</div>
          <dl class="row small mb-0">
            <dt class="col-5">Coordinates</dt><dd class="col-7 mono">${(+r.latitude).toFixed(4)}, ${(+r.longitude).toFixed(4)}</dd>
            <dt class="col-5">Reporter</dt><dd class="col-7">${r.reporter ?? '—'}</dd>
            <dt class="col-5">Email</dt><dd class="col-7 small">${r.reporter_email ?? '—'}</dd>
          </dl>
        </div>
      </div>
    </div>`;

  // Map
  setTimeout(() => {
    if (alertDetailMap) { alertDetailMap.remove(); alertDetailMap = null; }
    alertDetailMap = L.map('alertDetailMap').setView([r.latitude, r.longitude], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(alertDetailMap);
    L.marker([r.latitude, r.longitude], { icon: hazardDivIcon(r.hazard_type, r.severity) }).addTo(alertDetailMap);
  }, 150);

  // Real prediction check — reuses the same per-barangay forecast the
  // Prediction page computes, not a fabricated score.
  if (r.barangay_id) {
    fetch(window.BASE_URL + '/api/forecast.php?action=map').then(res2 => res2.json()).then(fdata => {
      const b = (fdata.barangays || []).find(x => x.barangay_id == r.barangay_id);
      const el = document.getElementById('alertPredictionCheck');
      if (!el) return;
      if (!b || !b.available) {
        el.querySelector('p').outerHTML = '<p class="text-muted small mb-0">Not enough history to forecast this barangay yet.</p>';
        return;
      }
      if (!b.accepted) {
        el.querySelector('p').outerHTML = `<p class="text-muted small mb-0">Backtest didn't meet the accuracy threshold — forecast withheld for this barangay.</p>`;
        return;
      }
      const diff = b.next_period_predicted - b.last_observed;
      const trend = diff > 0.5 ? ['Rising', 'text-danger'] : diff < -0.5 ? ['Declining', 'text-success'] : ['Stable', 'text-muted'];
      el.querySelector('p').outerHTML = `
        <div class="d-flex align-items-baseline gap-2 mb-1"><span class="fs-4 fw-semibold ${trend[1]}">${b.next_period_predicted}</span><span class="badge text-bg-light border">${trend[0]}</span></div>
        <p class="text-muted small mb-2">Predicted incidents next month for ${r.barangay}, vs. ${b.last_observed} last observed.</p>
        <a href="${window.BASE_URL}/admin/hotspots.php?tab=prediction" class="small">View that forecast &rarr;</a>`;
    }).catch(() => {});
  } else {
    document.getElementById('alertPredictionCheck').querySelector('p').textContent = 'No barangay recorded for this report.';
  }
}
</script>
<?php $pageScripts = ['/assets/js/hazard-icons.js']; require_once __DIR__ . '/../includes/admin_footer.php'; ?>
