<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$activeNav = 'overview';
$pageTitle = 'Map Dashboard — ' . APP_NAME;
$pageHeading = 'Map Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<div class="pagehead">
  <div><div class="kicker">Operations · Citywide</div><h1>Map Dashboard</h1></div>
  <div class="ph-actions">
    <div class="seg" id="dashRangeSeg">
      <button type="button" data-days="7">7d</button>
      <button type="button" data-days="30" class="active">30d</button>
      <button type="button" data-days="90">90d</button>
      <button type="button" data-days="0">All time</button>
    </div>
    <a href="<?= BASE_URL ?>/api/reports.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> Export report</a>
    <button class="btn btn-outline-secondary btn-sm" onclick="loadOverview()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    <a href="<?= BASE_URL ?>/admin/hotspots.php?tab=prediction" class="btn btn-success btn-sm"><i class="bi bi-graph-up-arrow"></i> Run forecast</a>
  </div>
</div>

<div class="row g-3 mb-3" id="kpiRow">
  <div class="col-12 text-muted small">Loading overview…</div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-3">
    <div class="widget-card" style="height:auto;">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-stack"></i></span>
        Layers
      </div>
      <div id="dashLayerList"><p class="text-muted small mb-0">Loading…</p></div>
      <hr class="my-2">
      <div class="mb-2">
        <div class="d-flex justify-content-between kpi-label" style="margin:0;"><span>Minimum severity</span><span id="dashSeverityLabel">Any</span></div>
        <input type="range" class="form-range" id="dashSeverityRange" min="0" max="3" step="1" value="0">
      </div>
      <div>
        <label class="kpi-label d-block mb-1">Hazard filter</label>
        <select class="form-select form-select-sm pill-select" id="dashHazardFilter"><option value="">All hazards</option></select>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="widget-card" style="height:auto;padding:0;overflow:hidden;">
      <div style="position:relative;">
        <div class="dash-maptools">
          <button type="button" class="active" id="dashVizPins">Clustered pins</button>
          <button type="button" id="dashVizHeat">Kernel density</button>
          <button type="button" id="dashFitCity">Fit city</button>
        </div>
        <div id="dashMap" style="height:420px;"></div>
        <div class="dash-maplegend glass-panel">
          <div class="fw-semibold mb-1">Hazard type</div>
          <div id="dashMapLegend"></div>
        </div>
        <span class="badge text-bg-light border" style="position:absolute;top:12px;right:12px;z-index:500;" id="dashMapBadge"></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3">
    <div class="widget-card" style="height:auto;">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-fire"></i></span>
        Ranked Hotspots
        <span class="badge text-bg-light border ms-1" id="dashHotspotCount"></span>
      </div>
      <p class="text-muted small mb-2">Balanced sensitivity, last 90 days.</p>
      <div id="dashHotspotList" style="max-height:280px;overflow-y:auto;"><p class="text-muted small mb-0">Loading…</p></div>
      <a href="<?= BASE_URL ?>/admin/hotspots.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">Open Hotspot Analysis</a>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-graph-up-arrow"></i></span>
        Incident Trend
      </div>
      <canvas id="chartTrend" height="220"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-pie-chart"></i></span>
        Report Status
      </div>
      <canvas id="chartStatus" height="220"></canvas>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-clock-history"></i></span>
        Peak Reporting Hours
      </div>
      <canvas id="chartPeakHours" height="200"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-trophy"></i></span>
        Top Barangays
      </div>
      <div id="topBarangaysList"><p class="text-muted small mb-0">Loading…</p></div>
    </div>
  </div>
</div>

<script>window.APP = { center: [<?= AOI_CENTER_LAT ?>, <?= AOI_CENTER_LNG ?>] };</script>
<?php $pageScripts = ['/assets/js/hazard-icons.js', '/assets/js/admin-dashboard.js', '/assets/js/admin-dashboard-map.js']; require_once __DIR__ . '/../includes/admin_footer.php'; ?>
