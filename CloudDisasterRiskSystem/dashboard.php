<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['drrmo', 'admin']);
$activeNav = 'analytics';
$pageTitle = 'Statistical Dashboard — ' . APP_NAME;
$pageHeading = 'Statistical Dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex justify-content-end flex-wrap gap-2 mb-3">
  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/admin/hotspots.php"><i class="bi bi-cpu"></i> AI Hotspot Analysis</a>
  <form class="d-flex gap-1" action="<?= BASE_URL ?>/api/reports.php" method="get" target="_blank">
    <input type="date" class="form-control form-control-sm" name="from">
    <input type="date" class="form-control form-control-sm" name="to">
    <button class="btn btn-sm btn-success"><i class="bi bi-download"></i> Export CSV</button>
  </form>
</div>

<div class="row g-3 mb-3" id="statCards"></div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-bar-chart"></i></span>
        Incidents by Hazard Type
      </div>
      <canvas id="chartByType" height="220"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-signpost-split"></i></span>
        Incidents by Barangay
      </div>
      <canvas id="chartByBarangay" height="220"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-green-light);color:var(--brand-green-dark);"><i class="bi bi-pie-chart"></i></span>
        Incidents by Severity
      </div>
      <canvas id="chartBySeverity" height="220"></canvas>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="widget-card">
      <div class="widget-title">
        <span class="widget-icon" style="background:var(--brand-orange-light);color:var(--brand-orange);"><i class="bi bi-graph-up"></i></span>
        Monthly Trend (last 12 months)
      </div>
      <canvas id="chartTrend" height="220"></canvas>
    </div>
  </div>
</div>

<?php $pageScripts = ['/assets/js/dashboard.js']; require_once __DIR__ . '/includes/admin_footer.php'; ?>
