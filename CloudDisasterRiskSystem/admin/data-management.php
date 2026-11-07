<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$activeNav = 'data';
$pageTitle = 'Data & Layers — ' . APP_NAME;
$pageHeading = 'Data & Layers';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="pagehead">
  <div><div class="kicker">Records · Geospatial Catalogue</div><h1>Data &amp; Layers</h1></div>
</div>
<p class="text-muted small">Upload baseline/historical hazard datasets (FR-11) and manage existing layers (FR-12).</p>

<div class="widget-card mb-4" style="height:auto;">
  <div class="widget-title">
    <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-upload"></i></span>
    Upload Dataset
  </div>
  <div id="uploadMsg" class="alert d-none"></div>
  <form id="uploadForm" enctype="multipart/form-data">
    <div class="dropzone mb-3" id="dropzone">
      <div class="dropzone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
      <h6 class="mb-1">Drag a file here, or choose one</h6>
      <p class="text-muted small mb-2" style="max-width:52ch;margin-inline:auto">
        Accepts .geojson, .json, .csv. Any CSV is loaded as a table; rows with a recognizable
        lat/lng-style column pair (lat/latitude/y, lng/lon/longitude/x) are also plotted on the
        map. Shapefile requires pre-conversion.
      </p>
      <input class="d-none" type="file" name="dataset" id="datasetFile" accept=".geojson,.json,.csv" required>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="chooseFileBtn"><i class="bi bi-folder2-open"></i> Choose file</button>
      <div class="small mt-2 fw-semibold text-success" id="chosenFileName"></div>
    </div>
    <div class="row g-2">
      <div class="col-md-4"><input class="form-control" name="name" placeholder="Layer name" required></div>
      <div class="col-md-4">
        <select class="form-select pill-select" name="layer_type">
          <option value="hazard_zone">Hazard Zone</option>
          <option value="barangay_boundary">Barangay Boundary</option>
          <option value="evacuation_route">Evacuation Route</option>
          <option value="baseline_dataset">Baseline Dataset</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="col-md-4"><input class="form-control" name="description" placeholder="Description (optional)"></div>
    </div>
    <div class="mt-3"><button class="btn btn-success btn-sm"><i class="bi bi-upload"></i> Upload &amp; Import</button></div>
  </form>
</div>

<div class="widget-card" style="height:auto;">
  <div class="widget-title">
    <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-layers"></i></span>
    Existing Layers
  </div>
  <div class="table-responsive sticky-col-table">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Name</th><th>Type</th><th>Rows</th><th>Uploaded By</th><th>Date</th><th>Visible</th><th></th></tr></thead>
      <tbody id="layersBody"><tr><td colspan="7" class="text-muted">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Table preview modal for CSV-sourced layers -->
<div class="modal fade" id="layerDataModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="layerDataModalTitle"><i class="bi bi-table"></i> Dataset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="layerDataModalBody">
        <div class="text-muted">Loading…</div>
      </div>
    </div>
  </div>
</div>

<script>
let layersCache = [];
let layerDataModal;

// ---- Dropzone: click-to-browse + drag-and-drop, feeding the same hidden
// file input the form already submits — no change to the upload logic. ----
(function () {
  const zone = document.getElementById('dropzone');
  const fileInput = document.getElementById('datasetFile');
  const chooseBtn = document.getElementById('chooseFileBtn');
  const nameLabel = document.getElementById('chosenFileName');

  function showChosen() {
    nameLabel.textContent = fileInput.files.length ? `Selected: ${fileInput.files[0].name}` : '';
  }
  chooseBtn.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', showChosen);
  ['dragenter', 'dragover'].forEach(evt => zone.addEventListener(evt, (e) => {
    e.preventDefault(); e.stopPropagation(); zone.classList.add('dragover');
  }));
  ['dragleave', 'drop'].forEach(evt => zone.addEventListener(evt, (e) => {
    e.preventDefault(); e.stopPropagation(); zone.classList.remove('dragover');
  }));
  zone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      showChosen();
    }
  });
})();

async function loadLayers() {
  const res = await fetch(window.BASE_URL + '/api/layers.php?action=list&all=1');
  const data = await res.json();
  const body = document.getElementById('layersBody');
  if (!data.layers || !data.layers.length) {
    body.innerHTML = '<tr><td colspan="7" class="text-muted">No layers yet.</td></tr>';
    layersCache = [];
    return;
  }
  layersCache = data.layers;
  body.innerHTML = data.layers.map(l => {
    const isCsv = l.source_format === 'csv';
    const rowsCell = isCsv
      ? `${l.row_count ?? 0} row(s)${l.mapped_count > 0 ? ` <span class="badge text-bg-light border">${l.mapped_count} mapped</span>` : ' <span class="badge text-bg-light border text-muted">table-only</span>'}`
      : '<span class="text-muted">—</span>';
    return `
    <tr>
      <td>${l.name}${l.description ? `<div class="text-muted small">${l.description}</div>` : ''}</td>
      <td><span class="badge text-bg-light border">${l.layer_type}</span></td>
      <td class="small">${rowsCell}</td>
      <td>${l.uploaded_by ?? '—'}</td>
      <td>${new Date(l.created_at).toLocaleDateString()}</td>
      <td>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" ${l.is_visible == 1 ? 'checked' : ''} onchange="toggleLayer(${l.id}, this.checked)">
        </div>
      </td>
      <td class="text-nowrap">
        ${isCsv ? `<button class="btn btn-sm btn-outline-secondary" onclick="viewLayerData(${l.id})"><i class="bi bi-table"></i> View</button>` : ''}
        <button class="btn btn-sm btn-outline-danger" onclick="deleteLayer(${l.id}, '${l.name.replace(/'/g,"\\'")}')"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
}

function viewLayerData(id) {
  const l = layersCache.find(x => x.id === id);
  if (!l) return;
  if (!layerDataModal) layerDataModal = new bootstrap.Modal(document.getElementById('layerDataModal'));
  document.getElementById('layerDataModalTitle').innerHTML = `<i class="bi bi-table"></i> ${l.name}`;

  const features = (l.geojson && l.geojson.features) || [];
  const body = document.getElementById('layerDataModalBody');
  if (!features.length) {
    body.innerHTML = '<div class="text-muted">No rows in this dataset.</div>';
    return;
  }
  const columns = Object.keys(features[0].properties || {});
  const rowsHtml = features.slice(0, 500).map(f => `
    <tr>${columns.map(c => `<td>${f.properties[c] ?? ''}</td>`).join('')}<td>${f.geometry ? '<span class="badge text-bg-success">mapped</span>' : '<span class="text-muted small">—</span>'}</td></tr>`).join('');
  body.innerHTML = `
    <p class="text-muted small">${features.length} row(s)${features.length > 500 ? ' (showing first 500)' : ''}.</p>
    <div class="table-responsive" style="max-height:60vh;overflow-y:auto;">
      <table class="table table-sm table-hover">
        <thead class="sticky-top bg-white"><tr>${columns.map(c => `<th>${c}</th>`).join('')}<th>Geometry</th></tr></thead>
        <tbody>${rowsHtml}</tbody>
      </table>
    </div>`;
  layerDataModal.show();
}

async function toggleLayer(id, visible) {
  await fetch(window.BASE_URL + '/api/layers.php?action=toggle', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, visible})
  });
}

async function deleteLayer(id, name) {
  if (!confirm(`Delete layer "${name}"? This cannot be undone.`)) return; // FR-12: deletion requires confirmation
  const res = await fetch(window.BASE_URL + '/api/layers.php?action=delete', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})
  });
  if (res.ok) loadLayers();
}

document.getElementById('uploadForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('uploadMsg');
  msg.className = 'alert d-none';
  const res = await fetch(window.BASE_URL + '/api/layers.php?action=upload', { method:'POST', body: new FormData(e.target) });
  const data = await res.json();
  msg.classList.remove('d-none');
  msg.classList.add(res.ok ? 'alert-success' : 'alert-danger');
  msg.textContent = data.message || data.error;
  if (res.ok) { e.target.reset(); document.getElementById('chosenFileName').textContent = ''; loadLayers(); }
});

loadLayers();
</script>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
