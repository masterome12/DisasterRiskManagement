<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['resident', 'drrmo', 'admin']);
$pageTitle = 'Report an Incident — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
$user = current_user();
?>
<div class="container-fluid py-3">
  <div class="row">
    <div class="col-lg-5">
      <h4><i class="bi bi-geo-alt-fill text-success"></i> Report an Incident</h4>
      <p class="text-muted small">
        <?= $user['role'] === 'admin'
              ? 'Pin the location on the map, tag the hazard type, and submit — your report publishes to the map immediately.'
              : 'Pin the location on the map, tag the hazard type, then fill in the details. Reports are reviewed by an Administrator before appearing on the public map (FR-10).' ?>
      </p>

      <div id="reportError" class="alert alert-danger d-none"></div>
      <div id="reportSuccess" class="alert alert-success d-none"></div>

      <form id="reportForm" enctype="multipart/form-data">
        <input type="hidden" name="latitude" id="latitude">
        <input type="hidden" name="longitude" id="longitude">
        <input type="hidden" name="hazard_type" id="hazardTypeInput" required>

        <div class="mb-3">
          <button type="button" id="useGpsBtn" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-crosshair"></i> Use My Current Location
          </button>
          <span class="ms-2 small text-muted" id="pinStatus">No location pinned yet — click the map.</span>
        </div>

        <div class="mb-3">
          <label class="form-label">Hazard Type</label>
          <div class="hazard-grid" id="hazardGrid"></div>
          <select class="form-select form-select-sm" id="hazardMore" style="max-width:220px;"></select>
        </div>
        <div class="mb-3">
          <label class="form-label">Severity</label>
          <select class="form-select" name="severity">
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Barangay</label>
          <select class="form-select" name="barangay_id" id="barangaySelect">
            <option value="">Select…</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Photo (optional, JPG/PNG/WEBP, max 5MB)</label>
          <input type="file" class="form-control" name="photo" accept="image/*">
        </div>
        <button type="submit" class="btn btn-success w-100"><i class="bi bi-send"></i> Submit Report</button>
      </form>
    </div>
    <div class="col-lg-7">
      <div id="pinMap" style="height:min(520px,60vh);border-radius:.5rem;overflow:hidden;"></div>
    </div>
  </div>
</div>

<script>window.APP = { center: [<?= AOI_CENTER_LAT ?>, <?= AOI_CENTER_LNG ?>] };</script>
<?php $pageScripts = ['/assets/js/hazard-icons.js', '/assets/js/report.js']; require_once __DIR__ . '/includes/footer.php'; ?>
