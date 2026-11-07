<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);
$activeNav = 'logs';
$pageTitle = 'History Log — ' . APP_NAME;
$pageHeading = 'History Log';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<p class="text-muted small">
  Every account and data-changing action in the system — logins (including failed attempts and
  lockouts), registrations, incident submissions and moderation decisions, geospatial data
  changes, user account changes, report exports, and blocked unauthorized-access attempts.
  <span id="logTotalBadge">…</span> entries recorded.
</p>

<div class="row g-2 mb-3 align-items-end">
  <div class="col-auto">
    <label class="form-label small mb-0">Action type</label>
    <select id="logType" class="form-select form-select-sm pill-select" style="width:200px"><option value="">All</option></select>
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">User</label>
    <select id="logUser" class="form-select form-select-sm pill-select" style="width:200px"><option value="">All</option></select>
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">From</label>
    <input type="date" id="logDateFrom" class="form-control form-control-sm">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">To</label>
    <input type="date" id="logDateTo" class="form-control form-control-sm">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">Search</label>
    <input type="text" id="logSearch" class="form-control form-control-sm" placeholder="Details, user…" style="width:200px">
  </div>
  <div class="col-auto">
    <label class="form-label small mb-0">Rows</label>
    <select id="logLimit" class="form-select form-select-sm pill-select" style="width:90px">
      <option value="50">50</option><option value="100" selected>100</option>
      <option value="250">250</option><option value="500">500</option>
    </select>
  </div>
  <div class="col-auto">
    <button class="btn btn-success btn-sm" onclick="loadAuditLog()"><i class="bi bi-funnel"></i> Apply</button>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-sm table-hover">
    <thead class="sticky-top bg-white"><tr>
      <th>When</th><th>Action</th><th>User</th><th>Details</th><th>IP</th>
    </tr></thead>
    <tbody id="logTable"><tr><td colspan="5" class="text-muted">Loading…</td></tr></tbody>
  </table>
</div>
<p class="text-muted small" id="logShownNote"></p>

<?php $pageScripts = ['/assets/js/logs.js']; require_once __DIR__ . '/../includes/admin_footer.php'; ?>
