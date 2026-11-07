<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['drrmo', 'admin']);
// One page backs two nav entries — "Hotspot Analysis" and "Prediction" —
// since the three tabs below (detection / forecast / reported cases) share
// a lot of state and helper code in hotspots.js; splitting them into
// separate pages would mean duplicating or re-plumbing that JS. ?tab=
// picks which nav item highlights and which tab opens by default.
$initialTab = ($_GET['tab'] ?? '') === 'prediction' ? 'prediction' : 'hotspots';
$activeNav = $initialTab;
$pageTitle = ($initialTab === 'prediction' ? 'Prediction' : 'Hotspot Analysis') . ' — ' . APP_NAME;
$pageHeading = $initialTab === 'prediction' ? 'Prediction' : 'Hotspot Analysis';
require_once __DIR__ . '/../includes/admin_header.php';
?>
<?php
$ahKicker = $initialTab === 'prediction' ? 'Prediction · Linear trend, backtested' : 'Analysis · Incident Clustering';
$ahMethod = $initialTab === 'prediction' ? 'Linear regression' : 'DBSCAN';
?>
<div class="pagehead">
  <div><div class="kicker" id="ahKicker"><?= htmlspecialchars($ahKicker) ?></div><h1 id="ahTitle"><?= htmlspecialchars($pageHeading) ?></h1></div>
  <div class="ph-actions">
    <div class="seg" id="ahMethodBadge" title="This app's real method for this tab"><button type="button" class="active" disabled><?= htmlspecialchars($ahMethod) ?></button></div>
  </div>
</div>
<p class="text-muted small">
  Geospatial hotspot/anomaly detection (FR-19) runs automatically on approved incident data.
  Predictive risk forecasting (FR-20) is a Phase&nbsp;2 capability, gated behind its own backtest — see the badge below.
</p>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link<?= $initialTab === 'hotspots' ? ' active' : '' ?>" id="hotspotTabBtn" data-bs-toggle="tab" data-bs-target="#hotspotTab">Hotspot Analysis</button></li>
  <li class="nav-item"><button class="nav-link<?= $initialTab === 'prediction' ? ' active' : '' ?>" id="forecastTabBtn" data-bs-toggle="tab" data-bs-target="#forecastTab">Prediction</button></li>
  <li class="nav-item"><button class="nav-link" id="reportedCasesTabBtn" data-bs-toggle="tab" data-bs-target="#reportedCasesTab">Reported Cases</button></li>
</ul>

<div class="tab-content">
  <!-- ============================= HOTSPOT TAB ============================= -->
  <div class="tab-pane fade<?= $initialTab === 'hotspots' ? ' show active' : '' ?>" id="hotspotTab">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-0">Time range</label>
        <select id="hotspotWindow" class="form-select form-select-sm pill-select" style="width:150px">
          <option value="30">Last 30 days</option>
          <option value="90" selected>Last 90 days</option>
          <option value="180">Last 6 months</option>
          <option value="365">Last year</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Sensitivity</label>
        <select id="hotspotSensitivity" class="form-select form-select-sm pill-select" style="width:150px">
          <option value="loose">Loose (fewer, wider)</option>
          <option value="balanced" selected>Balanced</option>
          <option value="strict">Strict (tighter)</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Hazard</label>
        <select id="hotspotHazardFilter" class="form-select form-select-sm pill-select" style="width:150px">
          <option value="">All hazards</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-success btn-sm" onclick="runHotspots()"><i class="bi bi-arrow-clockwise"></i> Recompute</button>
      </div>
      <div class="col-auto text-muted small" id="hotspotMeta"></div>
    </div>

    <div class="row g-2 mb-3" id="hotspotKpiRow" style="display:none">
      <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Clusters found</div><div class="kpi-value" id="hkClusters">—</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Incidents clustered</div><div class="kpi-value" id="hkClustered">—</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Largest cluster</div><div class="kpi-value" id="hkLargest">—</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi-card"><div class="kpi-label">Isolated points ignored</div><div class="kpi-value" id="hkNoise">—</div></div></div>
    </div>

    <div class="row g-3">
      <div class="col-lg-7">
        <div id="hotspotMap" style="height:520px;border-radius:.5rem;overflow:hidden;"></div>
      </div>
      <div class="col-lg-5">
        <div id="insufficientNotice" class="alert alert-warning d-none"></div>
        <h6>Ranked Hotspots</h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead><tr><th>#</th><th>Hazard</th><th>Incidents</th><th>Radius (m)</th></tr></thead>
            <tbody id="hotspotTable"><tr><td colspan="4" class="text-muted">Run detection to see results.</td></tr></tbody>
          </table>
        </div>

        <div id="hotspotDetailWrap" style="display:none">
          <h6 class="small text-muted text-uppercase mt-3">Top Cluster</h6>
          <div class="widget-card" style="height:auto;padding:1rem;">
            <div id="hotspotTopDetail"></div>
          </div>

          <h6 class="small text-muted text-uppercase mt-3">Hazard Breakdown (all hotspots)</h6>
          <div id="hotspotHazardBars"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================= FORECAST TAB ============================= -->
  <div class="tab-pane fade<?= $initialTab === 'prediction' ? ' show active' : '' ?>" id="forecastTab">
    <div class="d-flex gap-2 align-items-end mb-3 flex-wrap">
      <div>
        <label class="form-label small mb-0">Barangay (optional, for the trend chart below)</label>
        <select id="forecastBarangay" class="form-select form-select-sm pill-select" style="width:220px">
          <option value="">All South Caloocan</option>
        </select>
      </div>
      <button class="btn btn-success btn-sm" onclick="runForecast()"><i class="bi bi-graph-up-arrow"></i> Train &amp; Backtest</button>
      <span id="forecastBadge"></span>
    </div>
    <div id="forecastNotice" class="alert d-none"></div>

    <!-- Headline stats for the scope currently trained -->
    <div class="row g-2 mb-3" id="forecastStatCards" style="display:none">
      <div class="col-6 col-md-3">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Average / period</div><div class="kpi-value" id="statAvg">—</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Range (min–max)</div><div class="kpi-value" id="statRange">—</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Trend vs. first period</div><div class="kpi-value" id="statTrend">—</div></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Backtest MAE</div><div class="kpi-value" id="statMae">—</div></div>
      </div>
    </div>

    <div class="day-strip mb-3" id="forecastDayStrip" style="display:none"></div>

    <div class="row g-3">
      <div class="col-lg-8">
        <div style="height:320px"><canvas id="forecastChart"></canvas></div>
      </div>
      <div class="col-lg-4">
        <h6 class="small text-muted text-uppercase">Hazard composition (this scope)</h6>
        <div style="height:280px"><canvas id="forecastHazardChart"></canvas></div>
      </div>
    </div>

    <div id="backtestDetailWrap" style="display:none">
      <h6 class="mt-4"><i class="bi bi-clipboard-data"></i> Backtest Detail — Actual vs. Predicted (held-out periods)</h6>
      <p class="text-muted small mb-2">
        The model is trained with these most-recent periods hidden, then asked to predict them —
        this is what NFR-15's accuracy gate is actually checking, laid out period by period.
      </p>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead><tr><th>Period</th><th>Actual</th><th>Predicted</th><th>Error</th></tr></thead>
          <tbody id="backtestDetailTable"></tbody>
        </table>
      </div>
    </div>

    <hr class="my-4">

    <h6><i class="bi bi-geo-alt"></i> Predicted Risk by Barangay</h6>
    <p class="text-muted small mb-2">
      Every barangay is trained and backtested independently, so this shows <em>where</em> elevated
      risk is predicted, not just a citywide number. Barangays without enough history, or whose
      backtest didn't meet the NFR-15 accuracy threshold, are shown gray with the forecast withheld.
      Pick a barangay from the dropdown above to see its own trend chart.
    </p>

    <div class="row g-2 mb-3" id="forecastSummaryCards" style="display:none">
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Citywide predicted</div><div class="kpi-value" style="font-size:1.3rem" id="sumCitywide">—</div></div>
      </div>
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Trained &amp; accepted</div><div class="kpi-value" style="font-size:1.3rem" id="sumAccepted">—</div></div>
      </div>
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Rising</div><div class="kpi-value text-danger" style="font-size:1.3rem" id="sumRising">—</div></div>
      </div>
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Declining</div><div class="kpi-value text-success" style="font-size:1.3rem" id="sumDeclining">—</div></div>
      </div>
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Stable</div><div class="kpi-value" style="font-size:1.3rem" id="sumStable">—</div></div>
      </div>
      <div class="col-6 col-md-2">
        <div class="kpi-card text-center py-2" style="height:auto"><div class="kpi-label">Withheld / insufficient</div><div class="kpi-value text-muted" style="font-size:1.3rem" id="sumWithheld">—</div></div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-8">
        <h6 class="small text-muted text-uppercase mt-3">Top 10 predicted (accepted forecasts only)</h6>
        <div style="height:280px"><canvas id="forecastBarangayChart"></canvas></div>
      </div>
      <div class="col-lg-4">
        <h6 class="small text-muted text-uppercase mt-3">Rising-Risk Watchlist</h6>
        <div id="forecastWatchlist"><p class="text-muted small">Loading…</p></div>
      </div>
    </div>

    <div class="table-responsive mt-3" style="max-height:420px;overflow-y:auto;">
      <table class="table table-sm table-hover">
        <thead class="sticky-top bg-white"><tr><th>Barangay</th><th>Predicted next month</th><th>vs. last observed</th><th>Backtest</th></tr></thead>
        <tbody id="forecastMapTable"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ============================= REPORTED CASES TAB ============================= -->
  <div class="tab-pane fade" id="reportedCasesTab">
    <p class="text-muted small">
      Browses the imported historical multi-hazard dataset (<span id="rcTotalBadge">…</span> records,
      <span id="rcRangeBadge"></span>) and predicts next year's case volume per barangay from it —
      independent of the live incident reports used by the tabs above.
    </p>

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-0">Hazard</label>
        <select id="rcHazard" class="form-select form-select-sm pill-select" style="width:150px"><option value="">All</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Severity</label>
        <select id="rcSeverity" class="form-select form-select-sm pill-select" style="width:130px">
          <option value="">All</option>
          <option value="low">Low</option><option value="moderate">Moderate</option>
          <option value="high">High</option><option value="critical">Critical</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">District</label>
        <select id="rcDistrict" class="form-select form-select-sm pill-select" style="width:170px"><option value="">All</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Year from</label>
        <input type="number" id="rcYearFrom" class="form-control form-control-sm" style="width:90px">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Year to</label>
        <input type="number" id="rcYearTo" class="form-control form-control-sm" style="width:90px">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Rows</label>
        <select id="rcTableLimit" class="form-select form-select-sm pill-select" style="width:90px">
          <option value="25">25</option><option value="50" selected>50</option>
          <option value="100">100</option><option value="250">250</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-success btn-sm" onclick="loadReportedCases()"><i class="bi bi-funnel"></i> Apply</button>
      </div>
    </div>

    <div class="table-responsive mb-4" style="max-height:360px;overflow-y:auto;">
      <table class="table table-sm table-hover">
        <thead class="sticky-top bg-white"><tr>
          <th>Date</th><th>District</th><th>Barangay</th><th>Hazard</th><th>Severity</th>
          <th>Casualties</th><th>Injuries</th><th>Damage (₱)</th><th>Detail</th>
        </tr></thead>
        <tbody id="rcTable"><tr><td colspan="9" class="text-muted">Loading…</td></tr></tbody>
      </table>
    </div>
    <p class="text-muted small" id="rcShownNote"></p>

    <hr class="my-4">

    <h6><i class="bi bi-graph-up-arrow"></i> Predicted Risk by Barangay (Next Year)</h6>
    <p class="text-muted small mb-2">
      Every barangay is trained on its own full yearly history and backtested independently.
      Pick how many to list from the dropdown below.
    </p>
    <div class="d-flex align-items-end gap-2 mb-3">
      <div>
        <label class="form-label small mb-0">Show top</label>
        <select id="rcPredictLimit" class="form-select form-select-sm pill-select" style="width:110px">
          <option value="5">5</option><option value="10" selected>10</option>
          <option value="20">20</option><option value="47">All (47)</option>
        </select>
      </div>
      <button class="btn btn-success btn-sm" onclick="loadReportedCasesPredictions()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover">
        <thead><tr><th>Barangay</th><th>Hazard</th><th>Predicted</th><th>Backtest</th></tr></thead>
        <tbody id="rcPredictTable"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  window.APP = { center: [<?= AOI_CENTER_LAT ?>, <?= AOI_CENTER_LNG ?>] };
  // The Prediction tab's data lazy-loads on Bootstrap's "shown.bs.tab" event
  // (see hotspots.js) — which only fires on a user-driven tab switch, never
  // for a tab that's already server-rendered active on page load. When the
  // Prediction nav link (?tab=prediction) makes it the initial tab, fire
  // that same event manually so its data loads without an extra click.
  document.addEventListener('DOMContentLoaded', () => {
    if (<?= json_encode($initialTab) ?> === 'prediction') {
      document.getElementById('forecastTabBtn').dispatchEvent(new Event('shown.bs.tab'));
    }
  });
  // Pagehead kicker/title track whichever tab is actually showing.
  const AH_TITLES = {
    hotspotTabBtn: ['Analysis · Incident Clustering', 'Hotspot Analysis', 'DBSCAN'],
    forecastTabBtn: ['Prediction · Linear trend, backtested', 'Prediction', 'Linear regression'],
    reportedCasesTabBtn: ['Records · Historical Dataset', 'Reported Cases', 'Linear regression'],
  };
  Object.keys(AH_TITLES).forEach(id => {
    document.getElementById(id).addEventListener('shown.bs.tab', () => {
      document.getElementById('ahKicker').textContent = AH_TITLES[id][0];
      document.getElementById('ahTitle').textContent = AH_TITLES[id][1];
      document.getElementById('ahMethodBadge').innerHTML = `<button type="button" class="active" disabled>${AH_TITLES[id][2]}</button>`;
    });
  });
</script>
<?php $pageScripts = ['/assets/js/hazard-icons.js', '/assets/js/hotspots.js', '/assets/js/reported-cases.js']; require_once __DIR__ . '/../includes/admin_footer.php'; ?>
