<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = APP_NAME;
require_once __DIR__ . '/includes/header.php';
$user = current_user();
$canReport = $user && in_array($user['role'], ['resident', 'drrmo', 'admin'], true);
?>
<style>
/* Risk map — floating glass toolbar over a full-bleed map, matching the
   uploaded mockup's map-dashboard treatment. Scoped to .rm-* classes local
   to this page only; #map/#searchBox/etc. ids are untouched so map.js,
   quick-report.js, and hazard-icons.js all keep working exactly as before. */
.rm-page { flex: 1; display: flex; flex-direction: column; position: relative; }
.rm-toolbar {
  position: absolute; top: 14px; left: 14px; right: 14px; z-index: 500;
  display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
}
.rm-glass {
  background: rgba(255,255,255,.95); backdrop-filter: blur(6px);
  border: 1px solid rgba(15,95,38,.12); border-radius: 999px;
  box-shadow: 0 2px 10px rgba(6,54,20,.14);
}
.rm-toolbar .rm-glass.rm-search { border-radius: 999px; padding: 0 .25rem 0 .9rem; display: flex; align-items: center; flex: 1 1 260px; max-width: 320px; }
.rm-toolbar .rm-glass.rm-search input { border: 0; background: transparent; box-shadow: none; }
.rm-toolbar .btn { border-radius: 999px; }
.rm-badge {
  padding: .45rem .9rem; font-weight: 600; font-size: .78rem; color: var(--brand-green-dark);
  display: inline-flex; align-items: center;
}
.rm-spacer { flex: 1 1 auto; }
@media (max-width: 576px) {
  .rm-toolbar { position: static; padding: .6rem; background: transparent; }
  .rm-toolbar .rm-glass.rm-search { max-width: none; flex: 1 1 100%; order: 1; }
  .rm-badge { order: 3; flex: 1 1 auto; justify-content: center; }
}
</style>
<div class="rm-page">
  <div class="rm-toolbar">
    <div class="rm-glass rm-search position-relative">
      <i class="bi bi-search text-muted small me-1"></i>
      <input type="text" id="searchBox" class="form-control form-control-sm" style="width:100%"
             placeholder="Search location, barangay, incident...">
      <div id="searchResults" class="list-group position-absolute shadow-sm d-none" style="z-index:1000;top:100%;left:0;width:260px;"></div>
    </div>

    <div class="dropdown">
      <button class="btn btn-sm rm-glass dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-layers"></i> <span class="d-none d-sm-inline">Base Map</span>
      </button>
      <ul class="dropdown-menu">
        <li><button class="dropdown-item base-layer-btn" data-layer="street">Street</button></li>
        <li><button class="dropdown-item base-layer-btn" data-layer="satellite">Satellite</button></li>
      </ul>
    </div>

    <?php if ($user): ?>
    <div class="dropdown">
      <button class="btn btn-sm rm-glass dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-map"></i> <span class="d-none d-sm-inline">Overlay Layers</span>
      </button>
      <ul class="dropdown-menu p-2" id="layerToggleList" style="min-width:240px;">
        <li class="text-muted small px-2">Loading layers…</li>
      </ul>
    </div>
    <?php endif; ?>

    <span class="rm-spacer"></span>
    <span class="rm-glass rm-badge" id="markerCountBadge">Loading incidents…</span>

    <?php if ($canReport): ?>
      <button type="button" id="reportModeBtn" class="btn btn-sm btn-outline-success report-toggle-btn rm-glass" title="Tap the map to report an incident">
        <i class="bi bi-geo-alt"></i> <span class="d-none d-sm-inline">Tap to Report</span>
      </button>
      <a href="<?= BASE_URL ?>/report-incident.php" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Report Incident</span></a>
    <?php endif; ?>
  </div>

  <div id="map"></div>
</div>

<?php if ($canReport): ?>
<!-- Quick report modal: fills in when a location is tapped on the map while Report Mode is on. -->
<div class="modal fade" id="quickReportModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="quickReportForm" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-geo-alt-fill text-success"></i> Report Incident</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="latitude" id="qrLatitude">
          <input type="hidden" name="longitude" id="qrLongitude">
          <input type="hidden" name="hazard_type" id="qrHazardType">

          <div id="qrError" class="alert alert-danger d-none py-2"></div>
          <div id="qrSuccess" class="alert alert-success d-none py-2"></div>

          <p class="small text-muted mb-2" id="qrPinLabel">No location pinned.</p>

          <label class="form-label small mb-1">Hazard Type</label>
          <div class="hazard-grid" id="qrHazardGrid"></div>
          <select class="form-select form-select-sm mb-3" id="qrHazardMore"></select>

          <div class="mb-3">
            <label class="form-label small mb-1">Severity</label>
            <select class="form-select form-select-sm" name="severity" id="qrSeverity">
              <option value="low">Low</option>
              <option value="moderate">Moderate</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small mb-1">Barangay</label>
            <select class="form-select form-select-sm" name="barangay_id" id="qrBarangay">
              <option value="">Select…</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small mb-1">Description</label>
            <textarea class="form-control form-control-sm" name="description" rows="2" required></textarea>
          </div>
          <div class="mb-1">
            <label class="form-label small mb-1">Photo (optional)</label>
            <input type="file" class="form-control form-control-sm" name="photo" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="qrCancelBtn" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-send"></i> Submit Report</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  window.APP = {
    center: [<?= AOI_CENTER_LAT ?>, <?= AOI_CENTER_LNG ?>],
    isAuthenticated: <?= $user ? 'true' : 'false' ?>,
  };
</script>
<?php
$pageScripts = ['/assets/js/hazard-icons.js', '/assets/js/map.js'];
if ($canReport) { $pageScripts[] = '/assets/js/quick-report.js'; }
require_once __DIR__ . '/includes/footer.php';
?>
